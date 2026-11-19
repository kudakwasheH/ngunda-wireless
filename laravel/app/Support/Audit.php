<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Central audit trail writer. Never pass secrets in $old/$new: they are redacted by key name.
 */
class Audit
{
    private const REDACT = ['password', 'passphrase', 'token', 'secret', 'code', 'remember_token'];

    public static function log(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject ? class_basename($subject) : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ? self::redact($old) : null,
            'new_values' => $new ? self::redact($new) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    private static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            foreach (self::REDACT as $needle) {
                if (is_string($key) && str_contains(strtolower($key), $needle)) {
                    $values[$key] = '[redacted]';
                    continue 2;
                }
            }
        }

        return $values;
    }
}
