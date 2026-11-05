<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\DTO\CustomerRuntimeManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Services\MotionCustomerActivationService;
use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use App\Modules\MotionGraphics\Support\MotionAssetLicensePolicy;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use App\Services\ModuleLicenseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;

/**
 * ADR-078: provisions the local/test site (id 1 by default) end to end
 * through the real services — never raw inserts:
 *
 *   catalog sync → module licenses (base, and premium unless disabled)
 *   → TemplateAssetPackage `motion-library:core` (both assets optional)
 *   → SiteAssetPackage activation (only entitled items are installed)
 *   → site runtime flag → manifest (must build, with assets).
 *
 * Grants licenses, so it refuses to run in production. Idempotent: an
 * existing valid license is reused and an unchanged package is not
 * re-versioned (MotionRuntimeInstallationService's own idempotency).
 *
 *   php artisan db:seed --class=MotionGraphicsDemoSiteSeeder
 *   MOTION_DEMO_SITE_ID=7 MOTION_DEMO_PREMIUM=0 php artisan db:seed --class=MotionGraphicsDemoSiteSeeder
 */
final class MotionGraphicsDemoSiteSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('MotionGraphicsDemoSiteSeeder grants licenses and never runs in production. Skipped.');

            return;
        }

        $siteId = (int) env('MOTION_DEMO_SITE_ID', 1);
        $site = Site::query()->find($siteId) ?? throw new RuntimeException("Site [{$siteId}] does not exist.");
        $admin = User::query()->where('is_admin', true)->whereNull('site_id')->orderBy('id')->first()
            ?? throw new RuntimeException('No platform admin user (is_admin, no site_id) exists to act as the granting admin.');

        $manifest = $this->provision($site, $admin, filter_var(env('MOTION_DEMO_PREMIUM', true), FILTER_VALIDATE_BOOL));

        $this->command?->info(sprintf(
            'site %d: package %s, manifest %s with %d asset(s): %s',
            $site->id, $manifest->packageId, $manifest->manifestChecksum, count($manifest->assets),
            implode(', ', array_map(fn ($a): string => (string) $a->name, $manifest->assets))
        ));
    }

    public function provision(Site $site, User $admin, bool $withPremium): CustomerRuntimeManifest
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Motion demo provisioning never runs in production.');
        }

        $catalog = app(MotionLibraryCatalogService::class);
        $licenses = app(ModuleLicenseService::class);

        ModuleSeeder::seedMotionModules();
        $catalog->sync();

        $wanted = [RealMotionLicenseGateway::MODULE_SLUG => true, MotionAssetLicensePolicy::PREMIUM_MODULE_SLUG => $withPremium];
        foreach ($wanted as $slug => $grant) {
            $module = Module::query()->where('slug', $slug)->firstOrFail();
            if ($grant && ! $licenses->hasValidLicense($site, $module->id)) {
                $licenses->grantByAdmin($site, $module->id, $admin) ?? throw new RuntimeException("Could not grant [{$slug}].");
            }
        }

        // The installation services authorize a real platform-admin actor
        // against the trusted tenant (site) — the same checks the admin UI
        // goes through.
        Auth::setUser($admin);
        app(TenantContextContract::class)->set($site);
        $context = new MotionAdapterContext(
            actorId: app(PlatformAdminResolverContract::class)->actorIdentifier($admin),
            siteId: (string) $site->id,
            correlationId: (string) Str::uuid(),
        );

        $features = $site->features ?? [];
        $previous = (bool) ($features['motion_graphics_integration'] ?? false);
        $site->features = [...$features, 'motion_graphics_integration' => true];
        $site->save();

        try {
            app(MotionRuntimeInstallationService::class)->installTemplateForSite(
                MotionLibraryCatalogService::TEMPLATE_REFERENCE,
                [],
                $catalog->packageReferences(),
                [],
                null,
                $context,
            );

            return app(MotionCustomerActivationService::class)->enable($site, $context);
        } catch (\Throwable $e) {
            $site->features = [...($site->features ?? []), 'motion_graphics_integration' => $previous];
            $site->save();
            throw $e;
        }
    }
}
