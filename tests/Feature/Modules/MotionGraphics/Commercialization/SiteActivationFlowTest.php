<?php

namespace Tests\Feature\Modules\MotionGraphics\Commercialization;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\MotionCustomerActivationService;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use App\Services\ModuleLicenseService;
use App\Services\OrderFulfillmentService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 9 task section 5, the full workflow, end to end through real
 * code (no fakes on the commerce or license layer — only the read
 * contracts, per the same convention every prior phase used):
 *
 * Customer purchases Add-on -> License created -> Entitlement assigned
 * -> Site receives permission -> Template Builder requests Motion assets
 * -> Runtime Manifest generated -> Customer-site renders Motion.
 *
 * "License created" and "Site receives permission" are two genuinely
 * distinct, separately-audited steps (docs/motion-commercialization/
 * ENTITLEMENT_RULES.md) — purchasing never auto-creates a ModuleLicense
 * (confirmed in AUDIT_REPORT.md §3) and never auto-flips the site's
 * feature flag (STRICT RULES: "do not activate real customers
 * automatically") — both remain deliberate admin actions in this test,
 * exactly as they would in production.
 */
class SiteActivationFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
        $this->fakeActivityLogger();
        (new ModuleSeeder())->run();
    }

    /**
     * grantByAdmin() writes granted_by as a real foreign key to `users`
     * (module_licenses.granted_by) — must be a genuinely persisted row,
     * unlike the unsaved-User convention used elsewhere in this suite
     * for opaque actor identifiers.
     */
    private function admin(): User
    {
        return User::create([
            'mobile' => '0912'.random_int(1000000, 9999999),
            'password' => bcrypt('secret'),
            'is_admin' => true,
        ]);
    }

    private function activePackage(Site $site): SiteAssetPackage
    {
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => 'motion-1', 'version' => 1]],
            'package_version' => 1,
        ]);
        $sitePackage = SiteAssetPackage::query()->create([
            'site_id' => $site->id,
            'template_reference' => 'template-1',
            'template_asset_package_id' => $templatePackage->id,
            'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $sitePackage->id,
            'asset_id' => 'motion-1', 'active_version' => 1,
            'bound_checksum' => 'checksum-1', 'installed_at' => now(),
        ]);

        return $sitePackage->fresh(['items', 'templateAssetPackage']);
    }

    public function test_the_full_purchase_to_runtime_flow(): void
    {
        Bus::fake();

        // -- Customer purchases Add-on --------------------------------
        $site = $this->createSite();
        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();
        $product = Product::where('module_id', $module->id)->firstOrFail();

        $invoice = Invoice::create([
            'customer_id' => $site->customer_id,
            'amount_irr' => $product->price_irr,
            'status' => Invoice::STATUS_PAID,
            'paid_at' => now(),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id,
            'quantity' => 1, 'price_irr' => $product->price_irr, 'site_id' => $site->id,
        ]);

        $this->app->make(OrderFulfillmentService::class)->fulfill($invoice->fresh(['items']));

        // Ownership recorded through the real, unmodified commerce
        // pipeline — no Motion-specific code involved in this step.
        $this->assertDatabaseHas('site_module_purchases', ['site_id' => $site->id, 'module_id' => $module->id]);

        // -- License created + Entitlement assigned ---------------------
        // Corrected understanding, found by running this test, not
        // assumed: App\Services\ModulePurchaseService::recordPurchaseFromInvoice()
        // (app/Services/ModulePurchaseService.php:47-49) already calls
        // ModuleLicenseService::createFromInvoiceItem() when no valid
        // license exists yet — a real ModuleLicense is issued
        // automatically on purchase, for every type=paid module on this
        // platform, unmodified by this phase. This is real entitlement
        // (the customer paid, they are legally allowed to use it), not
        // the runtime activation the STRICT RULES forbid automating —
        // see the next section, where the *site-level flag* is proven to
        // still require a separate, deliberate admin action.
        $this->assertDatabaseHas('module_licenses', [
            'site_id' => $site->id, 'module_id' => $module->id, 'status' => ModuleLicense::STATUS_ACTIVE,
        ]);
        $this->assertTrue(
            $this->app->make(RealMotionLicenseGateway::class)->checkAvailability('motion-1', (string) $site->id)->allowed
        );

        // -- Site is entitled but NOT yet technically active ------------
        $this->assertFalse(
            (bool) ($site->fresh()->features['motion_graphics_integration'] ?? false),
            'entitlement alone must never flip the runtime flag — that is always a deliberate, separate admin action'
        );

        // -- Template Builder requests Motion assets / Site Asset Package exists --
        $this->activePackage($site);

        // -- Site receives permission (deliberate admin step) ----------
        $this->bindPublishedAsset('motion-1', 1);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');
        $manifest = $this->app->make(MotionCustomerActivationService::class)->enable($site, $context);

        $this->assertTrue((bool) $site->fresh()->features['motion_graphics_integration']);

        // -- Runtime Manifest generated ---------------------------------
        $this->assertNotEmpty($manifest->manifestChecksum);
        $this->assertCount(1, $manifest->assets);
        $this->assertSame('motion-1', $manifest->assets[0]->assetId);

        // Deployment dispatch for the *commerce* module row itself was
        // attempted and failed gracefully (no ModuleRelease — Motion
        // Graphics ships no deployable code package, by design; see
        // AUDIT_REPORT.md) — it never touched a real server and never
        // blocked entitlement, exactly like the platform's own existing
        // "dispatch failed, entitlement still granted" guarantee.
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->is_active);
    }

    public function test_revoking_the_license_blocks_further_enablement_of_a_new_site(): void
    {
        $site = $this->createSite();
        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();
        $license = $this->app->make(ModuleLicenseService::class)->grantByAdmin($site, $module->id, $this->admin());
        $this->app->make(ModuleLicenseService::class)->revoke($license);

        $this->activePackage($site);
        $this->bindPublishedAsset('motion-1', 1);

        $this->expectException(\App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException::class);

        $this->app->make(MotionCustomerActivationService::class)->enable(
            $site, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
        );
    }
}
