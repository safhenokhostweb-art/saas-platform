<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Profile;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'mobile',
        'email',
        'password',
        'is_admin',
        'is_active',
    ];

    /**
     * Phase 5 / P1-4:
     * cast `password => hashed` تضمین می‌کند هر تخصیصِ پسورد روی مدل به‌صورت خودکار
     * با Bcrypt هش شود. Laravel فقط زمانی Hash::make اعمال می‌کند که
     * `Hash::needsRehash($value)` true باشد؛ بنابراین پسوردهای موجود (که از قبل هش شده‌اند)
     * بدون تغییر باقی می‌مانند و backward-compatibility کاملاً حفظ می‌شود.
     */
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
