<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'paid_at' => 'datetime', 'provider_payload' => 'array', 'amount' => 'decimal:2'];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
