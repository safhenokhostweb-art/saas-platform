<?php

namespace Tests\Feature\DevOps;

use App\Models\Role;
use App\Models\SiteResourceLimit;
use App\Models\TenantPruneAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\FakesTenantHost;
use Tests\TestCase;

class DevOpsResourceControllerTest extends TestCase
{
    use RefreshDatabase;
    use FakesTenantHost;

    private const ROOT = '/var/www/cust_acme_12';

    private function user(array $attrs = [], ?string $role = null): User
    {
        $user = User::create(array_merge([
            'mobile'    => '0912' . random_int(1000000, 9999999),
            'password'  => Hash::make('password'),
            'is_admin'  => false,
            'is_active' => true,
        ], $attrs));

        if ($role) {
            $roleId = Role::where('slug', $role)->value('id')
                ?? Role::create(['name' => $role, 'slug' => $role, 'guard_name' => 'web'])->id;
            $user->roles()->attach($roleId);
        }

        return $user;
    }

    private function superAdmin(): User
    {
        return $this->user(['is_admin' => true], Role::SUPER_ADMIN);
    }

    /** Legacy admin: passes the admin middleware, has no prune permission. */
    private function legacyAdmin(): User
    {
        return $this->user(['is_admin' => true]);
    }

    private function hostWithOldLog(): array
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/logs' => ['old.log' => [4096, 40 * 86400]]]);
        $driver->addResponse('du -skx', 0, "1024\n/dev/vda1 1000000 500000 500000 50% /\n");
        $this->bindTenantTarget($site, $driver, self::ROOT);

        return [$site, $driver];
    }

    // ─── Access control ──────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.devops.resources.index'))->assertRedirect();
        $this->getJson(route('admin.devops.resources.metrics'))->assertStatus(401);
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $site = $this->makeDevOpsSite();
        $this->actingAs($this->user());

        $this->get(route('admin.devops.resources.index'))->assertForbidden();
        $this->getJson(route('admin.devops.resources.metrics'))->assertForbidden();
        $this->postJson(route('admin.devops.resources.preview', $site))->assertForbidden();
    }

    public function test_legacy_admin_can_view_but_not_prune(): void
    {
        [$site, $driver] = $this->hostWithOldLog();
        $this->actingAs($this->legacyAdmin());

        $this->get(route('admin.devops.resources.index'))
            ->assertOk()
            ->assertSee('پایش و پاک‌سازی دیسک سایت‌ها')
            ->assertSee('ماتریس مصرف سایت‌ها');

        $this->postJson(route('admin.devops.resources.preview', $site))->assertForbidden();
        $this->postJson(route('admin.devops.resources.prune', $site), ['confirmation_token' => str_repeat('a', 48)])->assertForbidden();
        $this->assertSame([], $driver->getCommandLog());
        $this->assertSame(0, TenantPruneAuditLog::count());
    }

    public function test_prune_is_super_admin_only_and_not_grantable(): void
    {
        [$site, $driver] = $this->hostWithOldLog();
        $staff = $this->user([], Role::STAFF);
        $this->actingAs($staff);

        $this->postJson(route('admin.devops.resources.preview', $site))->assertForbidden();

        // Even a role carrying a hand-made permission row with the old slug gets nothing.
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'test', 'slug' => 'devops.storage.prune', 'module_slug' => 'sites', 'guard_name' => 'web',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $staff->roles()->first()->id]);

        $this->postJson(route('admin.devops.resources.preview', $site))->assertForbidden();
        $this->assertSame([], $driver->getCommandLog());

        $this->actingAs($this->superAdmin());
        $this->postJson(route('admin.devops.resources.preview', $site))->assertOk();
    }

    public function test_dashboard_hides_prune_action_from_non_super_admin(): void
    {
        $this->makeDevOpsSite();

        $this->actingAs($this->legacyAdmin());
        $this->get(route('admin.devops.resources.index'))->assertOk()->assertViewHas('canPrune', false);

        $this->actingAs($this->superAdmin());
        $this->get(route('admin.devops.resources.index'))->assertOk()->assertViewHas('canPrune', true);
    }

    // ─── JSON integrity ──────────────────────────────────────────────────────

    public function test_metrics_json_has_complete_structure(): void
    {
        $site = $this->makeDevOpsSite('فروشگاه نمونه');
        $this->actingAs($this->legacyAdmin());

        $response = $this->getJson(route('admin.devops.resources.metrics'))
            ->assertOk()
            ->assertJsonStructure([
                'server' => [
                    'disk'   => ['status', 'total_bytes', 'free_bytes', 'used_bytes', 'used_percent', 'total_human', 'percent_human'],
                    'memory' => ['status', 'total_bytes', 'used_bytes', 'used_percent', 'percent_human'],
                    'cpu'    => ['status', 'cores', 'load_1m', 'percent', 'percent_human', 'load_human'],
                    'collected_at',
                ],
                'pool' => ['sites_total', 'allocated_bytes', 'consumed_bytes', 'physical_bytes', 'overcommit_ratio',
                           'overcommit_human', 'levels' => ['normal', 'warning', 'critical', 'unknown'], 'levels_human'],
                'tenants' => [['site_id', 'title', 'domain', 'disk_usage_bytes', 'soft_limit_bytes', 'percent',
                               'danger_level', 'danger_label', 'percent_human', 'soft_limit_human', 'audited_human', 'last_prune']],
                'thresholds' => ['warning_percent', 'critical_percent'],
                'auto_prune_enabled',
                'generated_at',
            ]);

        $row = collect($response->json('tenants'))->firstWhere('site_id', $site->id);
        $this->assertSame('unknown', $row['danger_level']);
        $this->assertSame('۵ گیگابایت', $row['soft_limit_human']);
        $this->assertSame(['warning_percent' => 80, 'critical_percent' => 90], $response->json('thresholds'));
        $this->assertFalse($response->json('auto_prune_enabled'));
    }

    public function test_metrics_search_filters_and_is_validated(): void
    {
        $this->makeDevOpsSite('فروشگاه آلفا');
        $this->makeDevOpsSite('کلینیک بتا');
        $this->actingAs($this->legacyAdmin());

        $titles = collect($this->getJson(route('admin.devops.resources.metrics', ['q' => 'آلفا']))->assertOk()->json('tenants'))->pluck('title');
        $this->assertSame(['فروشگاه آلفا'], $titles->all());

        $this->getJson(route('admin.devops.resources.metrics', ['q' => str_repeat('x', 101)]))->assertStatus(422);
    }

    public function test_show_returns_tenant_and_history_and_404s_unknown_site(): void
    {
        $site = $this->makeDevOpsSite();
        TenantPruneAuditLog::create(['site_id' => $site->id, 'triggered_by' => 'auto_agent', 'mode' => 'dry_run', 'status' => 'completed', 'reclaimed_bytes' => 2048]);
        $this->actingAs($this->legacyAdmin());

        $this->getJson(route('admin.devops.resources.show', $site))
            ->assertOk()
            ->assertJsonPath('tenant.site_id', $site->id)
            ->assertJsonPath('history.0.mode', 'dry_run')
            ->assertJsonPath('history.0.reclaimed_human', '۲ کیلوبایت');

        $this->getJson(route('admin.devops.resources.show', 999999))->assertNotFound();
    }

    // ─── Preview → token → prune ─────────────────────────────────────────────

    public function test_prune_requires_a_preview_token_and_token_is_single_use(): void
    {
        [$site, $driver] = $this->hostWithOldLog();
        $this->actingAs($this->superAdmin());

        $this->postJson(route('admin.devops.resources.prune', $site), [])->assertStatus(422);
        $this->postJson(route('admin.devops.resources.prune', $site), ['confirmation_token' => str_repeat('a', 48)])->assertStatus(422);
        $this->assertSame([], $this->rmCommands($driver));

        $preview = $this->postJson(route('admin.devops.resources.preview', $site), ['profile' => 'routine'])
            ->assertOk()
            ->assertJsonPath('audit.mode', 'dry_run')
            ->assertJsonPath('audit.pruned_items_count', 1)
            ->assertJsonPath('audit.categories.0.key', 'logs');
        $token = $preview->json('confirmation_token');
        $this->assertSame(48, strlen($token));
        $this->assertSame([], $this->rmCommands($driver), 'preview must not delete');

        $this->postJson(route('admin.devops.resources.prune', $site), ['confirmation_token' => $token])
            ->assertOk()
            ->assertJsonPath('audit.mode', 'execute')
            ->assertJsonPath('audit.status', 'completed')
            ->assertJsonPath('audit.reclaimed_bytes', 4096)
            ->assertJsonPath('preview_audit_id', $preview->json('audit.id'));
        $this->assertCount(1, $this->rmCommands($driver));

        $this->postJson(route('admin.devops.resources.prune', $site), ['confirmation_token' => $token])->assertStatus(422);
        $this->assertCount(1, $this->rmCommands($driver));

        $this->assertSame(2, TenantPruneAuditLog::where('site_id', $site->id)->where('triggered_by', 'admin_user')->count());
    }

    public function test_token_is_bound_to_site_and_user(): void
    {
        [$site, $driver] = $this->hostWithOldLog();
        $other = $this->makeDevOpsSite('سایت دیگر');
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $token = $this->postJson(route('admin.devops.resources.preview', $site))->json('confirmation_token');
        $this->postJson(route('admin.devops.resources.prune', $other), ['confirmation_token' => $token])->assertStatus(422);

        $token = $this->postJson(route('admin.devops.resources.preview', $site))->json('confirmation_token');
        $this->actingAs($this->superAdmin());
        $this->postJson(route('admin.devops.resources.prune', $site), ['confirmation_token' => $token])->assertStatus(422);

        $this->assertSame([], $this->rmCommands($driver));
    }

    public function test_preview_rejects_unknown_profile_and_reports_unreachable_host(): void
    {
        $site = $this->makeDevOpsSite();
        $this->actingAs($this->superAdmin());

        $this->postJson(route('admin.devops.resources.preview', $site), ['profile' => 'rm-rf'])->assertStatus(422);

        // No binding: the real resolver finds no completed installation.
        $this->postJson(route('admin.devops.resources.preview', $site))
            ->assertStatus(422)
            ->assertJsonPath('audit.status', 'failed')
            ->assertJsonMissingPath('confirmation_token');
    }

    // ─── Quota ───────────────────────────────────────────────────────────────

    public function test_quota_update_validates_and_persists(): void
    {
        $site = $this->makeDevOpsSite();
        $this->actingAs($this->legacyAdmin());

        $this->putJson(route('admin.devops.resources.quota', $site), ['disk_soft_limit_gb' => 5, 'disk_hard_limit_gb' => 4])
            ->assertStatus(422)->assertJsonValidationErrors('disk_hard_limit_gb');
        $this->putJson(route('admin.devops.resources.quota', $site), ['disk_soft_limit_gb' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('disk_soft_limit_gb');
        $this->putJson(route('admin.devops.resources.quota', $site), ['disk_soft_limit_gb' => 5, 'memory_limit_mb' => 'lots'])
            ->assertStatus(422)->assertJsonValidationErrors('memory_limit_mb');

        $this->putJson(route('admin.devops.resources.quota', $site), [
            'disk_soft_limit_gb' => 7.5, 'disk_hard_limit_gb' => 9, 'cpu_cores_limit' => 1.5, 'memory_limit_mb' => 512,
        ])->assertOk()->assertJsonPath('tenant.soft_limit_bytes', (int) (7.5 * 1073741824));

        $limit = SiteResourceLimit::where('site_id', $site->id)->sole();
        $this->assertSame(9 * 1073741824, (int) $limit->disk_hard_limit_bytes);
        $this->assertSame(512 * 1048576, (int) $limit->memory_limit_bytes);
        $this->assertEquals(1.5, $limit->cpu_cores_limit);
    }
}
