<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessToken extends Model
{
    public const UNUSED = 'unused';
    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';
    public const SUSPENDED = 'suspended';
    public const EXHAUSTED = 'exhausted';
    public const REVOKED = 'revoked';
    public const STATUSES = [self::UNUSED, self::ACTIVE, self::EXPIRED, self::SUSPENDED, self::EXHAUSTED, self::REVOKED];

    protected $guarded = [];
    protected $hidden = ['code_hash', 'code_encrypted'];

    protected function casts(): array
    {
        return [
            'is_promotional' => 'boolean',
            'valid_until' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'data_limit_bytes' => 'integer',
            'data_used_bytes' => 'integer',
        ];
    }

    public function package()
    {
        return $this->belongsTo(InternetPackage::class, 'internet_package_id');
    }

    public function batch()
    {
        return $this->belongsTo(TokenBatch::class, 'token_batch_id');
    }

    public function redemptions()
    {
        return $this->hasMany(TokenRedemption::class);
    }

    public function sessions()
    {
        return $this->hasMany(DeviceSession::class);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::UNUSED, self::ACTIVE], true);
    }
}
