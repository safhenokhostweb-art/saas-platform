<?php

namespace SaaSPlatform\Modules\MotionGraphics\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SaaSPlatform\Modules\MotionGraphics\Contracts\ClockContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAssetPipelineContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAssetStorageContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAuditLoggerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicDeployment;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicVersion;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGroup;

final class MotionLibraryService
{
    public function __construct(
        private readonly PlatformAdminResolverContract $admins,
        private readonly MotionPermissionGatewayContract $permissions,
        private readonly SiteResolverContract $sites,
        private readonly MotionAssetPipelineContract $pipeline,
        private readonly MotionAssetStorageContract $storage,
        private readonly MotionAuditLoggerContract $audit,
        private readonly MotionDeploymentGatewayContract $deploymentGateway,
        private readonly ClockContract $clock,
    ) {}

    public function createFromPipeline(array $data, array $input, mixed $actor): MotionGraphic
    {
        $prepared = $this->pipeline->prepare($input, $data['slug'], 1);

        return $this->create([...$data, ...$prepared], $actor);
    }

    public function create(array $data, mixed $actor): MotionGraphic
    {
        $actorId = $this->authorize($actor, 'motion.create');
        $this->validateOwnership($data['ownership_type'] ?? '', $data['owner_site_identifier'] ?? null);
        $this->validateClassification($data);
        $this->validatePreparedAssets($data['compiled_assets'] ?? [], $data['slug'], 1);

        return DB::transaction(function () use ($data, $actorId): MotionGraphic {
            $source = $data['source_definition'] ?? $data['source_definition_json'] ?? [];
            $schema = $data['editable_schema'] ?? $data['editable_schema_json'] ?? [];
            $checksum = $data['source_checksum'] ?? $this->checksum($source);
            $motion = MotionGraphic::create([
                ...Arr::only($data, [
                    'name', 'slug', 'description', 'ownership_type', 'owner_site_identifier',
                    'group_id', 'category_id', 'engine', 'asset_type', 'runtime_configuration',
                    'dimensions', 'accessibility',
                ]),
                'source_definition_json' => $source,
                'editable_schema_json' => $schema,
                'ai_metadata_json' => $data['ai_metadata'] ?? $data['ai_metadata_json'] ?? null,
                'latest_working_version' => 1,
                'approved_version' => null,
                'published_version' => null,
                'status' => 'draft',
                'created_by_actor_identifier' => $actorId,
            ]);
            $motion->versions()->create([
                'version' => 1,
                'parent_version' => null,
                'source_snapshot' => $source,
                'editable_schema_snapshot' => $schema,
                'ai_metadata_json' => $data['version_ai_metadata'] ?? null,
                'compiled_assets' => $data['compiled_assets'],
                'preview_metadata' => $data['preview_metadata'] ?? null,
                'source_checksum' => $checksum,
                'status' => 'draft',
                'change_description' => $data['change_description'] ?? 'Initial draft',
                'created_by_actor_identifier' => $actorId,
            ]);
            $motion->tags()->sync(array_values(array_unique($data['tag_ids'] ?? [])));
            $this->audit->record('motion.created', ['motion_uuid' => $motion->uuid, 'actor' => $actorId]);

            return $motion->fresh(['versions', 'group', 'category', 'tags']);
        });
    }

    public function createVersionFromPipeline(
        MotionGraphic $motion,
        array $input,
        string $description,
        mixed $actor,
        array $aiMetadata = [],
    ): MotionGraphicVersion {
        $next = (int) $motion->versions()->max('version') + 1;
        $prepared = $this->pipeline->prepare($input, $motion->slug, $next);

        return $this->createVersion($motion, $prepared, $description, $actor, $aiMetadata);
    }

    public function createVersion(
        MotionGraphic $motion,
        array $prepared,
        string $description,
        mixed $actor,
        array $aiMetadata = [],
    ): MotionGraphicVersion {
        $actorId = $this->authorize($actor, 'motion.update');

        return DB::transaction(function () use ($motion, $prepared, $description, $actorId, $aiMetadata): MotionGraphicVersion {
            $locked = MotionGraphic::query()->lockForUpdate()->findOrFail($motion->id);
            $next = (int) $locked->versions()->max('version') + 1;
            $this->validatePreparedAssets($prepared['compiled_assets'] ?? [], $locked->slug, $next);
            $source = $prepared['source_definition'] ?? [];
            $version = $locked->versions()->create([
                'version' => $next,
                'parent_version' => $locked->latest_working_version,
                'source_snapshot' => $source,
                'editable_schema_snapshot' => $prepared['editable_schema'] ?? $locked->editable_schema_json,
                'ai_metadata_json' => $aiMetadata ?: null,
                'compiled_assets' => $prepared['compiled_assets'],
                'preview_metadata' => $prepared['preview_metadata'] ?? null,
                'source_checksum' => $prepared['source_checksum'] ?? $this->checksum($source),
                'status' => 'draft',
                'change_description' => $description,
                'created_by_actor_identifier' => $actorId,
            ]);
            $locked->update([
                'source_definition_json' => $source,
                'latest_working_version' => $next,
                'status' => $locked->published_version === null ? 'draft' : 'published',
            ]);
            $this->audit->record('motion.version_created', [
                'motion_uuid' => $locked->uuid, 'version' => $next, 'actor' => $actorId,
            ]);

            return $version;
        });
    }

    public function approve(MotionGraphic $motion, int $version, mixed $actor): MotionGraphicVersion
    {
        $actorId = $this->authorize($actor, 'motion.approve');
        $record = $motion->versions()->where('version', $version)->whereIn('status', ['draft', 'review'])->firstOrFail();
        $record->update([
            'status' => 'approved',
            'approved_by_actor_identifier' => $actorId,
            'approved_at' => $this->clock->now(),
        ]);
        $motion->update([
            'approved_version' => $version,
            'status' => $motion->published_version === null ? 'approved' : 'published',
        ]);
        $this->audit->record('motion.approved', ['motion_uuid' => $motion->uuid, 'version' => $version, 'actor' => $actorId]);

        return $record->fresh();
    }

    public function publish(MotionGraphic $motion, int $version, mixed $actor): MotionGraphicVersion
    {
        if (! config('motion-graphics.publishing_enabled', false)) {
            throw ValidationException::withMessages(['publishing' => 'Publishing is disabled.']);
        }
        $actorId = $this->authorize($actor, 'motion.publish');

        return DB::transaction(function () use ($motion, $version, $actorId): MotionGraphicVersion {
            $locked = MotionGraphic::query()->lockForUpdate()->findOrFail($motion->id);
            $target = $locked->versions()->where('version', $version)->firstOrFail();
            if ($target->status !== 'approved' || (int) $locked->approved_version !== $version) {
                throw ValidationException::withMessages(['version' => 'Only the approved version can be published.']);
            }
            $locked->versions()->where('status', 'published')->update(['status' => 'retired']);
            $target->update([
                'status' => 'published',
                'published_by_actor_identifier' => $actorId,
                'published_at' => $this->clock->now(),
            ]);
            $locked->update([
                'published_version' => $version,
                'status' => 'published',
                'published_at' => $this->clock->now(),
            ]);
            MotionGraphicDeployment::query()
                ->where('motion_graphic_id', $locked->id)
                ->where('follow_latest_published', true)
                ->update(['selected_version' => null]);
            $this->deploymentGateway->published($locked->uuid, $version);
            $this->audit->record('motion.published', ['motion_uuid' => $locked->uuid, 'version' => $version, 'actor' => $actorId]);

            return $target->fresh();
        });
    }

    public function rollback(MotionGraphic $motion, int $fromVersion, string $description, mixed $actor): MotionGraphicVersion
    {
        $this->authorize($actor, 'motion.rollback');
        $snapshot = $motion->versions()->where('version', $fromVersion)->firstOrFail();
        $next = (int) $motion->versions()->max('version') + 1;
        $assets = [];
        $oldPrefix = trim((string) config('motion-graphics.asset_path_prefix', 'motion'), '/')
            .'/'.$motion->slug.'/v'.$fromVersion.'/';
        $newPrefix = trim((string) config('motion-graphics.asset_path_prefix', 'motion'), '/')
            .'/'.$motion->slug.'/v'.$next.'/';
        foreach ($snapshot->compiled_assets as $asset) {
            $oldPath = $asset['path'] ?? null;
            if (! is_string($oldPath) || ! str_starts_with($oldPath, $oldPrefix)) {
                throw ValidationException::withMessages(['version' => 'Historical asset path is invalid.']);
            }
            $newPath = $newPrefix.substr($oldPath, strlen($oldPrefix));
            $assets[] = $this->storage->put($newPath, $this->storage->get($oldPath), $asset['mime']);
        }

        return $this->createVersion($motion, [
            'source_definition' => $snapshot->source_snapshot,
            'editable_schema' => $snapshot->editable_schema_snapshot,
            'compiled_assets' => $assets,
            'preview_metadata' => $this->replaceVersionPath($snapshot->preview_metadata ?? [], $fromVersion, $next),
            'source_checksum' => $snapshot->source_checksum,
        ], $description ?: 'Restore from v'.$fromVersion, $actor, ['restored_from_version' => $fromVersion]);
    }

    public function compareVersions(MotionGraphic $motion, int $from, int $to, mixed $actor): array
    {
        $this->authorize($actor, 'motion.view');
        $versions = $motion->versions()->whereIn('version', [$from, $to])->get()->keyBy('version');
        if (! $versions->has($from) || ! $versions->has($to)) {
            throw ValidationException::withMessages(['version' => 'Comparison version was not found.']);
        }

        return [
            'from' => $from,
            'to' => $to,
            'source_changes' => $this->diff($versions[$from]->source_snapshot, $versions[$to]->source_snapshot),
            'assets_changed' => $versions[$from]->compiled_assets !== $versions[$to]->compiled_assets,
        ];
    }

    public function runtimeManifest(MotionGraphicDeployment $deployment): array
    {
        if (! $deployment->is_enabled) {
            throw ValidationException::withMessages(['deployment' => 'Deployment is disabled.']);
        }
        $motion = $deployment->motionGraphic()->firstOrFail();
        if ($motion->ownership_type === MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC
            && $motion->owner_site_identifier !== $deployment->external_site_identifier) {
            throw ValidationException::withMessages(['deployment' => 'Tenant isolation violation.']);
        }
        $versionNumber = $deployment->follow_latest_published
            ? $motion->published_version
            : $deployment->selected_version;
        if ($versionNumber === null) {
            throw ValidationException::withMessages(['deployment' => 'No published version is available.']);
        }
        $version = $motion->versions()->where('version', $versionNumber)->where('status', 'published')->firstOrFail();

        return [
            'slug' => $motion->slug,
            'version' => $version->version,
            'engine' => $motion->engine,
            'assets' => collect($version->compiled_assets)->map(fn (array $asset): array => Arr::only(
                $asset, ['url', 'sha256', 'mime', 'size']
            ))->values()->all(),
            'dimensions' => $motion->dimensions,
            'configuration' => $motion->runtime_configuration,
            'accessibility' => $motion->accessibility,
            'cache' => [
                'etag' => $version->source_checksum,
                'published_at' => $version->published_at?->toIso8601String(),
            ],
        ];
    }

    private function authorize(mixed $actor, string $ability): string
    {
        if (! $this->admins->isPlatformAdmin($actor) || ! $this->permissions->allows($actor, $ability)) {
            throw ValidationException::withMessages(['authorization' => 'Platform Admin authorization is required.']);
        }

        return $this->admins->actorIdentifier($actor);
    }

    private function validateOwnership(string $ownership, ?string $siteId): void
    {
        if ($ownership === MotionGraphic::OWNERSHIP_GLOBAL && $siteId !== null) {
            throw ValidationException::withMessages(['owner_site_identifier' => 'Global motions cannot have a site owner.']);
        }
        if ($ownership === MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC
            && (blank($siteId) || ! $this->sites->exists($siteId))) {
            throw ValidationException::withMessages(['owner_site_identifier' => 'A resolvable site owner is required.']);
        }
        if (! in_array($ownership, [MotionGraphic::OWNERSHIP_GLOBAL, MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC], true)) {
            throw ValidationException::withMessages(['ownership_type' => 'Unsupported ownership type.']);
        }
    }

    private function validateClassification(array $data): void
    {
        $group = MotionGroup::query()->findOrFail($data['group_id'] ?? null);
        if ($group->ownership_type === MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC
            && $group->owner_site_identifier !== ($data['owner_site_identifier'] ?? null)) {
            throw ValidationException::withMessages(['group_id' => 'Customer group belongs to another site.']);
        }
        if (isset($data['category_id'])
            && ! $group->categories()->whereKey($data['category_id'])->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Category does not belong to the selected group.']);
        }
    }

    private function validatePreparedAssets(array $assets, string $slug, int $version): void
    {
        $prefix = '/'.trim((string) config('motion-graphics.asset_path_prefix', 'motion'), '/')
            .'/'.$slug.'/v'.$version.'/';
        if ($assets === []) {
            throw ValidationException::withMessages(['compiled_assets' => 'At least one compiled asset is required.']);
        }
        foreach ($assets as $asset) {
            if (! isset($asset['url'], $asset['sha256'], $asset['mime'], $asset['size'])
                || ! str_starts_with($asset['url'], $prefix)
                || ! preg_match('/^[a-f0-9]{64}$/i', $asset['sha256'])) {
                throw ValidationException::withMessages(['compiled_assets' => 'Compiled asset manifest is invalid.']);
            }
        }
    }

    private function checksum(array $source): string
    {
        return hash('sha256', json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function replaceVersionPath(array $data, int $from, int $to): array
    {
        return json_decode(str_replace('/v'.$from.'/', '/v'.$to.'/', json_encode($data, JSON_THROW_ON_ERROR)), true, 512, JSON_THROW_ON_ERROR);
    }

    private function diff(array $from, array $to, string $prefix = ''): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($from), ...array_keys($to)]) as $key) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $old = $from[$key] ?? null;
            $new = $to[$key] ?? null;
            if (is_array($old) && is_array($new)) {
                $changes = [...$changes, ...$this->diff($old, $new, $path)];
            } elseif ($old !== $new) {
                $changes[] = ['path' => $path, 'from' => $old, 'to' => $new];
            }
        }
        return $changes;
    }
}
