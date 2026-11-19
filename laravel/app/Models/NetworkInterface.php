<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkInterface extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_checked_at' => 'datetime'];
    }

    public function accessPoints()
    {
        return $this->hasMany(AccessPoint::class);
    }
}
