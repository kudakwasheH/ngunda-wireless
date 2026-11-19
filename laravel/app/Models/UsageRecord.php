<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(DeviceSession::class, 'device_session_id');
    }
}
