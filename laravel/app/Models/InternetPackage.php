<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternetPackage extends Model
{
    use SoftDeletes;

    protected $guarded = []; // only ever filled from validated FormRequest data

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'decimal:2', 'cost_price' => 'decimal:2'];
    }

    public function bandwidthLimit()
    {
        return $this->hasOne(BandwidthLimit::class)->where('scope', 'package');
    }

    public function tokens()
    {
        return $this->hasMany(AccessToken::class);
    }

    public function getDataLimitBytesAttribute(): ?int
    {
        return $this->data_limit_mb === null ? null : (int) $this->data_limit_mb * 1024 * 1024;
    }
}
