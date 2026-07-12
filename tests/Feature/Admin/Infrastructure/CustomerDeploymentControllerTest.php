<?php

namespace Tests\Feature\Admin\Infrastructure;

use App\Jobs\CustomerInstallationRollbackJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallRollback;
use App\Models\CustomerRemoteServer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 13-O8 — permission boundary + audit trail for manual installation
 * rollback (CustomerDeploymentController::rollback()), mirroring the
 * pattern SiteModuleUpgradeControllerTest already established for
 * `tenant.upgrade.execute` in Phase 13-M5.
 */
class CustomerDeploymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    /**
     * Attaches the `staff` role, seeded (via
     * 2026_07_12_100000_seed_tenant_rollback_execute_permission.php) with
     * the `tenant.rollback.execute` permission this controller now requires.
     */
    private function admin(): User
    {
        $user = User::create([
            'mobile' => '09131234567',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $staffRole = Role::where('slug', Role::STAFF)->first();
        if ($staffRole) {
            $user->roles()->attach($staffRole->id);
        }

        return $user;
    }

    /**
     * `is_admin=true` (passes the blanket ['auth','admin'] middleware) but
     * no role at all, so no `tenant.rollback.execute` permission — the
     * exact class of legacy user this phase narrows.
     */
    private function adminWithoutRollbackPermission(): User
    {
        return User::create([
            'mobile' => '09131234568',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    /**
     * super_admin, deliberately with no explicit role_has_permissions row:
     * AuthorizationService::can() must bypass via User::isSuperAdmin() alone.
     */
    private function superAdmin(): User
    {
        $user = User::create([
            'mobile' => '09131234570',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    /**
     * A `failed` installation with one pending rollback checkpoint — the
     * minimum state CustomerDeploymentController::rollback() requires to
     * get past its own business checks, so these tests isolate the
     * permission/audit-log behavior added by this phase.
     */
    private function makeRollbackableInstallation(): CustomerInstallation
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'failed',
            'failed_at'         => now(),
            'site_id'           => 77,
            'customer_company'  => 'Rollback Perm Co',
            'customer_email'    => 'rollbackperm@example.com',
            'customer_domain'   => 'rollbackperm.example.com',
            'request_payload'   => [
                'company' => 'Rollback Perm Co', 'owner_name' => 'Owner',
                'email' => 'rollbackperm@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'rollbackperm.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        CustomerInstallRollback::create([
            'installation_id' => $installation->id,
            'stage'           => 'UPLOAD_PACKAGE',
            'snapshot'        => ['remote_path' => '/tmp/pkg.zip'],
            'created_at'      => now(),
        ]);

        return $installation;
    }

    public function test_rollback_allowed_with_permission(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        $installation = $this->makeRollbackableInstallation();

        $response = $this->post(route('admin.infrastructure.customer-deployment.installations.rollback', $installation));

        $response->assertRedirect(route('admin.infrastructure.customer-deployment.installations.show', $installation));
        $response->assertSessionHas('success');
        Bus::assertDispatched(CustomerInstallationRollbackJob::class, fn ($job) => $job->installationId === $installation->id);
    }

    public function test_rollback_denied_without_permission(): void
    {
        Bus::fake();
        $this->actingAs($this->adminWithoutRollbackPermission());
        $installation = $this->makeRollbackableInstallation();

        $response = $this->post(route('admin.infrastructure.customer-deployment.installations.rollback', $installation));

        $response->assertForbidden();
        Bus::assertNotDispatched(CustomerInstallationRollbackJob::class);
        $this->assertSame('failed', $installation->fresh()->status);
    }

    public function test_rollback_allowed_for_super_admin_without_permission_row(): void
    {
        Bus::fake();
        $this->actingAs($this->superAdmin());
        $installation = $this->makeRollbackableInstallation();

        $response = $this->post(route('admin.infrastructure.customer-deployment.installations.rollback', $installation));

        $response->assertSessionHas('success');
        Bus::assertDispatched(CustomerInstallationRollbackJob::class, fn ($job) => $job->installationId === $installation->id);
    }

    public function test_rollback_creates_activity_log(): void
    {
        Bus::fake();
        $admin = $this->admin();
        $this->actingAs($admin);
        $installation = $this->makeRollbackableInstallation();

        $this->post(route('admin.infrastructure.customer-deployment.installations.rollback', $installation), [
            'reason' => 'Customer requested manual rollback',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action'       => 'installation.rollback_requested',
            'subject_type' => CustomerInstallation::class,
            'subject_id'   => $installation->id,
            'user_id'      => $admin->id,
        ]);

        $log = \App\Models\ActivityLog::where('action', 'installation.rollback_requested')
            ->where('subject_id', $installation->id)
            ->firstOrFail();

        $this->assertSame($installation->id, $log->properties['installation_id']);
        $this->assertSame($installation->site_id, $log->properties['site_id']);
        $this->assertSame($admin->id, $log->properties['triggered_by']);
        $this->assertSame('Customer requested manual rollback', $log->properties['reason']);
    }
}
