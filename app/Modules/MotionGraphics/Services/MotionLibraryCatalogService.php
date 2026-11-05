<?php

namespace App\Modules\MotionGraphics\Services;

use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionCategory;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicVersion;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGroup;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionTag;
use SaaSPlatform\Modules\MotionGraphics\Support\MotionSvgComposer;

/**
 * ADR-078: publishes the platform's curated motion library assets into the
 * Motion Graphics catalog (`motion_graphics` + `motion_graphic_versions`).
 *
 * The module's library directory
 * (`assets/library/{slug}/{vN}/animation.svg` + `metadata.json`) is the only
 * copy. MotionLibraryAssetController serves it at
 * `/motion/{slug}/{vN}/animation.svg` (the module's `asset_path_prefix`),
 * referenced root-relative so customer-site's manifest reader accepts it and
 * prefixes the platform origin.
 *
 * Idempotent and lifecycle-safe:
 * - version rows are immutable, so a changed SVG never edits a version; it
 *   becomes a new version (N+1), published, with the previous published
 *   version retired — the same transition MotionLibraryService::publish()
 *   performs;
 * - re-running with unchanged files writes nothing to the version table.
 */
final class MotionLibraryCatalogService
{
    public const TEMPLATE_REFERENCE = 'motion-library:core';
    public const ACTOR = 'system:motion-library';

    /**
     * Library assets this catalog publishes, with their library version
     * directory. Groups/categories come from each asset's metadata.json.
     *
     * @var array<string, string>
     */
    public const ASSETS = [
        'hero-motion' => 'v2',
        'business-growth-motion' => 'v2',
    ];

    public const FONT_FILE = 'packages/saas-platform/modules/motion-graphics/assets/fonts/vazirmatn-motion.woff2';

    /** Records whose published versions never pointed at a real file. */
    public const LEGACY_SLUGS = ['hero-vector-showcase'];

    private const GROUPS = [
        'hero' => ['name' => 'هیرو', 'category' => 'موشن هیرو', 'sort' => 1],
        'business' => ['name' => 'کسب‌وکار', 'category' => 'رشد و تحلیل کسب‌وکار', 'sort' => 2],
    ];

    /**
     * @return array<string, array{uuid: string, version: int, created: bool}>
     */
    public function sync(): array
    {
        $result = [];
        foreach (self::ASSETS as $slug => $libraryVersion) {
            $result[$slug] = $this->publishAsset($slug, $libraryVersion);
        }
        $this->archiveLegacy();

        return $result;
    }

    /**
     * @return list<array{asset_id: string}> references for a template package
     */
    public function packageReferences(): array
    {
        return MotionGraphic::query()
            ->whereIn('slug', array_keys(self::ASSETS))
            ->whereNotNull('published_version')
            ->orderBy('id')
            ->pluck('uuid')
            ->map(fn (string $uuid): array => ['asset_id' => $uuid])
            ->all();
    }

    public static function libraryDirectory(string $slug, string $libraryVersion): string
    {
        return base_path('packages/saas-platform/modules/motion-graphics/assets/library/'.$slug.'/'.$libraryVersion);
    }

    /**
     * The path MotionLibraryAssetController serves this file at, without the
     * leading slash: `motion/{slug}/{vN}/animation.svg`.
     */
    public static function assetPath(string $slug, string $libraryVersion): string
    {
        $prefix = trim((string) config('motion-graphics.asset_path_prefix', 'motion'), '/');

        return $prefix.'/'.$slug.'/'.$libraryVersion.'/animation.svg';
    }

    /**
     * ADR-079: the bytes served for one library file and one set of raw
     * customer parameters. Unknown/invalid parameters are dropped by
     * MotionSvgComposer, so the result depends only on valid input. v1 files
     * declare no parameters and get no font (they predate both), so they are
     * served byte-for-byte as published.
     *
     * Returns null when the slug/version has no library file.
     *
     * @param array<string, mixed> $input
     */
    public static function render(string $slug, string $libraryVersion, array $input): ?string
    {
        $directory = self::libraryDirectory($slug, $libraryVersion);
        $svg = is_file($directory.'/animation.svg') ? file_get_contents($directory.'/animation.svg') : false;
        if (! is_string($svg) || $svg === '') {
            return null;
        }
        $metadata = json_decode((string) @file_get_contents($directory.'/metadata.json'), true);
        if (! is_array($metadata) || ! isset($metadata['parameters'])) {
            return $svg;
        }

        $definitions = MotionSvgComposer::normalizeDefinitions((array) $metadata['parameters']);
        $values = MotionSvgComposer::resolveValues($definitions, $input);

        return (new MotionSvgComposer())->compose($svg, $definitions, $values, self::font());
    }

    private static function font(): ?string
    {
        static $font = null;
        if ($font === null) {
            $path = base_path(self::FONT_FILE);
            $font = is_file($path) ? (string) file_get_contents($path) : '';
        }

        return $font === '' ? null : $font;
    }

    /**
     * @return array{uuid: string, version: int, created: bool}
     */
    private function publishAsset(string $slug, string $libraryVersion): array
    {
        $directory = self::libraryDirectory($slug, $libraryVersion);
        $metadata = json_decode((string) @file_get_contents($directory.'/metadata.json'), true);
        $svg = @file_get_contents($directory.'/animation.svg');
        if (! is_array($metadata) || ($metadata['slug'] ?? null) !== $slug || ! is_string($svg) || $svg === '') {
            throw new RuntimeException("Motion library asset [{$slug}/{$libraryVersion}] is missing or malformed.");
        }

        $relative = self::assetPath($slug, $libraryVersion);
        // ADR-079: the compiled asset is what the route actually serves for
        // the default parameters (font embedded), so size/sha256 describe the
        // delivered bytes; the source checksum stays the library file's.
        $served = self::render($slug, $libraryVersion, []) ?? throw new RuntimeException("Motion library asset [{$slug}/{$libraryVersion}] cannot be rendered.");
        $sha256 = hash('sha256', $served);
        $parameters = MotionSvgComposer::normalizeDefinitions((array) ($metadata['parameters'] ?? []));
        $descriptor = [
            'type' => 'svg',
            'path' => $relative,
            'url' => '/'.$relative,
            'mime' => 'image/svg+xml',
            'mime_type' => 'image/svg+xml',
            'size' => strlen($served),
            'sha256' => $sha256,
        ];
        $source = [
            'library_id' => $metadata['library_id'] ?? null,
            'library_version' => $libraryVersion,
            'file' => 'animation.svg',
            'sha256' => hash('sha256', $svg),
        ];

        return DB::transaction(function () use ($slug, $metadata, $descriptor, $source, $sha256, $parameters): array {
            [$group, $category] = $this->classification((string) ($metadata['category'] ?? 'hero'));

            $motion = MotionGraphic::withTrashed()->where('slug', $slug)->lockForUpdate()->first();
            $created = $motion === null;
            $attributes = [
                'name' => (string) $metadata['name'],
                'description' => (string) ($metadata['description'] ?? ''),
                'ownership_type' => MotionGraphic::OWNERSHIP_GLOBAL,
                'owner_site_identifier' => null,
                'group_id' => $group->id,
                'category_id' => $category->id,
                'engine' => (string) ($metadata['engine'] ?? 'svg-js'),
                'asset_type' => 'svg',
                'runtime_configuration' => [
                    'license_tier' => ($metadata['license_tier'] ?? null) === MotionGraphic::LICENSE_TIER_FREE
                        ? MotionGraphic::LICENSE_TIER_FREE : MotionGraphic::LICENSE_TIER_PREMIUM,
                    'supports_theme_tokens' => (bool) ($metadata['theme_support'] ?? false),
                    'theme_tokens' => [],
                    'color_schemes' => ['light', 'dark'],
                    'parameters' => $parameters,
                ],
                'dimensions' => $metadata['dimensions'] ?? ['width' => 960, 'height' => 540, 'viewBox' => '0 0 960 540'],
                'accessibility' => $metadata['accessibility'] ?? ['reduced_motion' => true, 'rtl' => true],
            ];

            if ($created) {
                $motion = MotionGraphic::query()->create([
                    ...$attributes,
                    'slug' => $slug,
                    'source_definition_json' => $source,
                    'editable_schema_json' => ['fields' => [], 'editable_properties' => $metadata['editable_properties'] ?? []],
                    'status' => 'draft',
                    'latest_working_version' => 0,
                    'created_by_actor_identifier' => self::ACTOR,
                ]);
            } else {
                if ($motion->trashed()) {
                    $motion->restore();
                }
                $motion->fill($attributes)->save();
            }

            $tagIds = collect($metadata['tags'] ?? [])->take(12)->map(
                fn (string $tag): int => MotionTag::query()->firstOrCreate(['slug' => $tag], ['name' => $tag])->id
            )->all();
            $motion->tags()->sync($tagIds);

            $existing = $motion->versions()->get()->first(
                fn (MotionGraphicVersion $v): bool => ($v->compiled_assets[0]['sha256'] ?? null) === $sha256
            );

            if ($existing !== null && $existing->status === 'published' && (int) $motion->published_version === (int) $existing->version) {
                $this->retireUnboundVersions($motion, (int) $existing->version);

                return ['uuid' => $motion->uuid, 'version' => (int) $existing->version, 'created' => false];
            }

            $now = now();
            $version = $existing ?? MotionGraphicVersion::query()->create([
                'motion_graphic_id' => $motion->id,
                'version' => 1 + (int) $motion->versions()->max('version'),
                'parent_version' => $motion->published_version,
                'source_snapshot' => $source,
                'editable_schema_snapshot' => ['editable_properties' => $metadata['editable_properties'] ?? []],
                'ai_metadata_json' => null,
                'compiled_assets' => [$descriptor],
                'preview_metadata' => ['poster' => 'preview.svg', 'library_version' => $source['library_version']],
                'source_checksum' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
                'status' => 'draft',
                'change_description' => "Motion library {$source['library_id']} ({$source['library_version']}) published from the platform library.",
                'created_by_actor_identifier' => self::ACTOR,
            ]);

            $version->update([
                'status' => 'published',
                'approved_by_actor_identifier' => self::ACTOR,
                'approved_at' => $now,
                'published_by_actor_identifier' => self::ACTOR,
                'published_at' => $now,
            ]);
            $motion->update([
                'latest_working_version' => max((int) $motion->latest_working_version, (int) $version->version),
                'approved_version' => $version->version,
                'published_version' => $version->version,
                'status' => 'published',
                'published_at' => $now,
                'archived_at' => null,
            ]);

            $this->retireUnboundVersions($motion, (int) $version->version);

            return ['uuid' => $motion->uuid, 'version' => (int) $version->version, 'created' => $existing === null];
        });
    }

    /**
     * ADR-079: an older published version is retired only once no site
     * package item is bound to it. docs/motion-persistence/VERSION_BINDING_RULES.md:
     * a master update must never break an installed site, and the asset
     * reader cannot read a retired version, so retiring a bound version
     * would make that site's manifest fail (`asset_not_available`). A bound
     * version stays published until the site is explicitly upgraded
     * (re-activation binds the new version); the next sync then retires it.
     */
    private function retireUnboundVersions(MotionGraphic $motion, int $current): void
    {
        $bound = SiteAssetPackageItem::query()
            ->where('asset_id', $motion->uuid)
            ->where('is_active', true)
            ->pluck('active_version')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $motion->versions()
            ->where('status', 'published')
            ->where('version', '!=', $current)
            ->whereNotIn('version', $bound)
            ->update(['status' => 'retired']);
    }

    /**
     * @return array{0: MotionGroup, 1: MotionCategory}
     */
    private function classification(string $key): array
    {
        $definition = self::GROUPS[$key] ?? self::GROUPS['hero'];
        $groupSlug = array_key_exists($key, self::GROUPS) ? $key : 'hero';

        $group = MotionGroup::query()->firstOrCreate(
            ['slug' => $groupSlug],
            ['name' => $definition['name'], 'ownership_type' => 'global', 'owner_site_identifier' => null,
             'search_metadata' => [], 'sort_order' => $definition['sort'], 'is_active' => true]
        );
        $category = MotionCategory::query()->firstOrCreate(
            ['motion_group_id' => $group->id, 'slug' => $groupSlug.'-motion'],
            ['name' => $definition['category'], 'sort_order' => 1, 'is_active' => true]
        );

        return [$group, $category];
    }

    /**
     * The pre-ADR-078 `hero-vector-showcase` record published a version whose
     * compiled asset was never a real file. It leaves discovery and the
     * runtime (no published pointer) but keeps its full history.
     */
    private function archiveLegacy(): void
    {
        foreach (MotionGraphic::query()->whereIn('slug', self::LEGACY_SLUGS)->get() as $legacy) {
            if ($legacy->status === 'archived' && $legacy->published_version === null) {
                continue;
            }
            DB::transaction(function () use ($legacy): void {
                $legacy->versions()->where('status', 'published')->update(['status' => 'retired']);
                $legacy->update(['status' => 'archived', 'archived_at' => now(), 'published_version' => null]);
            });
        }
    }
}
