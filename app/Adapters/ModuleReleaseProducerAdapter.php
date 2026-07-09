<?php

namespace App\Adapters;

use App\Models\Module;
use App\Models\ModuleRelease;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseRequest;
use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseResult;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleReleaseProducerInterface;

/**
 * Implements ModuleReleaseProducerInterface using the platform's concrete
 * Module and ModuleRelease models. Packages call this via the interface —
 * they never write to module_releases directly (see
 * docs/architecture/module-version-authority-adr.md).
 */
class ModuleReleaseProducerAdapter implements ModuleReleaseProducerInterface
{
    public function record(ModuleReleaseRequest $request): ModuleReleaseResult
    {
        $module = Module::where('slug', $request->slug)->first();

        if (!$module) {
            Log::warning('module_release.module_not_found', ['slug' => $request->slug]);

            return new ModuleReleaseResult(
                status: ModuleReleaseResult::STATUS_SKIPPED_NO_MODULE_ROW,
                reason: "ماژول {$request->slug} در سیستم ثبت نشده است."
            );
        }

        $duplicateExists = ModuleRelease::where('module_id', $module->id)
            ->where('version', $request->version)
            ->where('is_active', true)
            ->exists();

        if ($duplicateExists) {
            Log::warning('module_release.duplicate_version', [
                'slug' => $request->slug,
                'version' => $request->version,
            ]);

            return new ModuleReleaseResult(
                status: ModuleReleaseResult::STATUS_SKIPPED_DUPLICATE,
                reason: "نسخهٔ {$request->version} برای ماژول {$request->slug} قبلاً منتشر شده است."
            );
        }

        $latestActive = ModuleRelease::latestForModule($module->id);

        if ($latestActive && version_compare($request->version, $latestActive->version, '<=')) {
            Log::warning('module_release.version_not_greater', [
                'slug' => $request->slug,
                'requested_version' => $request->version,
                'latest_version' => $latestActive->version,
            ]);

            return new ModuleReleaseResult(
                status: ModuleReleaseResult::STATUS_FAILED,
                reason: "نسخهٔ {$request->version} باید از آخرین نسخهٔ منتشرشده ({$latestActive->version}) بزرگ‌تر باشد."
            );
        }

        try {
            $releaseId = DB::transaction(function () use ($module, $request) {
                $release = ModuleRelease::create([
                    'module_id' => $module->id,
                    'version' => $request->version,
                    'release_type' => $request->releaseType,
                    'changelog' => $request->changelog,
                    'distributed_at' => $request->distributedAt,
                    'is_active' => $request->isActive,
                ]);

                $module->update(['version' => $request->version]);

                return $release->id;
            });
        } catch (\Throwable $e) {
            Log::error('module_release.record_failed', [
                'slug' => $request->slug,
                'version' => $request->version,
                'error' => $e->getMessage(),
            ]);

            return new ModuleReleaseResult(
                status: ModuleReleaseResult::STATUS_FAILED,
                reason: 'خطا در ثبت نسخهٔ ماژول در پایگاه داده.'
            );
        }

        return new ModuleReleaseResult(
            status: ModuleReleaseResult::STATUS_CREATED,
            releaseId: $releaseId
        );
    }
}
