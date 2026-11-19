<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkDevice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['mac_is_randomized' => 'boolean', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    /** A MAC with the locally-administered bit set is almost certainly randomized/private. */
    public static function isRandomizedMac(string $mac): bool
    {
        $first = hexdec(substr($mac, 0, 2));
        return ($first & 0x02) === 0x02;
    }

    public function sessions()
    {
        return $this->hasMany(DeviceSession::class);
    }

    public function blocks()
    {
        return $this->hasMany(BlockedDevice::class);
    }
}
