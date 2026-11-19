<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_promotional' => 'boolean', 'printed_at' => 'datetime'];
    }

    public function package()
    {
        return $this->belongsTo(InternetPackage::class, 'internet_package_id');
    }

    public function tokens()
    {
        return $this->hasMany(AccessToken::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
