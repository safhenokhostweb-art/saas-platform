<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Models\User;
use App\Services\AssetProvisioning\SiteAssetEntitlementService;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\ModuleAssignmentService;
use App\Services\ModuleLicenseGrantService;

/**
 * ADR-088 — applies the onboarding wizard's licensing choices to the site
 * PROVISION_LICENSE just created, through the canonical writers only:
 *   - business vertical: SiteAssetEntitlementService::setVertical() (ADR-087);
 *   - each kit (a paid, license-only module, Module::isSiteLicensableKit(),
 *     e.g. `solar_industry_kit`; anything else fails the stage up front):
 *     ModuleLicenseGrantService::grant() then
 *     ModuleAssignmentService::enableForSite().
 * Together these are exactly what makes AssetAccessGate unlock a kit family
 * (vertical + entitlement key in the projection), so the industry template
 * is usable on the site from its first request.
 *
 * A kit that ships a code package is licensed but not activated here:
 * enableForSite() would dispatch a module installation against a site whose
 * own fresh installation is still running. That is recorded honestly in the
 * output (`activation_deferred`) for activation from the site's modules page.
 *
 * Platform-database only; nothing on the remote server. Site deletion
 * (PROVISION_LICENSE rollback) cascades every row written here, so no
 * rollback of its own is needed.
 */
class ApplySiteEntitlementsStage extends AbstractStage
{
    public function __construct(
        private readonly SiteAssetEntitlementService $assetAccess,
        private readonly ModuleLicenseGrantService $licenseGrants,
        private readonly ModuleAssignmentService $assignments,
    ) {}

    public function name(): string { return 'APPLY_SITE_ENTITLEMENTS'; }
    public function label(): string { return 'اعمال حوزهٔ کسب‌وکار و کیت‌های لایسنس'; }
    public function estimatedSeconds(): int { return 2; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $req = $ctx->request;

            if ($req->businessVertical === null && $req->kitModuleIds === [] && empty($req->activeModuleIds)) {
                return StageResult::skipped($this->name(), 'No business vertical, kits, or modules requested');
            }

            $site = $ctx->installation->site_id ? Site::find($ctx->installation->site_id) : null;
            if ($site === null) {
                return StageResult::failure($this->name(), 'Site record is missing — PROVISION_LICENSE must run first.', $this->elapsed());
            }

            $actor = $ctx->installation->installer_user_id ? User::find($ctx->installation->installer_user_id) : null;

            // Fail closed, and before anything is written: every requested
            // module must be a kit a site can be licensed for
            // (Module::isSiteLicensableKit, what ModuleLicenseGrantService
            // accepts). Installation 53 (2026-11-17) carried 53 core modules
            // with license_required, which the wizard used to offer as kits;
            // the stage granted the first kits, then failed on
            // «marketing_automation». The wizard now offers and accepts only
            // real kits; a stored payload that still has others is refused
            // here in one message.
            $modules = [];
            $notKits = [];
            foreach ($req->kitModuleIds as $moduleId) {
                $module = Module::find($moduleId);
                if ($module === null) {
                    return StageResult::failure($this->name(), "Module #{$moduleId} does not exist.", $this->elapsed());
                }
                if (! $module->isSiteLicensableKit()) {
                    $notKits[] = $module->slug;
                }
                $modules[] = $module;
            }
            if ($notKits !== []) {
                return StageResult::failure(
                    $this->name(),
                    'این ماژول‌ها کیت قابل‌لایسنس برای سایت نیستند و لایسنسی برایشان صادر نمی‌شود: «' . implode('»، «', $notKits)
                        . '». فقط کیت‌های پولیِ فقط‌لایسنسی (مثل solar_industry_kit) به سایت لایسنس می‌شوند؛ نصب را از ویزارد دوباره شروع کنید.',
                    $this->elapsed()
                );
            }

            if ($req->businessVertical !== null) {
                $this->assetAccess->setVertical($site, $req->businessVertical, $actor?->id);
            }

            $kits = [];
            foreach ($modules as $module) {
                if ($actor === null) {
                    return StageResult::failure($this->name(), 'The installing admin no longer exists; a kit license needs a granting admin.', $this->elapsed());
                }

                $grant = $this->licenseGrants->grant($site, $module->id, $actor, null, 'onboarding_wizard');
                if ($grant === ModuleLicenseGrantService::INVALID_MODULE) {
                    return StageResult::failure($this->name(), "Kit «{$module->slug}» cannot be licensed to a site.", $this->elapsed());
                }

                if ($this->hasCodePackage($module)) {
                    $kits[$module->slug] = 'activation_deferred';
                    continue;
                }

                $enabled = $this->assignments->enableForSite($site, $module->id, $actor->id);
                if (! ($enabled['success'] ?? false)) {
                    return StageResult::failure(
                        $this->name(),
                        "Kit «{$module->slug}» could not be activated: " . ($enabled['error'] ?? 'unknown error'),
                        $this->elapsed()
                    );
                }
                $kits[$module->slug] = 'active';

                if (in_array($module->slug, ['motion-graphics', 'motion-graphics-premium'], true)) {
                    $features = $site->features ?? [];
                    if (! ($features['motion_graphics_integration'] ?? false)) {
                        $features['motion_graphics_integration'] = true;
                        $site->features = $features;
                        $site->save();
                    }
                }
                if ($module->slug === 'solar_industry_kit' || $req->businessVertical === 'solar_energy') {
                    $solarMotion = \SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic::where('slug', 'solar-motion-graphic')->first();
                    if ($solarMotion && $solarMotion->uuid) {
                        \App\Modules\MotionGraphics\Models\MotionSiteAssetGrant::query()->firstOrCreate(
                            ['site_id' => $site->id, 'asset_id' => $solarMotion->uuid],
                            [
                                'status' => \App\Modules\MotionGraphics\Models\MotionSiteAssetGrant::STATUS_ACTIVE,
                                'source' => \App\Modules\MotionGraphics\Models\MotionSiteAssetGrant::SOURCE_ADMIN_GRANT,
                                'reference' => 'onboarding_vertical_solar_energy',
                                'note' => 'Auto-granted for solar energy vertical onboarding',
                                'granted_by_actor_identifier' => $actor ? 'admin:' . $actor->id : 'system',
                                'granted_at' => now(),
                            ]
                        );
                    }
                }
            }

            // Phase 18 / Onboarding unifications: Activate selected general and core modules directly
            $activatedModules = [];
            if (!empty($req->activeModuleIds)) {
                $additionalModules = Module::whereIn('id', $req->activeModuleIds)
                    ->where('is_active', true)
                    ->get();

                foreach ($additionalModules as $mod) {
                    if ($mod->isSiteLicensableKit()) {
                        continue; // Already processed via kits loop
                    }

                    \Illuminate\Support\Facades\DB::table('site_modules')->updateOrInsert(
                        ['site_id' => $site->id, 'module_id' => $mod->id],
                        [
                            'is_active' => true,
                            'deployment_status' => 'installed',
                            'activated_at' => now(),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                    $activatedModules[$mod->slug] = 'active';
                }
            }

            return StageResult::success($this->name(), [
                'site_id'           => $site->id,
                'business_vertical' => $site->fresh()->business_vertical,
                'kits'              => $kits,
                'modules'           => $activatedModules,
            ], [], $this->elapsed());
        });
    }

    private function hasCodePackage(Module $module): bool
    {
        $release = ModuleRelease::latestPublishedForModule($module->id);

        return $release !== null && !empty($release->package_sha256);
    }
}
