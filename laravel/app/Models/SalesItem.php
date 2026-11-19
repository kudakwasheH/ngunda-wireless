<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesItem extends Model
{
    protected $guarded = [];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function package()
    {
        return $this->belongsTo(InternetPackage::class, 'internet_package_id');
    }
}
