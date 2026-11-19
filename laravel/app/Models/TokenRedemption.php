<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenRedemption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['redeemed_at' => 'datetime', 'is_first_use' => 'boolean'];
    }

    public function token()
    {
        return $this->belongsTo(AccessToken::class, 'access_token_id');
    }

    public function device()
    {
        return $this->belongsTo(NetworkDevice::class, 'network_device_id');
    }
}
