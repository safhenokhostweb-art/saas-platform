<?php

namespace Tests\Feature\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationPackage;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\RemoteConnectionService;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeServer(): CustomerRemoteServer
    {
        return CustomerRemoteServer::create([
            'label'       => 'Test Server',
            'type'        => 'fake',
            'host'        => 'localhost',
            'port'        => 22,
            'username'    => 'deploy',
            'auth_method' => 'key',
        ]);
    }

    private function makePackage(): CustomerInstallationPackage
    {
        return CustomerInstallationPackage::create([
            'version'     => '1.0.0',
            'arvan_key'   => 'saas-platform/releases/customer-site/v1.0.0/customer-site-v1.0.0.zip',
            'sha256'      => str_repeat('a', 64),
            'size_bytes'  => 1024 * 1024 * 10,
            'released_at' => now(),
            'is_active'   => true,
        ]);
    }

    private function makeInstallation(CustomerRemoteServer $server): CustomerInstallation
    {
        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'pending',
            'customer_company'  => 'Test Co',
            'customer_email'    => 'test@example.com',
            'customer_domain'   => 'test.example.com',
            'request_payload'   => [
                'company'          => 'Test Co',
                'owner_name'       => 'Owner',
                'email'            => 'test@example.com',
                'phone'            => '09123456789',
                'language'         => 'fa',
                'timezone'         => 'Asia/Tehran',
                'currency'         => 'IRR',
                'domain'           => 'test.example.com',
                'license_type'     => 'basic',
                'theme_slug'       => '',
                'modules'          => [],
                'backup_policy'    => 'daily',
                'smtp'             => [],
                'ssl_enabled'      => false,
                'remote_server_id' => $server->id,
                'install_mode'     => 'MODE_FRESH',
            ],
        ]);
    }

    public function test_installation_record_created_with_correct_fields(): void
    {
        $server = $this->makeServer();
        $installation = $this->makeInstallation($server);

        $this->assertDatabaseHas('customer_installations', [
            'id'               => $installation->id,
            'status'           => 'pending',
            'customer_company' => 'Test Co',
            'customer_domain'  => 'test.example.com',
        ]);
    }

    /**
     * Phase 15-B1 — count raised 22 -> 23: IssueSslStage added between
     * CONFIGURE_WEB_SERVER and PROVISION_LICENSE.
     * Phase 15-D — count raised 23 -> 24: ProvisionDomainStage added between
     * VALIDATE_PERMISSIONS and UPLOAD_PACKAGE.
     * ADR-088 — count raised 24 -> 26: APPLY_SITE_ENTITLEMENTS after
     * PROVISION_LICENSE, CLEAR_APPLICATION_CACHE before CUSTOMER_READY.
     */
    public function test_orchestrator_builds_26_stages_for_fresh_mode(): void
    {
        $orchestrator = app(CustomerInstallationOrchestrator::class);
        $stages = $orchestrator->buildStageList('MODE_FRESH');

        $this->assertCount(26, $stages);

        $names = array_map(fn($s) => $s->name(), $stages);
        $this->assertContains('VALIDATE_SERVER', $names);
        $this->assertContains('PROVISION_DOMAIN', $names);
        $this->assertContains('UPLOAD_PACKAGE', $names);
        $this->assertContains('CREATE_DATABASE', $names);
        $this->assertContains('ISSUE_SSL', $names);
        $this->assertContains('CUSTOMER_READY', $names);

        $domainPos = array_search('PROVISION_DOMAIN', $names, true);
        $uploadPos = array_search('UPLOAD_PACKAGE', $names, true);
        $this->assertLessThan($uploadPos, $domainPos, 'PROVISION_DOMAIN must resolve the domain/document-root before any file is uploaded');
    }

    public function test_orchestrator_skips_db_stages_for_restore_mode(): void
    {
        $orchestrator = app(CustomerInstallationOrchestrator::class);
        $stages = $orchestrator->buildStageList('MODE_RESTORE');

        $names = array_map(fn($s) => $s->name(), $stages);

        $this->assertNotContains('CREATE_DATABASE', $names);
        $this->assertNotContains('RUN_MIGRATIONS', $names);
        $this->assertNotContains('RUN_SEEDER', $names);
        $this->assertContains('UPLOAD_PACKAGE', $names);
        $this->assertContains('CUSTOMER_READY', $names);
    }

    /**
     * Phase 13-I — MODE_MODULE_UPDATE stage list.
     */
    public function test_orchestrator_builds_module_update_stage_list(): void
    {
        $target = new \App\Services\CustomerInstallation\Dto\ModuleUpdateTarget('forms', 1, '1.1.0');
        $orchestrator = app(CustomerInstallationOrchestrator::class);
        $stages = $orchestrator->buildStageList('MODE_MODULE_UPDATE', $target);

        $names = array_map(fn($s) => $s->name(), $stages);

        $this->assertSame([
            'VALIDATE_SERVER',
            'VALIDATE_PHP',
            'VALIDATE_EXTENSIONS',
            'VALIDATE_DISK',
            'VALIDATE_PERMISSIONS',
            'LOCK_TENANT',
            'BACKUP_MODULE_UPGRADE',
            'UPLOAD_PACKAGE',
            'VERIFY_PACKAGE',
            'EXTRACT_MODULE_PACKAGE',
            'RUN_MIGRATIONS',
            'COMPLETE_MODULE_UPGRADE',
            'UNLOCK_TENANT',
        ], $names);
    }

    /**
     * Phase 13-J — lock happens right before the mutating stages, unlock
     * is the very last stage (after the health check), so a failed
     * upgrade never unlocks a broken site.
     */
    public function test_lock_precedes_mutation_and_unlock_is_last(): void
    {
        $target = new \App\Services\CustomerInstallation\Dto\ModuleUpdateTarget('forms', 1, '1.1.0');
        $orchestrator = app(CustomerInstallationOrchestrator::class);
        $names = array_map(fn ($s) => $s->name(), $orchestrator->buildStageList('MODE_MODULE_UPDATE', $target));

        $lockPos = array_search('LOCK_TENANT', $names, true);
        $unlockPos = array_search('UNLOCK_TENANT', $names, true);
        $uploadPos = array_search('UPLOAD_PACKAGE', $names, true);
        $completePos = array_search('COMPLETE_MODULE_UPGRADE', $names, true);

        $this->assertLessThan($uploadPos, $lockPos, 'LOCK_TENANT must precede UPLOAD_PACKAGE');
        $this->assertSame(count($names) - 1, $unlockPos, 'UNLOCK_TENANT must be the last stage');
        $this->assertGreaterThan($completePos, $unlockPos, 'UNLOCK_TENANT must come after the health check');
    }

    /**
     * Phase 13-M3a — LOCK_TENANT must precede BACKUP_MODULE_UPGRADE in the
     * forward stage list. Checkpoints are recorded in forward execution
     * order and replayed LIFO on rollback, so this forward ordering is what
     * guarantees BACKUP_MODULE_UPGRADE.rollback() (the database restore)
     * runs before LOCK_TENANT.rollback() (the unlock) during a failed
     * upgrade — never the other way around.
     */
    public function test_lock_precedes_backup(): void
    {
        $target = new \App\Services\CustomerInstallation\Dto\ModuleUpdateTarget('forms', 1, '1.1.0');
        $orchestrator = app(CustomerInstallationOrchestrator::class);
        $names = array_map(fn ($s) => $s->name(), $orchestrator->buildStageList('MODE_MODULE_UPDATE', $target));

        $lockPos = array_search('LOCK_TENANT', $names, true);
        $backupPos = array_search('BACKUP_MODULE_UPGRADE', $names, true);

        $this->assertLessThan($backupPos, $lockPos, 'LOCK_TENANT must precede BACKUP_MODULE_UPGRADE so rollback restores the database before unlocking the site');
    }

    public function test_module_update_stages_exclude_fresh_install_only_stages(): void
    {
        $target = new \App\Services\CustomerInstallation\Dto\ModuleUpdateTarget('forms', 1, '1.1.0');
        $orchestrator = app(CustomerInstallationOrchestrator::class);
        $stages = $orchestrator->buildStageList('MODE_MODULE_UPDATE', $target);

        $names = array_map(fn($s) => $s->name(), $stages);

        foreach (['CREATE_DATABASE', 'RUN_SEEDER', 'CONFIGURE_DNS', 'CONFIGURE_WEB_SERVER', 'ISSUE_SSL',
                  'PROVISION_DOMAIN', 'PROVISION_LICENSE', 'INSTALL_THEME', 'CUSTOMER_READY', 'EXTRACT_PACKAGE',
                  'INITIALIZE_BACKUP'] as $excluded) {
            $this->assertNotContains($excluded, $names, "{$excluded} should not appear in MODE_MODULE_UPDATE");
        }
    }

    public function test_module_update_upload_stage_resolves_module_scoped_package_service(): void
    {
        $target = new \App\Services\CustomerInstallation\Dto\ModuleUpdateTarget('forms', 1, '1.1.0');
        $orchestrator = app(CustomerInstallationOrchestrator::class);
        $orchestrator->buildStageList('MODE_MODULE_UPDATE', $target);

        // The contextual binding set for UploadPackageStage must resolve to the
        // module-scoped service, not the global CustomerReleasePackageService.
        $resolved = app()->make(\App\Services\CustomerInstallation\Stages\UploadPackageStage::class);
        $prop = new \ReflectionProperty($resolved, 'packages');
        $prop->setAccessible(true);

        $this->assertInstanceOf(
            \App\Services\CustomerInstallation\ModuleReleasePackageService::class,
            $prop->getValue($resolved)
        );
    }

    public function test_installation_state_transitions(): void
    {
        $server = $this->makeServer();
        $installation = $this->makeInstallation($server);

        $this->assertEquals('pending', $installation->status);
        $this->assertFalse($installation->isComplete());
        $this->assertTrue($installation->isRunning());

        $installation->update(['status' => 'complete', 'completed_at' => now()]);
        $installation->refresh();

        $this->assertTrue($installation->isComplete());
        $this->assertFalse($installation->isRunning());
    }

    public function test_completed_stages_tracking(): void
    {
        $server = $this->makeServer();
        $installation = $this->makeInstallation($server);

        $this->assertEmpty($installation->completedStagesList());
        $this->assertFalse($installation->hasCompletedStage('VALIDATE_SERVER'));

        $installation->markStageComplete('VALIDATE_SERVER');
        $installation->refresh();

        $this->assertTrue($installation->hasCompletedStage('VALIDATE_SERVER'));
        $this->assertFalse($installation->hasCompletedStage('VALIDATE_PHP'));
    }

    public function test_resume_skips_completed_stages(): void
    {
        $server = $this->makeServer();
        $installation = $this->makeInstallation($server);
        $installation->markStageComplete('VALIDATE_SERVER');
        $installation->markStageComplete('VALIDATE_PHP');

        $orchestrator = app(CustomerInstallationOrchestrator::class);
        $stages = $orchestrator->buildStageList('MODE_FRESH');

        $skipped = array_filter($stages, fn($s) => $installation->hasCompletedStage($s->name()));
        $this->assertCount(2, $skipped);
    }

    public function test_validate_disk_stage_fails_on_insufficient_space(): void
    {
        $stage = app(\App\Services\CustomerInstallation\Stages\ValidateDiskStage::class);
        $driver = new FakeRemoteDriver();
        $driver->setDiskFree('default', 100); // 100 bytes — well below minimum

        $server = $this->makeServer();
        $installation = $this->makeInstallation($server);

        $request = \App\Services\CustomerInstallation\Dto\CustomerInstallRequest::fromArray($installation->request_payload);
        $ctx = new \App\Services\CustomerInstallation\Dto\InstallationContext($installation, $request, $driver);

        $result = $stage->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient', $result->error);
    }

    public function test_validate_extensions_fails_on_missing_extension(): void
    {
        $stage = app(\App\Services\CustomerInstallation\Stages\ValidateExtensionsStage::class);
        $driver = new FakeRemoteDriver();
        $driver->addResponse('php -m', 0, "Core\ndate\njson\n");

        $server = $this->makeServer();
        $installation = $this->makeInstallation($server);

        $request = \App\Services\CustomerInstallation\Dto\CustomerInstallRequest::fromArray($installation->request_payload);
        $ctx = new \App\Services\CustomerInstallation\Dto\InstallationContext($installation, $request, $driver);

        $result = $stage->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Missing', $result->error);
    }

    public function test_verify_package_fails_on_sha256_mismatch(): void
    {
        $stage = app(\App\Services\CustomerInstallation\Stages\VerifyPackageStage::class);
        $driver = new FakeRemoteDriver();
        $driver->addResponse('sha256sum', 0, str_repeat('b', 64) . '  /tmp/file.zip');
        $driver->putFile('/tmp/file.zip');

        $server = $this->makeServer();
        $installation = $this->makeInstallation($server);
        $installation->update(['package_sha256' => str_repeat('a', 64)]);

        $request = \App\Services\CustomerInstallation\Dto\CustomerInstallRequest::fromArray($installation->request_payload);
        $ctx = new \App\Services\CustomerInstallation\Dto\InstallationContext($installation, $request, $driver);
        $ctx->recordOutput('UPLOAD_PACKAGE', [
            'remote_path' => '/tmp/file.zip',
            'sha256'      => str_repeat('a', 64),
        ]);

        $result = $stage->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('mismatch', $result->error);
    }
}
