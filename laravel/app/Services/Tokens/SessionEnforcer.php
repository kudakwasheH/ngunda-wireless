<?php

namespace App\Services\Tokens;

use App\Models\AccessToken;
use App\Models\DeviceSession;
use App\Models\NetworkEvent;
use App\Models\UsageRecord;
use App\Services\Network\GatewayException;
use App\Services\Network\NetworkGateway;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Token lifecycle after redemption: revoke / suspend, usage accounting, automatic expiry and exhaustion.
 * Database state is changed first (source of truth); OS enforcement follows and failures are
 * recorded as network events so the reconciler can retry. Python also expires grants on its own.
 */
class SessionEnforcer
{
    public function __construct(private NetworkGateway $gateway)
    {
    }

    public function revoke(AccessToken $token, ?int $userId, ?string $reason = null): void
    {
        $this->terminal($token, AccessToken::REVOKED, 'revoked', function (AccessToken $t) use ($userId, $reason) {
            $t->forceFill(['revoked_at' => now(), 'revoked_by' => $userId, 'revoke_reason' => $reason]);
        });
        Audit::log('token.revoked', $token, null, ['reason' => $reason]);
    }

    public function suspend(AccessToken $token, ?string $reason = null): void
    {
        $this->terminal($token, AccessToken::SUSPENDED, 'suspended', fn () => null);
        Audit::log('token.suspended', $token, null, ['reason' => $reason]);
    }

    public function unsuspend(AccessToken $token): void
    {
        DB::transaction(function () use ($token) {
            $t = AccessToken::lockForUpdate()->findOrFail($token->id);
            if ($t->status !== AccessToken::SUSPENDED) {
                return;
            }
            $t->status = $t->activated_at ? AccessToken::ACTIVE : AccessToken::UNUSED;
            $t->save();
        });
        Audit::log('token.unsuspended', $token);
    }

    /**
     * Apply measured traffic (called by the network service sync). Returns true if the token was exhausted.
     */
    public function recordUsage(DeviceSession $session, int $downDelta, int $upDelta, int $rateDownBps = 0, int $rateUpBps = 0): bool
    {
        $downDelta = max(0, $downDelta);
        $upDelta = max(0, $upDelta);

        $exhausted = DB::transaction(function () use ($session, $downDelta, $upDelta, $rateDownBps, $rateUpBps) {
            $token = AccessToken::lockForUpdate()->findOrFail($session->access_token_id);

            DeviceSession::where('id', $session->id)->update([
                'bytes_down' => DB::raw('bytes_down + '.$downDelta),
                'bytes_up' => DB::raw('bytes_up + '.$upDelta),
                'last_activity_at' => now(),
            ]);
            UsageRecord::create([
                'device_session_id' => $session->id, 'recorded_at' => now(),
                'bytes_down_delta' => $downDelta, 'bytes_up_delta' => $upDelta,
                'rate_down_bps' => max(0, $rateDownBps), 'rate_up_bps' => max(0, $rateUpBps),
            ]);

            // Data allowance counts download + upload.
            $token->data_used_bytes += $downDelta + $upDelta;

            if ($token->status === AccessToken::ACTIVE && $token->data_limit_bytes !== null
                && $token->data_used_bytes >= $token->data_limit_bytes) {
                $token->status = AccessToken::EXHAUSTED;
                $token->save();

                return true;
            }
            $token->save();

            return false;
        });

        if ($exhausted) {
            $this->endTokenSessions(AccessToken::find($session->access_token_id), 'exhausted');
            Audit::log('token.exhausted', AccessToken::find($session->access_token_id));
        }

        return $exhausted;
    }

    /** Scheduler entry point (every minute). Returns the number of tokens/sessions it settled. */
    public function sweep(): int
    {
        $n = 0;

        // Time expiry
        AccessToken::where('status', AccessToken::ACTIVE)->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->pluck('id')->each(function ($id) use (&$n) {
                $t = DB::transaction(function () use ($id) {
                    $t = AccessToken::lockForUpdate()->find($id);
                    if ($t && $t->status === AccessToken::ACTIVE && $t->expires_at->lte(now())) {
                        $t->forceFill(['status' => AccessToken::EXPIRED])->save();

                        return $t;
                    }

                    return null;
                });
                if ($t) {
                    $this->endTokenSessions($t, 'expired');
                    $n++;
                }
            });

        // Unredeemed vouchers past their redeem-by date
        AccessToken::where('status', AccessToken::UNUSED)->whereNotNull('valid_until')->where('valid_until', '<=', now())
            ->update(['status' => AccessToken::EXPIRED]);

        // Defence in depth: any live session past its hard stop is closed even if its token row was missed.
        DeviceSession::where('status', 'active')->whereNotNull('ends_at')->where('ends_at', '<=', now())->get()
            ->each(function (DeviceSession $s) use (&$n) {
                $this->closeSession($s, 'expired');
                $n++;
            });

        return $n;
    }

    // ---------------------------------------------------------------- internals

    private function terminal(AccessToken $token, string $status, string $endReason, callable $mutate): void
    {
        $locked = DB::transaction(function () use ($token, $status, $mutate) {
            $t = AccessToken::lockForUpdate()->findOrFail($token->id);
            $mutate($t);
            $t->status = $status;
            $t->save();

            return $t;
        });
        $token->setRawAttributes($locked->getAttributes(), true);
        $this->endTokenSessions($locked, $endReason);
    }

    private function endTokenSessions(AccessToken $token, string $reason): void
    {
        DeviceSession::where('access_token_id', $token->id)->where('status', 'active')->get()
            ->each(fn (DeviceSession $s) => $this->closeSession($s, $reason));
    }

    private function closeSession(DeviceSession $s, string $reason): void
    {
        $s->forceFill(['status' => 'ended', 'active_device_id' => null, 'ended_at' => now(), 'end_reason' => $reason])->save();
        $s->device?->forceFill(['status' => $s->device->status === 'blocked' ? 'blocked' : 'offline'])->save();

        try {
            $this->gateway->deauthorize($s, $reason);
        } catch (GatewayException $e) {
            // DB says ended; record that the OS side must be reconciled (Python also expires grants itself).
            NetworkEvent::create(['type' => 'deauth_failed', 'severity' => 'error',
                'network_device_id' => $s->network_device_id,
                'message' => 'Could not remove firewall access for ended session: '.$e->getMessage(),
                'payload' => ['session_id' => $s->id, 'mac' => $s->mac, 'reason' => $reason], 'occurred_at' => now()]);
        }
    }
}
