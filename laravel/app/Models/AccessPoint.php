<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessPoint extends Model
{
    protected $guarded = [];
    protected $hidden = ['passphrase_encrypted'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean', 'client_isolation' => 'boolean', 'is_enabled' => 'boolean'];
    }

    public function interface()
    {
        return $this->belongsTo(NetworkInterface::class, 'network_interface_id');
    }
}
