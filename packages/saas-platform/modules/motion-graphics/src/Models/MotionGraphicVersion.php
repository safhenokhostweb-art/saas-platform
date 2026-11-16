<?php

namespace SaaSPlatform\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class MotionGraphicVersion extends Model
{
    protected $fillable = [
        'motion_graphic_id', 'version', 'version_label', 'parent_version', 'source_snapshot',
        'editable_schema_snapshot', 'ai_metadata_json', 'compiled_assets',
        'preview_metadata', 'source_checksum', 'status', 'change_description',
        'created_by_actor_identifier', 'approved_by_actor_identifier', 'approved_at',
        'published_by_actor_identifier', 'published_at',
    ];

    protected $hidden = ['source_snapshot', 'editable_schema_snapshot', 'ai_metadata_json'];

    protected $casts = [
        'source_snapshot' => 'encrypted:array',
        'editable_schema_snapshot' => 'encrypted:array',
        'ai_metadata_json' => 'encrypted:array',
        'compiled_assets' => 'array',
        'preview_metadata' => 'array',
        'approved_at' => 'immutable_datetime',
        'published_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $immutable = [
                'motion_graphic_id', 'version', 'version_label', 'parent_version', 'source_snapshot',
                'editable_schema_snapshot', 'ai_metadata_json', 'compiled_assets',
                'preview_metadata', 'source_checksum', 'change_description',
                'created_by_actor_identifier',
            ];
            if ($version->isDirty($immutable)) {
                throw new LogicException('Historical motion version content is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Motion version history cannot be deleted.'));
    }

    /**
     * ADR-086: accepted shape of a human version label (`v2`, `1.2`,
     * `1.2.0`, `1.2.0-rc.1`, `2.1.0+build.7`).
     */
    public const VERSION_LABEL_PATTERN = '/^v?[0-9]{1,4}(\.[0-9]{1,4}){0,2}([-+][0-9A-Za-z.]{1,16})?$/';

    /**
     * ADR-086: the release identifier of this version. The stored label wins;
     * a row published before labels existed falls back to its library
     * directory (`v2`), then to the integer version (`v3`). Never empty.
     */
    public function versionLabel(): string
    {
        foreach ([$this->version_label ?? null, ($this->preview_metadata ?? [])['library_version'] ?? null] as $candidate) {
            if (self::isValidVersionLabel($candidate)) {
                return (string) $candidate;
            }
        }

        return 'v'.(int) $this->version;
    }

    public static function isValidVersionLabel(mixed $label): bool
    {
        return is_string($label) && strlen($label) <= 32 && preg_match(self::VERSION_LABEL_PATTERN, $label) === 1;
    }

    /**
     * ADR-086: a short build identifier of the delivered bytes (first 12 hex
     * characters of the compiled asset's sha256), or null when the version
     * has no compiled digest.
     */
    public function buildId(): ?string
    {
        $sha = (($this->compiled_assets ?? [])[0] ?? [])['sha256'] ?? null;

        return is_string($sha) && preg_match('/^[a-f0-9]{64}$/', $sha) === 1 ? substr($sha, 0, 12) : null;
    }

    public function motionGraphic(): BelongsTo
    {
        return $this->belongsTo(MotionGraphic::class);
    }
}
