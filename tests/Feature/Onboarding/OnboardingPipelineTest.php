<?php

namespace Tests\Feature\Onboarding;

use App\Jobs\PushSiteTemplateJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Models\ThemeAssignment;
use App\Services\AssetProvisioning\SiteAssetEntitlementService;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\InstallationStateManager;
use App\Services\CustomerInstallation\Ssl\NginxVhostWriter;
use App\Services\CustomerInstallation\Stages\ApplySiteEntitlementsStage;
use App\Services\CustomerInstallation\Stages\ClearApplicationCacheStage;
use App\Services\CustomerInstallation\Stages\GenerateEnvironmentStage;
use App\Services\CustomerInstallation\Stages\InstallThemeStage;
use App\Services\CustomerInstallation\Stages\ProvisionLicenseStage;
use App\Services\Onboarding\SiteTemplateBinder;
use App\Services\ThemeBuilder\ThemeInstallGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessGate;
use Tests\TestCase;

/**
 * ADR-088 — what the pipeline does with a wizard submission: owner
 * attachment, vertical + kit licensing, theme binding and push, cache
 * clearing, the provision secret, and the nginx vhost of a new subdomain.
 */
class OnboardingPipelineTest extends TestCase
{
    use OnboardingFixtures;
    use RefreshDatabase;

    private FakeRemoteDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        // InstallationTargetGuard::packageError() (17648113) refuses to start
        // without an active install package; the wizard GET used to fabricate one.
        \App\Models\CustomerInstallationPackage::create([
            'version' => '1.1.0', 'arvan_key' => 'packages/customer-site-v1.1.0.zip',
            'sha256' => str_repeat('a', 64), 'size_bytes' => 1024, 'is_active' => true, 'released_at' => now(),
        ]);
        $this->platformRootDomain();
        $this->driver = new FakeRemoteDriver();
        config(['services.customer_provision_secret' => 'provision-secret-for-tests']);
    }

    private function installation(array $payload = [], ?int $installerId = null, ?int $siteId = null): CustomerInstallation
    {
        $server = $this->server();
        $payload = array_merge([
            'company' => 'خورشید انرژی پارس', 'owner_name' => 'سارا', 'email' => '', 'phone' => '09351234567',
            'domain' => 'khorshid.' . self::ROOT, 'license_type' => 'basic', 'theme_slug' => '', 'modules' => [],
            'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => true, 'remote_server_id' => $server->id,
            'install_mode' => 'MODE_FRESH',
        ], $payload);

        return CustomerInstallation::create([
            'site_id' => $siteId, 'remote_server_id' => $server->id, 'installer_user_id' => $installerId ?? $this->admin()->id,
            'install_mode' => 'MODE_FRESH', 'status' => 'running', 'customer_company' => $payload['company'],
            'customer_email' => '', 'customer_domain' => $payload['domain'], 'request_payload' => $payload,
        ]);
    }

    private function runStage(string $stageClass, CustomerInstallation $installation): StageResult
    {
        $installation->refresh();
        $ctx = new InstallationContext($installation, CustomerInstallRequest::fromArray($installation->request_payload), $this->driver);

        return app($stageClass)->execute($ctx);
    }

    /** The content of an `echo <base64> | base64 -d > file` write (quoting differs per host OS). */
    private function decodedWrite(?string $command): string
    {
        $this->assertNotNull($command, 'expected a file write command');
        $this->assertSame(1, preg_match('/echo ["\']([A-Za-z0-9+\/=]+)["\'] \| base64 -d/', $command, $m), $command);

        return base64_decode($m[1]);
    }

    private function siteOf(CustomerInstallation $installation): Site
    {
        return Site::findOrFail($installation->fresh()->site_id);
    }

    // ── stage order ────────────────────────────────────────────────────

    public function test_onboarding_stages_run_in_a_safe_order(): void
    {
        $names = array_map(fn ($s) => $s->name(), app(CustomerInstallationOrchestrator::class)->buildStageList('MODE_FRESH'));
        $pos = array_flip($names);

        $this->assertLessThan($pos['APPLY_SITE_ENTITLEMENTS'], $pos['PROVISION_LICENSE'], 'entitlements need the site PROVISION_LICENSE creates');
        $this->assertLessThan($pos['INSTALL_THEME'], $pos['APPLY_SITE_ENTITLEMENTS']);
        $this->assertLessThan($pos['CLEAR_APPLICATION_CACHE'], $pos['INSTALL_THEME']);
        $this->assertSame($pos['CUSTOMER_READY'] - 1, $pos['CLEAR_APPLICATION_CACHE'], 'caches are cleared right before the site opens');
        $this->assertLessThan($pos['CLEAR_APPLICATION_CACHE'], $pos['CONFIGURE_WEB_SERVER']);
    }

    // ── PROVISION_LICENSE ──────────────────────────────────────────────

    public function test_the_site_is_attached_to_the_customer_the_wizard_resolved(): void
    {
        $customer = $this->customer('09121112233');
        $this->site($customer, 'existing.' . self::ROOT);
        $installation = $this->installation(['customer_id' => $customer->id, 'phone' => '09121112233']);

        $result = $this->runStage(ProvisionLicenseStage::class, $installation);

        $this->assertTrue($result->success, $result->error ?? '');
        $site = $this->siteOf($installation);
        $this->assertSame($customer->id, $site->customer_id);
        $this->assertSame('khorshid.' . self::ROOT, $site->domain);
        $this->assertNotEmpty($site->license_key);
        $this->assertSame(2, $customer->sites()->count(), 'one mobile manages several sites');
    }

    public function test_a_legacy_payload_without_a_valid_mobile_fails_instead_of_merging_owners(): void
    {
        $this->customer('09121112233');
        Customer::create(['name' => 'Phone-less', 'mobile' => '']);
        $installation = $this->installation(['phone' => '']);

        $result = $this->runStage(ProvisionLicenseStage::class, $installation);

        $this->assertFalse($result->success);
        $this->assertNull($installation->fresh()->site_id);
        $this->assertSame(0, Site::query()->where('domain', 'khorshid.' . self::ROOT)->count());
    }

    public function test_a_legacy_payload_reuses_the_owner_of_a_differently_typed_mobile(): void
    {
        $customer = $this->customer('09121112233');
        $installation = $this->installation(['phone' => '+98 912 111 2233']);

        $this->assertTrue($this->runStage(ProvisionLicenseStage::class, $installation)->success);
        $this->assertSame($customer->id, $this->siteOf($installation)->customer_id);
        $this->assertSame(1, Customer::query()->where('mobile', '09121112233')->count());
    }

    // ── APPLY_SITE_ENTITLEMENTS ────────────────────────────────────────

    public function test_vertical_and_kit_are_applied_so_the_solar_family_unlocks_on_the_new_site(): void
    {
        $kit = $this->solarKit();
        $installation = $this->installation(['business_vertical' => 'solar_energy', 'kit_module_ids' => [$kit->id]]);
        $this->assertTrue($this->runStage(ProvisionLicenseStage::class, $installation)->success);

        $result = $this->runStage(ApplySiteEntitlementsStage::class, $installation);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame(['solar_industry_kit' => 'active'], $result->output['kits']);
        $site = $this->siteOf($installation);
        $this->assertSame('solar_energy', $site->business_vertical);
        $this->assertTrue(ModuleLicense::query()->where(['site_id' => $site->id, 'module_id' => $kit->id, 'status' => ModuleLicense::STATUS_ACTIVE])->exists());
        $this->assertTrue((bool) $site->modules()->wherePivot('is_active', true)->where('modules.id', $kit->id)->exists());

        $entitlements = app(SiteAssetEntitlementService::class)->forSite($site->fresh());
        $this->assertContains('solar_industry_kit', $entitlements->entitlementKeys);
        $this->assertTrue(AssetAccessGate::decideTemplate('lib:solar-page-home', $entitlements)->allowed());
        $this->assertTrue(AssetAccessGate::decideTemplate('lib:solar-header', $entitlements)->allowed());
    }

    public function test_entitlements_are_skipped_when_the_wizard_chose_none(): void
    {
        $installation = $this->installation();
        $this->runStage(ProvisionLicenseStage::class, $installation);

        $result = $this->runStage(ApplySiteEntitlementsStage::class, $installation);

        $this->assertTrue($result->success);
        $this->assertNull($this->siteOf($installation)->business_vertical);
        $this->assertSame(0, ModuleLicense::count());
    }

    public function test_entitlements_fail_closed_for_a_module_that_is_not_a_kit(): void
    {
        $core = \App\Models\Module::query()->create(['slug' => 'core-x', 'title' => 'Core', 'type' => \App\Models\Module::TYPE_CORE, 'is_active' => true]);
        $installation = $this->installation(['kit_module_ids' => [$core->id]]);
        $this->runStage(ProvisionLicenseStage::class, $installation);

        $result = $this->runStage(ApplySiteEntitlementsStage::class, $installation);

        $this->assertFalse($result->success);
        $this->assertSame(0, ModuleLicense::count());
    }

    // ── INSTALL_THEME ──────────────────────────────────────────────────

    public function test_the_theme_is_assigned_and_queued_for_push_once_the_site_opens(): void
    {
        $theme = $this->eligibleTheme();
        $installation = $this->installation(['theme_id' => $theme->id, 'theme_slug' => $theme->slug]);
        $this->runStage(ProvisionLicenseStage::class, $installation);

        $result = $this->runStage(InstallThemeStage::class, $installation);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('assigned', $result->output['status']);
        $site = $this->siteOf($installation);
        $this->assertSame($theme->id, (int) $site->theme_id);
        $this->assertTrue(ThemeAssignment::query()->where(['site_id' => $site->id, 'theme_id' => $theme->id, 'status' => ThemeAssignment::STATUS_ASSIGNED])->exists());
        $this->assertSame(SiteTemplateBinder::STATUS_PENDING_PUSH, $installation->fresh()->metadata['template_binding']['status']);
    }

    public function test_a_legacy_theme_slug_is_finally_honoured(): void
    {
        $theme = $this->eligibleTheme('legacy-theme');
        $installation = $this->installation(['theme_slug' => 'legacy-theme']);
        $this->runStage(ProvisionLicenseStage::class, $installation);

        $this->assertSame('assigned', $this->runStage(InstallThemeStage::class, $installation)->output['status']);
        $this->assertSame($theme->id, (int) $this->siteOf($installation)->theme_id);
    }

    public function test_an_unassignable_theme_is_a_recorded_warning_not_a_rollback(): void
    {
        $draft = $this->draftTheme();
        $installation = $this->installation(['theme_id' => $draft->id]);
        $this->runStage(ProvisionLicenseStage::class, $installation);

        $result = $this->runStage(InstallThemeStage::class, $installation);

        $this->assertTrue($result->success);
        $this->assertSame('warning', $result->output['status']);
        $this->assertSame(SiteTemplateBinder::STATUS_FAILED, $installation->fresh()->metadata['template_binding']['status']);
        $this->assertNull($this->siteOf($installation)->theme_id);
    }

    // ── CLEAR_APPLICATION_CACHE ────────────────────────────────────────

    public function test_caches_are_cleared_in_the_web_root_then_www_data_ownership_is_restored(): void
    {
        $installation = $this->installation();

        $result = $this->runStage(ClearApplicationCacheStage::class, $installation);

        $this->assertTrue($result->success);
        $this->assertSame('cleared', $result->output['status']);
        $log = $this->driver->getCommandLog();
        $webRoot = escapeshellarg('/var/www/cust_site_' . $installation->id); // host-quoted, as the stage does
        foreach (['config:clear', 'cache:clear', 'view:clear'] as $i => $command) {
            $this->assertStringContainsString("cd {$webRoot} && php artisan {$command}", $log[$i]);
        }
        $this->assertStringContainsString("chown -R www-data:www-data {$webRoot}/storage {$webRoot}/bootstrap/cache", $log[3]);
    }

    public function test_a_failed_cache_clear_is_reported_as_a_warning(): void
    {
        $this->driver->addResponse('view:clear', 1, 'boom');

        $result = $this->runStage(ClearApplicationCacheStage::class, $this->installation());

        $this->assertTrue($result->success);
        $this->assertSame('warning', $result->output['status']);
        $this->assertStringStartsWith('failed', $result->output['commands']['view:clear']);
    }

    // ── GENERATE_ENVIRONMENT ───────────────────────────────────────────

    public function test_the_generated_env_carries_the_provision_secret_the_theme_push_needs(): void
    {
        $installation = $this->installation();

        $this->assertTrue($this->runStage(GenerateEnvironmentStage::class, $installation)->success);

        $write = collect($this->driver->getCommandLog())->first(fn ($c) => str_contains($c, '/.env'));
        $env = $this->decodedWrite($write);
        $this->assertStringContainsString("\nCUSTOMER_PROVISION_SECRET=provision-secret-for-tests\n", $env);
        $this->assertStringContainsString("\nADMIN_MOBILE=09351234567\n", $env);
    }

    // ── template push ──────────────────────────────────────────────────

    public function test_the_bound_theme_is_pushed_to_the_live_site_with_the_provision_secret(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $theme = $this->eligibleTheme();
        $installation = $this->installation(['theme_id' => $theme->id]);
        $this->runStage(ProvisionLicenseStage::class, $installation);
        $this->runStage(InstallThemeStage::class, $installation);
        $installation->update(['status' => 'complete']);

        $this->assertTrue(app(SiteTemplateBinder::class)->bind($installation));

        $this->assertSame(SiteTemplateBinder::STATUS_BOUND, $installation->fresh()->metadata['template_binding']['status']);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://khorshid.' . self::ROOT . '/builder/api/theme/install'
            && $r->header('X-Provision-Secret')[0] === 'provision-secret-for-tests'
            && $r['theme_id'] === $theme->slug
            && isset($r['pages']['home']));
    }

    public function test_a_refused_push_is_recorded_for_retry(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Provisioning not configured'], 403)]);
        $theme = $this->eligibleTheme();
        $installation = $this->installation(['theme_id' => $theme->id]);
        $this->runStage(ProvisionLicenseStage::class, $installation);
        $this->runStage(InstallThemeStage::class, $installation);
        $installation->update(['status' => 'complete']);

        $this->assertFalse(app(SiteTemplateBinder::class)->bind($installation));

        $binding = $installation->fresh()->metadata['template_binding'];
        $this->assertSame(SiteTemplateBinder::STATUS_FAILED, $binding['status']);
        $this->assertStringContainsString('403', $binding['message']);
        $this->assertSame(1, $binding['attempts']);
    }

    public function test_the_push_is_queued_only_once_the_installation_completed(): void
    {
        Queue::fake();
        $theme = $this->eligibleTheme();
        $installation = $this->installation(['theme_id' => $theme->id]);
        $this->runStage(ProvisionLicenseStage::class, $installation);
        $this->runStage(InstallThemeStage::class, $installation);
        $binder = app(SiteTemplateBinder::class);

        $this->assertFalse($binder->dispatchIfPending($installation), 'still running: customer-site would redirect to /install');
        Queue::assertNothingPushed();

        app(InstallationStateManager::class)->markComplete($installation);
        $this->assertTrue($binder->dispatchIfPending($installation));
        Queue::assertPushed(PushSiteTemplateJob::class, fn ($job) => $job->installationId === $installation->id);
    }

    public function test_the_push_job_retries_a_refused_push_with_a_delay(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $theme = $this->eligibleTheme();
        $installation = $this->installation(['theme_id' => $theme->id]);
        $this->runStage(ProvisionLicenseStage::class, $installation);
        $this->runStage(InstallThemeStage::class, $installation);
        $installation->update(['status' => 'complete']);

        $job = new PushSiteTemplateJob($installation->id);
        $queueJob = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(PushSiteTemplateJob::RETRY_DELAYS[0]);
        $queueJob->shouldReceive('isReleased', 'isDeleted', 'hasFailed')->andReturn(false);
        $job->setJob($queueJob);

        $job->handle(app(SiteTemplateBinder::class));
    }

    public function test_retry_from_the_installation_page_requeues_the_push(): void
    {
        Queue::fake();
        $theme = $this->eligibleTheme();
        $installation = $this->installation(['theme_id' => $theme->id]);
        $this->runStage(ProvisionLicenseStage::class, $installation);
        app(SiteTemplateBinder::class)->markFailed($installation, $theme, 'timeout');
        $installation->update(['status' => 'complete']);

        $this->actingAs($this->admin())
            ->post(route('admin.onboarding.retry-template', $installation))
            ->assertSessionHas('success');

        $this->assertSame(SiteTemplateBinder::STATUS_PENDING_PUSH, $installation->fresh()->metadata['template_binding']['status']);
        Queue::assertPushed(PushSiteTemplateJob::class);
    }

    public function test_the_installation_page_shows_every_pipeline_stage_and_the_binding(): void
    {
        $theme = $this->eligibleTheme();
        $installation = $this->installation(['theme_id' => $theme->id]);
        app(SiteTemplateBinder::class)->markPendingPush($installation, $theme);

        $this->actingAs($this->admin())
            ->get(route('admin.infrastructure.customer-deployment.installations.show', $installation))
            ->assertOk()
            ->assertSee('اعمال حوزهٔ کسب‌وکار و کیت‌های لایسنس')
            ->assertSee('پاک‌سازی کش برنامه و نماها')
            ->assertSee('بررسی DNS دامنه')
            ->assertSee('اتصال فایل‌های سایت به فضای ابری') // CONFIGURE_MEDIA_STORAGE (Phase 4)
            ->assertSee('/ 28 مرحله', false)
            ->assertSee('در انتظار پایان نصب و اعمال روی سایت');
    }

    // ── nginx vhost of a new subdomain ─────────────────────────────────

    public function test_a_new_subdomain_gets_its_own_vhost_on_the_detected_php_fpm_socket(): void
    {
        $this->driver->addResponse('PHP_MAJOR_VERSION', 0, '8.4');
        $this->driver->addResponse('test -S', 0, 'exists');
        $this->driver->addResponse('nginx -t', 0, 'syntax is ok, test is successful');
        $installation = $this->installation(['ssl_enabled' => false]);
        $installation->refresh();
        $ctx = new InstallationContext($installation, CustomerInstallRequest::fromArray($installation->request_payload), $this->driver);

        $result = app(NginxVhostWriter::class)->write($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $slug = 'cust_site_' . $installation->id;
        $this->assertSame("/etc/nginx/sites-available/{$slug}", $result->output['config_path']);
        $write = collect($this->driver->getCommandLog())->first(fn ($c) => str_contains($c, 'sites-available') && str_contains($c, 'base64 -d'));
        $vhost = $this->decodedWrite($write);
        $this->assertStringContainsString('server_name khorshid.' . self::ROOT . ';', $vhost);
        $this->assertStringContainsString("root /var/www/{$slug}/public;", $vhost);
        $this->assertStringContainsString('fastcgi_pass unix:/run/php/php8.4-fpm.sock;', $vhost);
    }

    // ── end to end ─────────────────────────────────────────────────────

    public function test_a_wizard_submission_flows_through_the_pipeline_to_a_bound_industry_site(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $theme = $this->eligibleTheme('solar-industry');
        $kit = $this->solarKit();
        $owner = $this->customer('09351234567', 'سارا محمدی');
        $this->site($owner, 'first.' . self::ROOT);

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), [
            'customer_name' => '', 'business_vertical' => 'solar_energy', 'kit_module_ids' => [$kit->id], 'theme_id' => $theme->id,
        ]))->assertSessionHasNoErrors();
        $installation = CustomerInstallation::query()->sole();

        foreach ([ProvisionLicenseStage::class, ApplySiteEntitlementsStage::class, InstallThemeStage::class, ClearApplicationCacheStage::class] as $stage) {
            $result = $this->runStage($stage, $installation);
            $this->assertTrue($result->success, $stage . ': ' . ($result->error ?? ''));
        }
        app(InstallationStateManager::class)->markComplete($installation);
        $this->assertTrue(app(SiteTemplateBinder::class)->dispatchIfPending($installation));
        (new PushSiteTemplateJob($installation->id))->handle(app(SiteTemplateBinder::class));

        $site = $this->siteOf($installation);
        $this->assertSame($owner->id, $site->customer_id);
        $this->assertSame(2, $owner->sites()->count());
        $this->assertSame('khorshid.' . self::ROOT, $site->domain);
        $this->assertSame('solar_energy', $site->business_vertical);
        $this->assertSame($theme->id, (int) $site->theme_id);
        $this->assertTrue(AssetAccessGate::decideTemplate('lib:solar-page-home', app(SiteAssetEntitlementService::class)->forSite($site))->allowed());
        $this->assertSame(SiteTemplateBinder::STATUS_BOUND, $installation->fresh()->metadata['template_binding']['status']);
        Http::assertSentCount(1);
    }
}
