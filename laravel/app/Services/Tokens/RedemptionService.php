<?php

namespace App\Services\Tokens;

use App\Models\AccessToken;
use App\Models\BandwidthLimit;
use App\Models\BlockedDevice;
use App\Models\DeviceSession;
use App\Models\NetworkDevice;
use App\Models\NetworkEvent;
use App\Models\TokenRedemption;
use App\Services\Network\GatewayException;
use App\Services\Network\NetworkGateway;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The only code path that turns a voucher into network access.
 *
 * Concurrency: every redemption runs in one DB transaction that takes a row lock
 * (SELECT ... FOR UPDATE) on the token. Two simultaneous redemptions of the same token are
 * serialised; the second sees the committed state (activated / device limit reached).
 * A UNIQUE index (device_sessions.active_device_id) additionally guarantees one live session per device.
 * The firewall grant happens INSIDE the transaction: if enforcement fails, activation rolls back.
 */
class RedemptionService
{
    public function __construct(private NetworkGateway $gateway)
    {
    }

    /**
     * @throws TokenException when refused (message is customer-safe)
     * @throws GatewayException when the OS could not enforce the grant (nothing is committed)
     */
    public function redeem(string $code, string $mac, ?string $ip = null, ?string $userAgent = null): DeviceSession
    {
        $mac = TokenService::normalizeMac($mac);
        $limiterKey = 'redeem|'.$mac.'|'.($ip ?? '-');
        $maxFails = (int) Settings::get('token.max_failed_attempts', '5');

        if (RateLimiter::tooManyAttempts($limiterKey, $maxFails)) {
            $mins = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);
            throw new TokenException('locked', "Too many failed attempts. Please try again in {$mins} minute(s).");
        }

        $normalized = TokenService::normalize($code);
        if (strlen($normalized) < 8 || strlen($normalized) > 32) {
            $this->fail($limiterKey, 'invalid', null, $mac, $ip);
        }

        try {
            return DB::transaction(function () use ($normalized, $mac, $ip, $userAgent) {
                $token = AccessToken::where('code_hash', TokenService::hash($normalized))->lockForUpdate()->first();
                if (! $token) {
                    throw TokenException::invalid();
                }

                $device = $this->deviceFor($mac, $ip);

                if (BlockedDevice::where('network_device_id', $device->id)->whereNull('unblocked_at')->exists()) {
                    throw new TokenException('blocked', 'This device has been blocked. Please contact support.');
                }

                $this->assertTokenUsable($token, $mac);

                $redemption = TokenRedemption::where('access_token_id', $token->id)
                    ->where('network_device_id', $device->id)->first();

                if (! $redemption) {
                    $bound = TokenRedemption::where('access_token_id', $token->id)->count();
                    if ($bound >= $token->max_devices) {
                        throw new TokenException('device_limit',
                            'This token has reached its device limit ('.$token->max_devices.').');
                    }
                }

                $firstUse = $token->status === AccessToken::UNUSED;
                if ($firstUse) {
                    $now = now();
                    $token->forceFill([
                        'status' => AccessToken::ACTIVE,
                        'activated_at' => $now,
                        'expires_at' => $now->copy()->addMinutes($token->duration_minutes),
                        'code_encrypted' => null, // plaintext copy no longer needed
                    ])->save();
                }

                $redemption ??= TokenRedemption::create([
                    'access_token_id' => $token->id,
                    'network_device_id' => $device->id,
                    'ip_address' => $ip,
                    'mac' => $mac,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                    'is_first_use' => $firstUse,
                    'redeemed_at' => now(),
                ]);

                // A device holds one live session at a time (e.g. switching tokens).
                $this->endSessions(DeviceSession::where('network_device_id', $device->id)->where('status', 'active')->lockForUpdate()->get(), 'replaced');

                $session = DeviceSession::create([
                    'access_token_id' => $token->id,
                    'network_device_id' => $device->id,
                    'ip_address' => $ip,
                    'mac' => $mac,
                    'status' => 'active',
                    'active_device_id' => $device->id,
                    'started_at' => now(),
                    'last_activity_at' => now(),
                    'ends_at' => $token->expires_at,
                ]);

                $device->forceFill(['status' => 'online', 'last_seen_at' => now(), 'last_ip' => $ip])->save();

                $remaining = $token->data_limit_bytes === null ? null : max(0, $token->data_limit_bytes - $token->data_used_bytes);
                $this->gateway->authorize($session, $this->limitsFor($token, $remaining));

                Audit::log('token.redeemed', $token, null, [
                    'device' => $mac, 'first_use' => $firstUse, 'session_id' => $session->id,
                ]);

                return $session;
            });
        } catch (TokenException $e) {
            $this->fail($limiterKey, $e->reason, null, $mac, $ip, $e);
        } catch (GatewayException $e) {
            NetworkEvent::create(['type' => 'authorize_failed', 'severity' => 'error',
                'message' => 'Gateway refused to authorize a device: '.$e->getMessage(),
                'payload' => ['mac' => $mac], 'occurred_at' => now()]);
            throw $e;
        }
    }

    private function assertTokenUsable(AccessToken $token, string $mac): void
    {
        $now = now();

        // Lazily settle states that the scheduler would also settle, so we are correct even between runs.
        if ($token->status === AccessToken::ACTIVE) {
            if ($token->expires_at && $token->expires_at->lte($now)) {
                $token->forceFill(['status' => AccessToken::EXPIRED])->save();
            } elseif ($token->data_limit_bytes !== null && $token->data_used_bytes >= $token->data_limit_bytes) {
                $token->forceFill(['status' => AccessToken::EXHAUSTED])->save();
            }
        }
        if ($token->status === AccessToken::UNUSED && $token->valid_until && $token->valid_until->lte($now)) {
            $token->forceFill(['status' => AccessToken::EXPIRED])->save();
        }

        $messages = [
            AccessToken::EXPIRED => 'This token has expired.',
            AccessToken::EXHAUSTED => 'This token has used all of its data.',
            AccessToken::SUSPENDED => 'This token is suspended. Please contact support.',
            AccessToken::REVOKED => 'This token is no longer valid.',
        ];
        if (isset($messages[$token->status])) {
            throw new TokenException($token->status, $messages[$token->status]);
        }

        if ($token->restricted_mac !== null && $token->restricted_mac !== $mac) {
            throw new TokenException('wrong_device', 'This token can only be used on its registered device.');
        }
    }

    private function deviceFor(string $mac, ?string $ip): NetworkDevice
    {
        $device = NetworkDevice::firstOrCreate(['mac' => $mac], [
            'mac_is_randomized' => NetworkDevice::isRandomizedMac($mac),
            'first_seen_at' => now(), 'last_seen_at' => now(), 'last_ip' => $ip,
        ]);

        return $device;
    }

    /** @return array{download_kbps:?int, upload_kbps:?int, session_seconds:int, quota_bytes:?int} */
    public function limitsFor(AccessToken $token, ?int $remainingBytes): array
    {
        // Most specific wins: token override > package > global.
        $limit = BandwidthLimit::where('is_active', true)->where('scope', 'token')->where('access_token_id', $token->id)->first()
            ?? BandwidthLimit::where('is_active', true)->where('scope', 'package')->where('internet_package_id', $token->internet_package_id)->first();

        $down = $limit?->download_kbps ?? (Settings::get('bandwidth.global_download_kbps') ?: null);
        $up = $limit?->upload_kbps ?? (Settings::get('bandwidth.global_upload_kbps') ?: null);

        $seconds = $token->expires_at ? max(1, (int) now()->diffInSeconds($token->expires_at, false)) : $token->duration_minutes * 60;

        return [
            'download_kbps' => $down === null ? null : (int) $down,
            'upload_kbps' => $up === null ? null : (int) $up,
            'session_seconds' => $seconds,
            'quota_bytes' => $remainingBytes,
        ];
    }

    public function endSessions($sessions, string $reason): void
    {
        foreach ($sessions as $s) {
            $s->forceFill([
                'status' => 'ended', 'active_device_id' => null, 'ended_at' => now(), 'end_reason' => $reason,
            ])->save();
        }
    }

    private function fail(string $key, string $reason, ?int $tokenId, string $mac, ?string $ip, ?TokenException $e = null): never
    {
        // Only count genuine guessing against the lockout (not "expired", "blocked", etc. for a real code).
        if (in_array($reason, ['invalid'], true)) {
            RateLimiter::hit($key, (int) config('nws.redeem_lockout_seconds'));
        }
        Audit::log('token.redeem_failed', null, null, ['reason' => $reason, 'mac' => $mac, 'ip' => $ip]);

        throw $e ?? TokenException::invalid();
    }
}
