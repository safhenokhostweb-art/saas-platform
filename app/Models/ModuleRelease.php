<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک نسخهٔ منتشرشدهٔ ماژول (برای patch-based updates).
 */
class ModuleRelease extends Model
{
    public const TYPE_PATCH = 'patch';
    public const TYPE_MINOR = 'minor';
    public const TYPE_MAJOR = 'major';

    protected $fillable = [
        'module_id',
        'version',
        'release_type',
        'changelog',
        'distributed_at',
        'is_active',
        'package_sha256',
        'package_path',
        'package_key',
        'package_size_bytes',
    ];

    protected $casts = [
        'distributed_at' => 'datetime',
        'is_active' => 'boolean',
        'package_size_bytes' => 'integer',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * آخرین نسخهٔ فعال برای یک ماژول.
     */
    public static function latestForModule(int $moduleId): ?self
    {
        $releases = self::where('module_id', $moduleId)
            ->where('is_active', true)
            ->get();

        return $releases->reduce(function (?self $best, self $r) {
            if ($best === null) {
                return $r;
            }
            return version_compare($r->version, $best->version, '>') ? $r : $best;
        });
    }

    /**
     * fallback: اگر module_releases خالی باشد، از modules.version استفاده می‌شود.
     */
    public static function getLatestVersionForModule(Module $module): string
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('module_releases')) {
            return $module->version ?? Module::DEFAULT_VERSION;
        }
        $release = self::latestForModule($module->id);
        if ($release) {
            return $release->version;
        }
        return $module->version ?? Module::DEFAULT_VERSION;
    }
}
