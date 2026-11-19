<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // role_id and is_active are deliberately NOT mass-assignable; controllers set them explicitly.
    protected $fillable = ['name', 'email', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === Role::SUPER_ADMIN;
    }

    /** Super administrators implicitly hold every permission. */
    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active || ! $this->role) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissionNames()->contains($permission);
    }

    private ?\Illuminate\Support\Collection $permissionCache = null;

    public function permissionNames(): \Illuminate\Support\Collection
    {
        return $this->permissionCache ??= $this->role
            ? $this->role->permissions()->pluck('name')
            : collect();
    }

    public function canAccessAdmin(): bool
    {
        return $this->is_active && $this->role && $this->role->is_staff;
    }
}
