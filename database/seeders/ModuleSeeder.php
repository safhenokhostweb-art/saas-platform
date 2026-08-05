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

        // Phase 9 (docs/motion-commercialization/AUDIT_REPORT.md §5):
        // reclassified from Phase 6's type=core (deliberately internal
        // -only, for safely proving the technical integration with zero
        // commercial surface) to type=paid — a real commercial module.
        // This is an intentional evolution across phases, not a
        // contradiction: Phase 6-8 proved the integration was safe;
        // Phase 9 makes it sellable through the platform's real,
        // existing commerce pipeline (Module + Product), reused
        // unmodified — see MODULE_POSITIONING.md.
        //
        // type=paid changes ModuleLicenseAdapter::checkEntitlement()'s
        // own branching (unchanged code) to require a real ModuleLicense
        // row per site (hasValidLicense()'s TYPE_PAID gate) instead of
        // the old unconditional isCore() allow — RealMotionLicenseGateway
        // (Phase 6) needed zero code changes to pick this up. Free
        // internal assignment (task requirement) is unaffected: an admin
        // can still grant a ModuleLicense directly via the existing
        // ModuleLicenseController::grant(), no purchase required.
        //
        // marketplace_visible=true + a real Product row below makes it
        // genuinely discoverable and purchasable through
        // Module::paidMarketplace() / TenantMarketplaceController — the
        // same real customer-facing surface every other paid module
        // already uses. Runtime activation (the sites.features flag)
        // stays a separate, deliberate admin step — see
        // ENTITLEMENT_RULES.md / PLAN_INTEGRATION.md for why purchasing
        // never auto-activates it.
        $motionGraphics = Module::updateOrCreate(
            ['slug' => 'motion-graphics'],
            [
                'title' => 'Motion Graphics',
                'description' => 'موتور موشن‌گرافیک برای افزودن انیمیشن‌های آماده و بهینه‌شده به قالب‌های سایت.',
                'short_description' => 'Motion Graphics Engine — Design Enhancement',
                'category' => 'design_enhancement',
                'is_active' => true,
                'type' => Module::TYPE_PAID,
                'free_assignable' => false,
                'license_required' => false,
                'marketplace_visible' => true,
                'price_irr' => 4900000,
                'currency' => 'IRR',
                'license_type_default' => \App\Models\ModuleLicense::TYPE_ONE_TIME,
            ]
        );

        // The sellable unit (docs/motion-commercialization/AUDIT_REPORT.md
        // §1/§3) — reuses App\Models\Product exactly like the platform's
        // other paid add-ons (Product::TYPE_MODULE_ADDON), no new
        // commerce model introduced.
        \App\Models\Product::updateOrCreate(
            ['sku' => 'motion-graphics-addon'],
            [
                'name_fa' => 'موشن گرافیک',
                'name_en' => 'Motion Graphics Engine',
                'type' => \App\Models\Product::TYPE_MODULE_ADDON,
                'module_id' => $motionGraphics->id,
                'price_irr' => $motionGraphics->price_irr,
                'is_one_time' => true,
                'is_active' => true,
                'description' => 'افزودن انیمیشن‌های موشن‌گرافیک آماده به قالب سایت — دسته‌بندی: بهبود طراحی (Design Enhancement).',
            ]
        );
    }
}
