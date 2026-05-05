<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';
    public const STAFF = 'staff';
    public const RESELLER = 'reseller';

    protected $fillable = ['name', 'slug', 'guard_name', 'description'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SUPER_ADMIN;
    }

    public function isReseller(): bool
    {
        return $this->slug === self::RESELLER;
    }
}
