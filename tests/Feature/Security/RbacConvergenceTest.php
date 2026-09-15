<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\CheckTenantMaintenance;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * AUTH-03 (Task 2C RBAC convergence) — regression coverage for the 4 call
 * sites that were checking the raw `is_admin` flag directly instead of the
 * canonical AuthorizationService/Role authority. A user granted admin
 * access ONLY through the roles system (is_admin=false, a real
 * super_admin/staff role row) must be treated identically to an
 * is_admin=true user at every one of these sites — previously it was not.
 */
class RbacConvergenceTest extends TestCase
{
    use RefreshDatabase;

    /** is_admin=false, authorized purely via the SUPER_ADMIN role row. */
    private function roleOnlySuperAdmin(string $mobile = '09150000001'): User
    {
        $user = User::create([
            'mobile' => $mobile,
            'password' => Hash::make('password'),
            'is_admin' => false,
            'is_active' => true,
        ]);
        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        $this->assertNotNull($role, 'SUPER_ADMIN role must be seeded for this regression suite to be meaningful.');
        $user->roles()->attach($role->id);

        return $user;
    }

    /** Plain authenticated user: no is_admin flag, no role at all. */
    private function ordinaryUser(string $mobile = '09150000002'): User
    {
        return User::create([
            'mobile' => $mobile,
            'password' => Hash::make('password'),
            'is_admin' => false,
            'is_active' => true,
        ]);
    }

    // ── 1. PlatformInstallerController::unlock() ───────────────────────────

    public function test_installer_unlock_accepts_a_role_only_super_admin(): void
    {
        $lockPath = storage_path('install.lock');
        file_put_contents($lockPath, json_encode(['locked_at' => now()->toIso8601String()]));

        try {
            $admin = $this->roleOnlySuperAdmin();
            $response = $this->actingAs($admin)->post(route('installer.unlock'), ['confirm' => 'UNLOCK-INSTALLER']);

            $response->assertRedirect(route('installer.index'));
            $this->assertFileDoesNotExist($lockPath, 'A role-only Super Admin must be able to unlock the installer.');
        } finally {
            @unlink($lockPath);
        }
    }

    public function test_installer_unlock_rejects_a_plain_authenticated_user(): void
    {
        $lockPath = storage_path('install.lock');
        file_put_contents($lockPath, json_encode(['locked_at' => now()->toIso8601String()]));

        try {
            $user = $this->ordinaryUser();
            $response = $this->actingAs($user)->post(route('installer.unlock'), ['confirm' => 'UNLOCK-INSTALLER']);

            $response->assertForbidden();
            $this->assertFileExists($lockPath, 'A non-admin must never be able to unlock the installer.');
        } finally {
            @unlink($lockPath);
        }
    }

    // ── 2. LoginController — post-login redirect ────────────────────────────

    public function test_login_redirects_a_role_only_super_admin_to_the_admin_dashboard(): void
    {
        $admin = $this->roleOnlySuperAdmin('09150000003');

        $response = $this->post(route('login'), [
            'mobile' => $admin->mobile,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_redirects_an_ordinary_user_to_the_customer_dashboard(): void
    {
        $user = $this->ordinaryUser('09150000004');

        $response = $this->post(route('login'), [
            'mobile' => $user->mobile,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    // ── 3. ModuleLabelController — admin vs customer keyset ────────────────

    public function test_module_labels_index_shows_the_admin_keyset_for_a_role_only_super_admin(): void
    {
        $admin = $this->roleOnlySuperAdmin('09150000005');

        $response = $this->actingAs($admin)->get(route('admin.module-labels.index'));

        $response->assertOk();
        $response->assertViewHas('pageTitle', 'عنوان و جزئیات ماژول‌ها (پنل ادمین)');
    }

    // ── 4. CheckTenantMaintenance — admin bypass authority ──────────────────

    public function test_tenant_maintenance_bypass_uses_the_canonical_authorization_service_not_the_raw_flag(): void
    {
        $maintenance = $this->createMock(\SaaSPlatform\CoreUtils\Services\Maintenance\TenantMaintenanceManager::class);
        $maintenance->method('isUnderMaintenance')->willReturn(true);
        $maintenance->method('getMaintenanceRecord')->willReturn((object) [
            'allow_admin_bypass' => true,
            'message' => 'در حال تعمیر',
        ]);

        $tenantContext = $this->createMock(\SaaSPlatform\CoreUtils\Contracts\TenantContextContract::class);
        $tenantContext->method('hasTenant')->willReturn(true);
        $tenantContext->method('isSite')->willReturn(true);
        $tenantContext->method('id')->willReturn(1);

        $middleware = new CheckTenantMaintenance($maintenance, $tenantContext, app(AuthorizationService::class));

        // Role-only Super Admin bypasses maintenance.
        $this->actingAs($this->roleOnlySuperAdmin('09150000006'));
        $passed = false;
        $middleware->handle(request(), function () use (&$passed) {
            $passed = true;
            return response('ok');
        });
        $this->assertTrue($passed, 'A role-only Super Admin must bypass maintenance mode, same as an is_admin=true user.');

        // Plain user does not bypass — sees the 503 maintenance response.
        $this->actingAs($this->ordinaryUser('09150000007'));
        $response = $middleware->handle(request(), function () {
            return response('should not reach here');
        });
        $this->assertSame(503, $response->getStatusCode());
    }
}
