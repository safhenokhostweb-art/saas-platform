<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TwoFactor extends Model
{
    // SAAS ZERO-FAILURE PUNCH LIST: 2026_05_16_194813_create_two_factor_table.php
    // creates the table as `two_factor` (singular) — Eloquent's default
    // convention for this class name would look for `two_factors`
    // (plural). Without this override, User::twoFactor()/
    // UserService::enableTwoFactor() threw a real QueryException ("table
    // ... doesn't exist") on every call — in production too, not just
    // tests. Silently misreported as HTTP 409 "already enabled" by
    // UserApiController::enableTwoFactor()'s `catch (\RuntimeException $e)`,
    // since Laravel's QueryException extends PDOException extends
    // RuntimeException. Fixed here (matching the existing migration) rather
    // than renaming the migration's table, to avoid any risk to whatever
    // may already exist under this table name.
    protected $table = 'two_factor';

    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'secret',
        'recovery_codes',
        'confirmed_at',
        'disabled_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
