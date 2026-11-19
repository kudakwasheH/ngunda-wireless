<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'last_activity_at' => 'datetime',
            'ends_at' => 'datetime', 'ended_at' => 'datetime',
        ];
    }

    public function token()
    {
        return $this->belongsTo(AccessToken::class, 'access_token_id');
    }

    public function device()
    {
        return $this->belongsTo(NetworkDevice::class, 'network_device_id');
    }

    public function usage()
    {
        return $this->hasMany(UsageRecord::class);
    }
}
