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

    /**
     * Phase 17-H — Part 2: Release Status Lifecycle.
     * draft → validated → built → published → deprecated.
     * Independent of `is_active` (a rollback-safety flag) — see the
     * status column's migration docblock and
     * docs/architecture/module-package-contract.md.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_BUILT = 'built';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DEPRECATED = 'deprecated';

    protected $fillable = [
        'module_id',
        'version',
        'release_type',
        'changelog',
        'distributed_at',
        'is_active',
        'status',
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

    /**
     * Phase 17-H — Part 7: the deployment/upgrade-eligible counterpart of
     * latestForModule(). That method (kept unchanged, still used by
     * ModuleReleaseProducerAdapter's version-monotonicity check, which
     * must compare against every release regardless of publish status)
     * answers "what's the newest version that was ever released"; this
     * one answers "what's the newest version a customer may actually
     * install or upgrade to" — draft/validated/built/deprecated releases
     * are never eligible, no matter how new their version number is.
     */
    public static function latestPublishedForModule(int $moduleId): ?self
    {
        $releases = self::where('module_id', $moduleId)
            ->where('is_active', true)
            ->where('status', self::STATUS_PUBLISHED)
            ->get();

        return $releases->reduce(function (?self $best, self $r) {
            if ($best === null) {
                return $r;
            }
            return version_compare($r->version, $best->version, '>') ? $r : $best;
        });
    }

    /**
     * Published-only counterpart of getLatestVersionForModule(). Falls
     * back to modules.version like its counterpart when there is no
     * published release yet (e.g. a module still in draft) — this is
     * purely a display fallback, never a deployment decision (see the
     * version-authority ADR: modules.version is cache, never authority).
     */
    public static function getLatestPublishedVersionForModule(Module $module): string
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('module_releases')) {
            return $module->version ?? Module::DEFAULT_VERSION;
        }
        $release = self::latestPublishedForModule($module->id);
        if ($release) {
            return $release->version;
        }
        return $module->version ?? Module::DEFAULT_VERSION;
    }
}
