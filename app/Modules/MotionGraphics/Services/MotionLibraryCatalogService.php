<?php

namespace App\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignment;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Support\MotionPrivateAssetUrl;
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
        // ADR-086: the reference PRIVATE library motion. Its metadata.json
        // declares `"visibility": "private"`, so it is published with a
        // private (signed) reference and reaches only sites granted it in
        // the central panel (motion_site_asset_grants).
        'solar-motion-graphic' => 'v1',
    ];

    /**
     * ADR-081: exclusive library assets, slug => library version directory.
     * Each one's metadata.json must declare
     * `"ownership": {"type": "customer_specific", "site_id": N}`;
     * it is published as customer_specific for that one site and served
     * only through site-scoped signed URLs (MotionPrivateAssetController),
     * never by the public `/motion/{slug}/{vN}/animation.svg` route.
     *
     * Runtime config `motion-library.exclusive_assets` adds entries (and
     * `motion-library.exclusive_library_path` relocates their files); tests
     * use both. There is no config file, so production uses this list only.
     *
     * @var array<string, string>
     */
    public const EXCLUSIVE_ASSETS = [];

    public const FONT_FILE = 'packages/saas-platform/modules/motion-graphics/assets/fonts/vazirmatn-motion.woff2';

    /** Records whose published versions never pointed at a real file. */
    public const LEGACY_SLUGS = ['hero-vector-showcase'];

    private const GROUPS = [
        'hero' => ['name' => 'هیرو', 'category' => 'موشن هیرو', 'sort' => 1],
        'business' => ['name' => 'کسب‌وکار', 'category' => 'رشد و تحلیل کسب‌وکار', 'sort' => 2],
        'solar' => ['name' => 'صنعت برق و انرژی خورشیدی', 'category' => 'موشن صنعت برق و انرژی', 'sort' => 3],
    ];

    /**
     * @return array<string, array{uuid: string, version: int, created: bool}>
     */
    public function sync(): array
    {
        $result = [];
        foreach (self::catalogAssets() as $slug => $libraryVersion) {
            $result[$slug] = $this->publishAsset($slug, $libraryVersion);
        }
        $this->archiveLegacy();

        return $result;
    }

    /**
     * ADR-081: exclusive assets are package references too. Activation
     * binds an optional asset only for a site the projection lets read it,
     * so every other site skips an exclusive asset (audited as
     * `asset_not_available_for_site`) and only its owner installs it.
     *
     * @return list<array{asset_id: string}> references for a template package
     */
    public function packageReferences(): array
    {
        return MotionGraphic::query()
            ->whereIn('slug', array_keys(self::catalogAssets()))
            ->whereNotNull('published_version')
            ->orderBy('id')
            ->pluck('uuid')
            ->map(fn (string $uuid): array => ['asset_id' => $uuid])
            ->all();
    }

    /** @return array<string, string> */
    public static function exclusiveAssets(): array
    {
        return [...self::EXCLUSIVE_ASSETS, ...(array) config('motion-library.exclusive_assets', [])];
    }

    /**
     * Every asset the catalog publishes, public ones first so they keep
     * their publish (and package) order. A slug cannot be both public and
     * exclusive: the public entry wins, and sync() then refuses it if its
     * metadata declares an owner.
     *
     * @return array<string, string>
     */
    public static function catalogAssets(): array
    {
        return self::ASSETS + self::exclusiveAssets();
    }

    public static function isExclusive(string $slug): bool
    {
        return array_key_exists($slug, self::exclusiveAssets()) && ! array_key_exists($slug, self::ASSETS);
    }

    public static function libraryDirectory(string $slug, string $libraryVersion): string
    {
        $exclusiveRoot = config('motion-library.exclusive_library_path');
        if (is_string($exclusiveRoot) && $exclusiveRoot !== '' && self::isExclusive($slug)) {
            return rtrim($exclusiveRoot, '/\\').'/'.$slug.'/'.$libraryVersion;
        }

        return base_path('packages/saas-platform/modules/motion-graphics/assets/library/'.$slug.'/'.$libraryVersion);
    }

    /**
     * ADR-081: the owning site a library version's metadata declares, or
     * null for a public asset. Extends the metadata's existing `ownership`
     * block (`{"type": "platform", "license": ..., "customer_restriction": false}`):
     *
     *   "ownership": {"type": "customer_specific", "site_id": 7, "customer_restriction": true}
     *
     * Only `type: customer_specific` makes an asset exclusive. A declaration
     * that asks for a restriction but does not name exactly one valid site is
     * an error, never silently "public".
     *
     * @param array<string, mixed> $metadata
     */
    public static function declaredOwnerSiteId(array $metadata): ?int
    {
        $ownership = $metadata['ownership'] ?? null;
        if (! is_array($ownership)) {
            return null;
        }
        $exclusive = ($ownership['type'] ?? null) === MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC;
        if (! $exclusive) {
            if (($ownership['customer_restriction'] ?? false) === true || array_key_exists('site_id', $ownership)) {
                throw new RuntimeException('Motion library ownership restricts customers but its type is not "customer_specific".');
            }

            return null;
        }
        $siteId = $ownership['site_id'] ?? null;
        if (! is_int($siteId) || $siteId < 1) {
            throw new RuntimeException('Exclusive motion ownership must name one site: {"type": "customer_specific", "site_id": <positive integer>}.');
        }

        return $siteId;
    }

    /**
     * ADR-086: the visibility a library version's metadata declares.
     * `visibility` absent = public (every asset published before ADR-086);
     * anything other than "public"/"private" is an error, never silently
     * public.
     *
     * @param array<string, mixed> $metadata
     */
    public static function declaredVisibility(array $metadata): string
    {
        $visibility = $metadata['visibility'] ?? MotionGraphic::VISIBILITY_PUBLIC;
        if (! in_array($visibility, [MotionGraphic::VISIBILITY_PUBLIC, MotionGraphic::VISIBILITY_PRIVATE], true)) {
            throw new RuntimeException('Motion library visibility must be "public" or "private".');
        }

        return $visibility;
    }

    /**
     * ADR-086: the visibility a library version's files declare, read
     * straight from its metadata.json (public when there is none). A
     * malformed declaration reads as private, so a file route never serves
     * it publicly by mistake.
     */
    public static function fileVisibility(string $slug, string $libraryVersion): string
    {
        $metadata = json_decode((string) @file_get_contents(self::libraryDirectory($slug, $libraryVersion).'/metadata.json'), true);
        if (! is_array($metadata)) {
            return MotionGraphic::VISIBILITY_PUBLIC;
        }
        try {
            return self::declaredVisibility($metadata);
        } catch (RuntimeException) {
            return MotionGraphic::VISIBILITY_PRIVATE;
        }
    }

    /**
     * ADR-086: true for a catalog (non-exclusive) library version whose
     * files are private: served only through MotionPrivateAssetController
     * to a granted site.
     */
    public static function isPrivateLibraryFile(string $slug, string $libraryVersion): bool
    {
        return array_key_exists($slug, self::ASSETS)
            && self::fileVisibility($slug, $libraryVersion) === MotionGraphic::VISIBILITY_PRIVATE;
    }

    /**
     * ADR-086: the release label a new version row records: the metadata's
     * `version_label` (`1.2.0`), else its `version` (`v2`), else the
     * library directory. Never invented, never empty.
     *
     * @param array<string, mixed> $metadata
     */
    public static function versionLabelFor(array $metadata, string $libraryVersion): string
    {
        foreach ([$metadata['version_label'] ?? null, $metadata['version'] ?? null] as $candidate) {
            if (MotionGraphicVersion::isValidVersionLabel($candidate)) {
                return (string) $candidate;
            }
        }

        return $libraryVersion;
    }

    /**
     * The owner a library version's files declare, read straight from its
     * metadata.json: null when it declares none (or has no metadata), 0 when
     * the declaration is malformed. The public file route refuses anything
     * non-null, so an owned file is never served publicly even if it were
     * ever listed there.
     */
    public static function fileOwnerSiteId(string $slug, string $libraryVersion): ?int
    {
        $metadata = json_decode((string) @file_get_contents(self::libraryDirectory($slug, $libraryVersion).'/metadata.json'), true);
        if (! is_array($metadata)) {
            return null;
        }
        try {
            return self::declaredOwnerSiteId($metadata);
        } catch (RuntimeException) {
            return 0;
        }
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

        // ADR-081: ownership comes from metadata.json and must agree with the
        // list the slug is in, so a file meant for one site can never be
        // published as public (or the reverse) by a listing mistake.
        $ownerSiteId = self::declaredOwnerSiteId($metadata);
        // ADR-086: visibility governs GLOBAL motions. An exclusive motion is
        // already restricted to its owner by ownership, so it must not also
        // ask for grants (its owner would then need a grant to see it).
        $visibility = self::declaredVisibility($metadata);
        if ($ownerSiteId !== null && array_key_exists('visibility', $metadata)) {
            throw new RuntimeException("Exclusive motion [{$slug}] is already private to its owner; remove `visibility` from its metadata.json.");
        }
        if (MotionPrivateAssetUrl::isReservedSlug($slug)) {
            throw new RuntimeException("Motion library slug [{$slug}] is reserved.");
        }
        if (self::isExclusive($slug) !== ($ownerSiteId !== null)) {
            throw new RuntimeException(self::isExclusive($slug)
                ? "Exclusive motion [{$slug}] must declare ownership.site_id in its metadata.json."
                : "Public motion [{$slug}] declares an owner; list it as an exclusive asset instead.");
        }
        if ($ownerSiteId !== null && ! Site::query()->whereKey($ownerSiteId)->exists()) {
            throw new RuntimeException("Exclusive motion [{$slug}] names site [{$ownerSiteId}], which does not exist.");
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
            // An exclusive or private (ADR-086) version stores the unsigned
            // private base, which no route serves; the manifest signs it per
            // site.
            'url' => $ownerSiteId === null && $visibility === MotionGraphic::VISIBILITY_PUBLIC
                ? '/'.$relative
                : MotionPrivateAssetUrl::baseReference($slug, $libraryVersion),
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

        $versionLabel = self::versionLabelFor($metadata, $libraryVersion);

        return DB::transaction(function () use ($slug, $metadata, $descriptor, $source, $sha256, $parameters, $ownerSiteId, $visibility, $versionLabel): array {
            [$group, $category] = $this->classification((string) ($metadata['category'] ?? 'hero'));

            $motion = MotionGraphic::withTrashed()->where('slug', $slug)->lockForUpdate()->first();
            $created = $motion === null;
            $ownership = $ownerSiteId === null
                ? ['ownership_type' => MotionGraphic::OWNERSHIP_GLOBAL, 'owner_site_identifier' => null]
                : ['ownership_type' => MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC, 'owner_site_identifier' => (string) $ownerSiteId];

            // ADR-081: ownership is fixed at first publish. Moving a motion
            // to another owner, or between public and exclusive, would strand
            // the sites already bound to it (their manifest fails closed on an
            // asset they can no longer read); publish a new slug instead.
            if (! $created && ($motion->ownership_type !== $ownership['ownership_type']
                || $motion->owner_site_identifier !== $ownership['owner_site_identifier'])) {
                throw new RuntimeException("Motion [{$slug}] ownership cannot change after it is published; use a new slug.");
            }

            $attributes = [
                'name' => (string) $metadata['name'],
                'description' => (string) ($metadata['description'] ?? ''),
                ...$ownership,
                // ADR-086: metadata.json is authoritative, like license_tier.
                // The admin switch (`runtime_enabled`) is deliberately not
                // here, so a sync never re-enables a motion an admin turned off.
                'visibility' => $visibility,
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
                    && ($v->compiled_assets[0]['url'] ?? null) === $descriptor['url']
            );

            if ($existing !== null && $existing->status === 'published' && (int) $motion->published_version === (int) $existing->version) {
                $this->retireUnboundVersions($motion, (int) $existing->version);

                return ['uuid' => $motion->uuid, 'version' => (int) $existing->version, 'created' => false];
            }

            $now = now();
            $version = $existing ?? MotionGraphicVersion::query()->create([
                'motion_graphic_id' => $motion->id,
                'version' => 1 + (int) $motion->versions()->max('version'),
                'version_label' => $versionLabel,
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
     *
     * ADR-081: an active bespoke assignment binds its version the same way.
     */
    private function retireUnboundVersions(MotionGraphic $motion, int $current): void
    {
        $bound = SiteAssetPackageItem::query()
            ->where('asset_id', $motion->uuid)
            ->where('is_active', true)
            ->pluck('active_version')
            ->merge(MotionBespokeAssignment::query()
                ->where('asset_id', $motion->uuid)
                ->where('status', MotionBespokeAssignment::STATUS_ACTIVE)
                ->pluck('active_version'))
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
