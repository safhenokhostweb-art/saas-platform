<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Profile;
use SaaSPlatform\CoreUtils\Contracts\Platform\UserModelInterface;

class User extends Authenticatable implements UserModelInterface
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public function getId(): int         { return (int) $this->id; }
    public function getKey(): mixed      { return $this->id; }
    public function getName(): ?string   { return $this->profile?->full_name ?? $this->mobile; }
    public function getMobile(): ?string { return $this->mobile; }
    public function isAdmin(): bool      { return (bool) $this->is_admin; }

    public function getNameAttribute(): ?string
    {
        return $this->profile?->full_name ?? $this->mobile;
    }

    protected $fillable = [
        'mobile',
        'password',
        'is_admin',
        'is_active',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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

    // --- 2FA ---
    public function twoFactor()
    {
        return $this->morphOne(TwoFactor::class, 'authenticatable');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_confirmed_at !== null;
    }

    // --- Auto-lock ---
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    // --- Groups ---
    public function groups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_user')
            ->withTimestamps();
    }

    // --- Tags (polymorphic) ---
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    // --- Login Attempts ---
    public function loginAttempts()
    {
        return $this->hasMany(LoginAttempt::class);
    }

    // --- Password Resets ---
    public function passwordResets()
    {
        return $this->hasMany(PasswordReset::class, 'mobile', 'mobile');
    }

    // --- RBAC helpers ---
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
