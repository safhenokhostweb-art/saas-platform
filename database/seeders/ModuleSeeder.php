<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\ModuleFeature;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        Module::firstOrCreate(
            ['slug' => 'blog'],
            [
                'title' => 'وبلاگ',
                'description' => 'ماژول وبلاگ و مطالب',
                'is_active' => true,
            ]
        );

        Module::firstOrCreate(
            ['slug' => 'shop'],
            [
                'title' => 'فروشگاه',
                'description' => 'ماژول فروشگاه آنلاین',
                'is_active' => true,
            ]
        );

        $installment = Module::firstOrCreate(
            ['slug' => 'installment'],
            [
                'title' => 'پرداخت اقساطی',
                'description' => 'پرداخت اقساطی با سرویس‌دهنده‌ها',
                'is_active' => true,
            ]
        );

        ModuleFeature::firstOrCreate([
            'module_id' => $installment->id,
            'key' => 'azki_vam',
        ], [
            'title' => 'ازکی وام',
            'is_active' => true,
        ]);

        ModuleFeature::firstOrCreate([
            'module_id' => $installment->id,
            'key' => 'tara',
        ], [
            'title' => 'تارا',
            'is_active' => true,
        ]);

        ModuleFeature::firstOrCreate([
            'module_id' => $installment->id,
            'key' => 'snap_pay',
        ], [
            'title' => 'اسنپ پی',
            'is_active' => true,
        ]);

        // Phase 6 (docs/motion-integration-phase6-audit.md §5): registers
        // motion-graphics with the real canonical entitlement system
        // (App\Adapters\ModuleLicenseAdapter) so
        // RealMotionLicenseGateway has a module row to check against.
        // type=core + free_assignable=true makes checkEntitlement() allow
        // any active site unconditionally (same shape as blog/shop),
        // while remaining excluded from every live customer-facing
        // marketplace surface: TenantMarketplaceController only ever
        // queries Module::paidMarketplace() (type=paid), and
        // MarketplaceModuleCatalog requires a published ModuleRelease,
        // which motion-graphics deliberately never has. The *actual*
        // per-site "is Motion Graphics usable here" restriction is the
        // `motion_graphics_integration` feature flag, not this row —
        // see docs/motion-integration-phase6/FEATURE_FLAG_USAGE.md.
        Module::firstOrCreate(
            ['slug' => 'motion-graphics'],
            [
                'title' => 'Motion Graphics',
                'description' => 'Motion asset packages for template rendering (internal/admin-controlled only).',
                'is_active' => true,
                'type' => Module::TYPE_CORE,
                'free_assignable' => true,
                'license_required' => false,
                'marketplace_visible' => false,
            ]
        );
    }
}
