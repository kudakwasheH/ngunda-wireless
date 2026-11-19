<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedDevice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['blocked_at' => 'datetime', 'unblocked_at' => 'datetime'];
    }

    public function device()
    {
        return $this->belongsTo(NetworkDevice::class, 'network_device_id');
    }
}
