<?php

namespace Tests\Feature\CustomerDeletion;

use App\Jobs\CustomerDeletionPipelineJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Services\CustomerDeletion\CustomerDeleteOrchestrator;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Purge\PurgePlanBuilder;
use App\Services\CustomerDeletion\Purge\TenantObjectPurger;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CustomerDeletion\FakePurgeServer;
use Tests\Support\CustomerDeletion\FakeTenantObjectPurger;
use Tests\TestCase;

/**
 * ADR-090 — «حذف کامل سایت» end to end: authorization, dry-run, the three
 * confirmations, queueing, and the pipeline outcome on a simulated server
 * and object store (what is gone AND what must survive).
 */
class SitePurgeFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakePurgeServer $server;
    private FakeTenantObjectPurger $objects;

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();

        config(['app.url' => 'https://safhenokhost.ir']);

        $this->objects = new FakeTenantObjectPurger();
        $this->app->instance(TenantObjectPurger::class, $this->objects);

        $this->server = new FakePurgeServer();
        $server = $this->server;
        $this->app->instance(RemoteConnectionService::class, new class($server) extends RemoteConnectionService {
            public function __construct(private readonly FakePurgeServer $driver) {}
            public function buildDriver(CustomerRemoteServer $server): RemoteConnectionInterface
            {
                return $this->driver;
            }
        });
    }

    // ── fixtures ─────────────────────────────────────────────────────────

    private function superAdmin(): User
    {
        $user = User::create(['mobile' => '09131230001', 'password' => Hash::make('secret-pass'), 'is_admin' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', Role::SUPER_ADMIN)->value('id'));

        return $user;
    }

    private function plainAdmin(): User
    {
        return User::create(['mobile' => '09131230002', 'password' => Hash::make('secret-pass'), 'is_admin' => true, 'is_active' => true]);
    }

    /** @return array{0: Site, 1: CustomerInstallation, 2: string} site, installation, web root */
    private function installedSite(?string $serverPath = null, bool $recordWebRoot = true): array
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Prod', 'type' => 'fake', 'host' => '87.107.12.81', 'port' => 22, 'username' => 'deploy',
            'auth_method' => 'key', 'is_platform_managed' => true, 'path' => $serverPath,
        ]);
        $customer = Customer::create(['name' => 'مشتری', 'mobile' => '09120000001']);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'فروشگاه', 'domain' => 'shop.example.ir']);
        SiteDomain::create(['site_id' => $site->id, 'domain' => 'old-shop.example.ir', 'role' => SiteDomain::ROLE_ALIAS, 'status' => SiteDomain::STATUS_REDIRECT]);

        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'complete',
            'customer_company' => 'Shop Co', 'customer_email' => '', 'customer_domain' => 'shop.example.ir', 'site_id' => $site->id,
            'request_payload' => [
                'company' => 'Shop Co', 'owner_name' => 'O', 'email' => '', 'phone' => '09120000000', 'language' => 'fa',
                'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => 'shop.example.ir', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => true,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $slug    = DeleteContext::resolveSlug($installation);
        $webRoot = "/var/www/{$slug}";

        if ($recordWebRoot) {
            CustomerInstallationLog::create(['installation_id' => $installation->id, 'stage' => 'EXTRACT_PACKAGE', 'event' => 'completed',
                'message' => 'ok', 'output' => ['web_root' => $webRoot], 'created_at' => now()]);
        }
        CustomerInstallationLog::create(['installation_id' => $installation->id, 'stage' => 'CREATE_DATABASE', 'event' => 'completed',
            'message' => 'ok', 'output' => ['db_name' => 'cust_db_' . $installation->id, 'db_user' => 'u_shop' . $installation->id], 'created_at' => now()]);

        // The simulated server: this site, its config, and a neighbour that must survive.
        $this->server
            ->put("{$webRoot}/.env", 'APP_URL=https://shop.example.ir')
            ->put("{$webRoot}/public/index.php")
            ->put("/etc/nginx/sites-available/{$slug}", "server_name shop.example.ir;\nssl_certificate /etc/letsencrypt/live/shop.example.ir/fullchain.pem;")
            ->put("/etc/nginx/sites-enabled/{$slug}", 'symlink')
            ->put('/etc/letsencrypt/renewal/shop.example.ir.conf', "webroot_path = {$webRoot}/public,\n")
            ->put('/etc/letsencrypt/live/shop.example.ir/fullchain.pem')
            ->put('/etc/letsencrypt/renewal/old-shop.example.ir.conf', "webroot_path = {$webRoot}/public,\n")
            ->put("/etc/cron.d/{$slug}", '* * * * * php artisan schedule:run')
            ->put("/etc/supervisor/conf.d/{$slug}_worker.conf", '[program]')
            ->put('/var/www/cust_neighbour_999/.env', 'APP_URL=https://neighbour.ir')
            ->put('/etc/nginx/sites-available/cust_neighbour_999', 'ssl_certificate /etc/letsencrypt/live/neighbour.ir/fullchain.pem;')
            ->put('/etc/letsencrypt/renewal/neighbour.ir.conf', "webroot_path = /var/www/cust_neighbour_999/public,\n");
        $this->server->databases = ['cust_db_' . $installation->id, 'cust_db_neighbour'];
        $this->server->dbUsers   = ['u_shop' . $installation->id, 'u_neighbour'];

        $id = $site->id;
        $this->objects
            ->put('backups', "tenants/site-{$id}/db/2026-11-19.sql.gz", 1000)
            ->put('backups', "customers/site-{$id}/baselines/initial.sql.gz", 500)
            ->put('backups', "saas-platform/archives/site_{$id}_20261101_101010.zip", 300)
            ->put('backups', "tenants/site-{$id}0/db/keep.sql.gz")              // site {id}0 — must survive
            ->put('backups', "saas-platform/archives/site_{$id}0_20261101.zip") // ditto
            ->put('backups', 'saas-platform/database/platform.sql.gz')          // the platform's own backup
            ->put('media', "sites/{$id}/media/logo.png", 50)
            ->put('media', "sites/{$id}/private/invoice.pdf", 70)
            ->put('media', "sites/{$id}0/media/keep.png");

        return [$site, $installation, $webRoot];
    }

    private function planHash(Site $site, bool $backups, bool $media): string
    {
        return PurgePlanBuilder::hash(app(PurgePlanBuilder::class)->forSite($site, $backups, $media));
    }

    private function submit(Site $site, array $overrides = [])
    {
        $backups = (bool) ($overrides['delete_backups'] ?? true);
        $media   = (bool) ($overrides['delete_media'] ?? true);

        return $this->post(route('admin.sites.purge.store', $site), array_merge([
            'confirm_domain'   => 'shop.example.ir',
            'current_password' => 'secret-pass',
            'acknowledge'      => '1',
            'delete_backups'   => $backups ? '1' : '0',
            'delete_media'     => $media ? '1' : '0',
            'plan_hash'        => $this->planHash($site, $backups, $media),
        ], $overrides));
    }

    private function runPipeline(): CustomerDeletion
    {
        $deletion = CustomerDeletion::latest('id')->firstOrFail();
        app(CustomerDeleteOrchestrator::class)->run($deletion);

        return $deletion->fresh();
    }

    private function log(): string
    {
        return implode("\n", $this->server->getCommandLog());
    }

    // ── authorization ────────────────────────────────────────────────────

    public function test_only_a_super_admin_can_open_or_submit_a_purge(): void
    {
        Queue::fake();
        [$site] = $this->installedSite();
        $this->actingAs($this->plainAdmin());

        $this->get(route('admin.sites.purge.show', $site))->assertForbidden();
        $this->submit($site)->assertForbidden();

        $this->assertSame(0, CustomerDeletion::count());
        Queue::assertNothingPushed();
    }

    public function test_site_list_offers_full_purge_only_to_super_admins(): void
    {
        [$site] = $this->installedSite();
        $purgeUrl = route('admin.sites.purge.show', $site);

        $this->actingAs($this->plainAdmin())->get(route('admin.sites.index'))
            ->assertOk()->assertSee('حذف از پنل')->assertDontSee($purgeUrl);

        $this->actingAs($this->superAdmin())->get(route('admin.sites.index'))
            ->assertOk()->assertSee($purgeUrl);
    }

    public function test_the_permission_row_exists_and_is_assigned_to_no_role(): void
    {
        $id = \DB::table('permissions')->where('slug', 'sites.purge')->value('id');

        $this->assertNotNull($id);
        $this->assertSame(0, \DB::table('role_has_permissions')->where('permission_id', $id)->count());
    }

    // ── dry-run ──────────────────────────────────────────────────────────

    public function test_preview_shows_every_target_with_live_object_counts(): void
    {
        [$site, $installation, $webRoot] = $this->installedSite();
        $this->actingAs($this->superAdmin());

        $response = $this->get(route('admin.sites.purge.show', ['site' => $site, 'delete_backups' => 1, 'delete_media' => 1]))->assertOk();

        $response->assertSee($webRoot)
            ->assertSee('/etc/nginx/sites-available/' . DeleteContext::resolveSlug($installation))
            ->assertSee('shop.example.ir')
            ->assertSee('old-shop.example.ir')
            ->assertSee('cust_db_' . $installation->id)
            ->assertSee("tenants/site-{$site->id}/")
            ->assertSee("sites/{$site->id}/")
            ->assertSee($this->planHash($site, true, true));
        $this->assertSame(0, CustomerDeletion::count(), 'the preview never starts anything');
    }

    // ── confirmations ────────────────────────────────────────────────────

    public function test_wrong_domain_wrong_password_missing_ack_or_stale_plan_queue_nothing(): void
    {
        Queue::fake();
        [$site] = $this->installedSite();
        $this->actingAs($this->superAdmin());

        $this->submit($site, ['confirm_domain' => 'shop.example.com'])->assertSessionHasErrors('confirm_domain');
        $this->submit($site, ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->submit($site, ['acknowledge' => null])->assertSessionHasErrors('acknowledge');

        // The site changed after the preview (a new alias domain): the shown plan is stale.
        $stale = $this->planHash($site, true, true);
        SiteDomain::create(['site_id' => $site->id, 'domain' => 'new.example.ir', 'role' => SiteDomain::ROLE_ALIAS, 'status' => SiteDomain::STATUS_REDIRECT]);
        $this->submit($site, ['plan_hash' => $stale])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, CustomerDeletion::count());
        Queue::assertNothingPushed();
    }

    // ── full purge ───────────────────────────────────────────────────────

    public function test_full_purge_removes_server_resources_backups_and_media_and_keeps_the_customer(): void
    {
        Queue::fake();
        [$site, $installation, $webRoot] = $this->installedSite();
        $slug = DeleteContext::resolveSlug($installation);
        $customerId = $site->customer_id;
        $id = $site->id;
        $this->actingAs($admin = $this->superAdmin());
        $expectedHash = $this->planHash($site, true, true);

        $response = $this->submit($site);

        $deletion = CustomerDeletion::firstOrFail();
        $response->assertRedirect(route('admin.infrastructure.customer-deployment.deletions.progress', $deletion));
        Queue::assertPushed(CustomerDeletionPipelineJob::class, fn ($job) => $job->deletionId === $deletion->id);
        $this->assertSame('delete_everything', $deletion->policy);
        $this->assertSame($expectedHash, $deletion->plan_sha256);
        $this->assertSame($webRoot, $deletion->plan['web_root']['path']);
        // assertEquals: MySQL JSON columns do not preserve key order.
        $this->assertEquals(['delete_backups' => true, 'delete_media' => true, 'source' => 'site_purge'], $deletion->options);
        $this->assertNotNull($deletion->requested_ip);
        $this->assertTrue(ActivityLog::where('action', 'site.purge_requested')->where('user_id', $admin->id)->exists());

        $deletion = $this->runPipeline();

        $this->assertSame('complete', $deletion->status, (string) $deletion->failure_reason);

        // Server: everything of this site is gone …
        $this->assertFalse($this->server->exists($webRoot));
        $this->assertFalse($this->server->exists("/etc/nginx/sites-available/{$slug}"));
        $this->assertFalse($this->server->exists("/etc/nginx/sites-enabled/{$slug}"));
        $this->assertFalse($this->server->exists('/etc/letsencrypt/renewal/shop.example.ir.conf'));
        $this->assertFalse($this->server->exists('/etc/letsencrypt/live/shop.example.ir'));
        $this->assertFalse($this->server->exists('/etc/letsencrypt/renewal/old-shop.example.ir.conf'));
        $this->assertFalse($this->server->exists("/etc/cron.d/{$slug}"));
        $this->assertFalse($this->server->exists("/etc/supervisor/conf.d/{$slug}_worker.conf"));
        $this->assertSame(['cust_db_neighbour'], $this->server->databases);
        $this->assertSame(['u_neighbour'], $this->server->dbUsers);
        // … and the neighbour is untouched.
        $this->assertTrue($this->server->exists('/var/www/cust_neighbour_999/.env'));
        $this->assertTrue($this->server->exists('/etc/nginx/sites-available/cust_neighbour_999'));
        $this->assertTrue($this->server->exists('/etc/letsencrypt/renewal/neighbour.ir.conf'));

        // Safe nginx sequence: test before, test after, then reload.
        $log = $this->log();
        $this->assertStringContainsString('rm -rf --one-file-system -- ' . escapeshellarg($webRoot), $log);
        $this->assertStringNotContainsString('rm -rf --one-file-system -- ' . escapeshellarg('/var/www') . ' ', $log);
        $firstTest = strpos($log, 'nginx -t');
        $reload    = strpos($log, 'systemctl reload nginx');
        $this->assertNotFalse($firstTest);
        $this->assertGreaterThan($firstTest, $reload);
        $this->assertSame(2, substr_count($log, 'nginx -t'));
        $this->assertStringNotContainsString('mysqldump', $log, 'no final backup when backups are deleted');

        // Arvan: this site's prefixes are empty, site {id}0 and the platform keep theirs.
        $this->assertSame([
            "tenants/site-{$id}0/db/keep.sql.gz",
            "saas-platform/archives/site_{$id}0_20261101.zip",
            'saas-platform/database/platform.sql.gz',
        ], $this->objects->keys('backups'));
        $this->assertSame(["sites/{$id}0/media/keep.png"], $this->objects->keys('media'));

        // Platform: site gone, customer kept, installation marked, audit trail kept.
        $this->assertNull(Site::find($site->id));
        $this->assertNotNull(Customer::find($customerId), 'owner decision: the customer record is always kept');
        $this->assertSame(0, SiteDomain::where('site_id', $id)->count());
        $this->assertSame('deleted', $installation->fresh()->status);
        $this->assertSame($id, $deletion->site_id, 'the deletion record outlives the site');
        $this->assertGreaterThan(0, $deletion->logs()->count());
    }

    public function test_without_the_backup_option_a_failed_final_backup_stops_before_anything_is_deleted(): void
    {
        Queue::fake();
        [$site, , $webRoot] = $this->installedSite();
        $this->actingAs($this->superAdmin());

        $this->submit($site, ['delete_backups' => '0', 'delete_media' => '0'])->assertRedirect();
        $deletion = $this->runPipeline();

        $this->assertSame('never_delete_backup', $deletion->policy);
        $this->assertSame('failed', $deletion->status);
        $this->assertStringContainsString('FINAL_BACKUP', (string) $deletion->failure_reason);
        $this->assertTrue($this->server->exists($webRoot));
        $this->assertNotNull(Site::find($site->id));
        $this->assertCount(6, $this->objects->keys('backups'));
        $this->assertCount(3, $this->objects->keys('media'));
    }

    public function test_a_failed_media_purge_keeps_the_site_and_retry_finishes_without_repeating_stages(): void
    {
        Queue::fake();
        [$site, , $webRoot] = $this->installedSite();
        $this->actingAs($this->superAdmin());
        $this->objects->failNext['media'] = 'Arvan unreachable';

        $this->submit($site)->assertRedirect();
        $deletion = $this->runPipeline();

        $this->assertSame('failed', $deletion->status);
        $this->assertStringContainsString('REMOVE_TENANT_MEDIA', (string) $deletion->failure_reason);
        $this->assertNotNull(Site::find($site->id), 'platform records stay until every stage succeeded');
        $this->assertFalse($this->server->exists($webRoot), 'independent resources were still removed');

        // The first job "finished" (it ran inline above): release its
        // ShouldBeUnique lock the way the queue worker does after a job.
        (new UniqueLock(app(CacheRepository::class)))->release(new CustomerDeletionPipelineJob($deletion->id));

        $this->post(route('admin.infrastructure.customer-deployment.deletions.retry', $deletion))->assertRedirect();
        Queue::assertPushed(CustomerDeletionPipelineJob::class, 2);
        $this->assertTrue(ActivityLog::where('action', 'customer.deletion_retried')->exists());

        $deletion = $this->runPipeline();

        $this->assertSame('complete', $deletion->status, (string) $deletion->failure_reason);
        $this->assertNull(Site::find($site->id));
        $this->assertSame(["sites/{$site->id}0/media/keep.png"], $this->objects->keys('media'));
        $log = $this->log();
        $this->assertSame(1, substr_count($log, 'rm -rf --one-file-system'), 'completed stages are not re-run');
        $this->assertSame(2, substr_count($log, ' delete --cert-name '), 'two lineages, each deleted once');
    }

    // ── guard outcomes ───────────────────────────────────────────────────

    public function test_an_unsafe_server_path_override_is_never_deleted_and_is_reported_as_manual(): void
    {
        Queue::fake();
        // Server-wide path override and no recorded web root: the only
        // "web root" the platform could compute is /var/www itself.
        [$site] = $this->installedSite(serverPath: '/var/www', recordWebRoot: false);
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.sites.purge.show', $site))->assertOk()->assertSee('اقدام دستی');

        $this->submit($site)->assertRedirect();
        $deletion = $this->runPipeline();

        $this->assertSame('complete_with_warnings', $deletion->status, (string) $deletion->failure_reason);
        $this->assertArrayHasKey('REMOVE_FILES', $deletion->manual_actions);
        $this->assertStringNotContainsString('rm -rf --one-file-system', $this->log());
        $this->assertTrue($this->server->exists('/var/www/cust_neighbour_999/.env'));
    }

    public function test_a_certificate_whose_webroot_is_another_site_is_left_alone(): void
    {
        Queue::fake();
        [$site] = $this->installedSite();
        $this->server->put('/etc/letsencrypt/renewal/old-shop.example.ir.conf', "webroot_path = /var/www/cust_neighbour_999/public,\n");
        $this->actingAs($this->superAdmin());

        $this->submit($site)->assertRedirect();
        $deletion = $this->runPipeline();

        $this->assertSame('complete_with_warnings', $deletion->status, (string) $deletion->failure_reason);
        $this->assertArrayHasKey('REMOVE_SSL', $deletion->manual_actions);
        $this->assertTrue($this->server->exists('/etc/letsencrypt/renewal/old-shop.example.ir.conf'));
        $this->assertFalse($this->server->exists('/etc/letsencrypt/renewal/shop.example.ir.conf'));
    }

    public function test_an_invalid_nginx_config_after_removal_restores_the_vhost_and_keeps_the_site(): void
    {
        Queue::fake();
        [$site, $installation] = $this->installedSite();
        $slug = DeleteContext::resolveSlug($installation);
        // Valid before the change, invalid once the vhost is moved away.
        $this->server->nginxValid = fn (FakePurgeServer $s) => $s->exists("/etc/nginx/sites-available/{$slug}");
        $this->actingAs($this->superAdmin());

        $this->submit($site)->assertRedirect();
        $deletion = $this->runPipeline();

        $this->assertSame('failed', $deletion->status);
        $this->assertStringContainsString('REMOVE_WEBSERVER', (string) $deletion->failure_reason);
        $this->assertTrue($this->server->exists("/etc/nginx/sites-available/{$slug}"), 'vhost moved back');
        $this->assertTrue($this->server->exists("/etc/nginx/sites-enabled/{$slug}"));
        $this->assertStringNotContainsString('systemctl reload nginx', $this->log());
        $this->assertNotNull(Site::find($site->id));
    }

    public function test_pull_backups_skip_a_site_that_is_being_purged(): void
    {
        Queue::fake();
        [$site] = $this->installedSite();
        $this->actingAs($this->superAdmin());
        $this->submit($site)->assertRedirect();

        $this->artisan('db:backup-sites', ['--dry-run' => true])
            ->doesntExpectOutputToContain("site {$site->id} (")
            ->assertSuccessful();
    }
}
