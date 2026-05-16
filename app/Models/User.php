<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Profile;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'mobile',
        'email',
        'password',
        'is_admin',
        'is_active',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function reseller()
    {
        return $this->hasOne(Reseller::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        return $this->roles()->whereHas('permissions', fn ($q) => $q->where('slug', $slug))->exists();
    }
}
