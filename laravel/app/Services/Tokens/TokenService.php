<?php

namespace App\Services\Tokens;

use App\Models\AccessToken;
use App\Models\InternetPackage;
use App\Models\TokenBatch;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TokenService
{
    /** Canonical form used for hashing: upper-case alphanumerics only. */
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function hash(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), config('nws.token_pepper'));
    }

    public static function format(string $code): string
    {
        return implode('-', str_split(self::normalize($code), 5));
    }

    /** Cryptographically secure random code from the unambiguous alphabet. */
    public function randomCode(int $length): string
    {
        $alphabet = config('nws.token_alphabet');
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Generate tokens in one batch.
     *
     * @param  array{name?:string, is_promotional?:bool, max_devices?:int, duration_minutes?:int,
     *               data_limit_mb?:?int, restricted_mac?:?string, valid_until?:?\DateTimeInterface,
     *               length?:int, notes?:?string, created_by?:?int}  $opts   (overrides allow custom packages)
     * @return array{batch:TokenBatch, codes:array<int,string>}  plaintext codes — shown ONCE
     */
    public function generate(InternetPackage $package, int $quantity, array $opts = []): array
    {
        if ($quantity < 1 || $quantity > 1000) {
            throw new InvalidArgumentException('Quantity must be between 1 and 1000.');
        }
        $length = (int) ($opts['length'] ?? Settings::get('token.length', '10'));
        if ($length < 8 || $length > 24) {
            throw new InvalidArgumentException('Token length must be between 8 and 24.');
        }
        $mac = isset($opts['restricted_mac']) && $opts['restricted_mac'] !== ''
            ? self::normalizeMac($opts['restricted_mac']) : null;
        if ($mac !== null && $quantity > 1) {
            throw new InvalidArgumentException('A device-restricted token must be generated individually.');
        }

        $durationMinutes = (int) ($opts['duration_minutes'] ?? $package->duration_minutes);
        $dataMb = array_key_exists('data_limit_mb', $opts) ? $opts['data_limit_mb'] : $package->data_limit_mb;
        $dataBytes = $dataMb === null ? null : (int) $dataMb * 1024 * 1024;

        return DB::transaction(function () use ($package, $quantity, $opts, $length, $mac, $durationMinutes, $dataBytes) {
            $batch = TokenBatch::create([
                'name' => $opts['name'] ?? ($package->name.' · '.now()->format('Y-m-d H:i')),
                'internet_package_id' => $package->id,
                'quantity' => $quantity,
                'is_promotional' => (bool) ($opts['is_promotional'] ?? false),
                'created_by' => $opts['created_by'] ?? null,
                'notes' => $opts['notes'] ?? null,
            ]);

            $codes = [];
            while (count($codes) < $quantity) {
                $code = $this->randomCode($length);
                try {
                    // Savepoint so a (very unlikely) hash collision doesn't poison the outer transaction.
                    DB::transaction(fn () => AccessToken::create([
                        'token_batch_id' => $batch->id,
                        'internet_package_id' => $package->id,
                        'code_hash' => self::hash($code),
                        'code_encrypted' => Crypt::encryptString($code),
                        'code_hint' => substr($code, -4),
                        'status' => AccessToken::UNUSED,
                        'is_promotional' => $batch->is_promotional,
                        'duration_minutes' => $durationMinutes,
                        'data_limit_bytes' => $dataBytes,
                        'max_devices' => (int) ($opts['max_devices'] ?? $package->max_devices),
                        'restricted_mac' => $mac,
                        'valid_until' => $opts['valid_until'] ?? null,
                        'created_by' => $opts['created_by'] ?? null,
                    ]));
                    $codes[] = $code;
                } catch (UniqueConstraintViolationException) {
                    continue; // regenerate
                }
            }

            Audit::log('token.batch_generated', $batch, null, [
                'package' => $package->name, 'quantity' => $quantity, 'promotional' => $batch->is_promotional,
                'device_restricted' => $mac !== null,
            ]);

            return ['batch' => $batch, 'codes' => $codes];
        });
    }

    /** Decrypt the stored voucher codes of a batch for printing (only unredeemed/unpurged ones). */
    public function printableCodes(TokenBatch $batch): array
    {
        return $batch->tokens()->whereNotNull('code_encrypted')->orderBy('id')->get()
            ->map(fn (AccessToken $t) => ['token' => $t, 'code' => Crypt::decryptString($t->code_encrypted)])
            ->all();
    }

    /** Drop encrypted copies for tokens that are older than $days (unsold stock hygiene). */
    public function purgeStoredCodes(int $days): int
    {
        return AccessToken::whereNotNull('code_encrypted')
            ->where('created_at', '<', now()->subDays($days))
            ->update(['code_encrypted' => null]);
    }

    public static function normalizeMac(string $mac): string
    {
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $mac));
        if (strlen($hex) !== 12) {
            throw new InvalidArgumentException('Invalid MAC address.');
        }

        return implode(':', str_split($hex, 2));
    }
}
