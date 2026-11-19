<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BandwidthLimit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function package()
    {
        return $this->belongsTo(InternetPackage::class, 'internet_package_id');
    }
}
