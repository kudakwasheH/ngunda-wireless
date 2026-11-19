<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';
    public const NETWORK_ADMIN = 'network_admin';
    public const SALES_OFFICER = 'sales_officer';
    public const CUSTOMER = 'customer';

    protected $fillable = ['name', 'slug', 'description', 'is_staff'];

    protected function casts(): array
    {
        return ['is_staff' => 'boolean'];
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
