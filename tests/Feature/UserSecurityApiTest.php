<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Tag;
use App\Models\PasswordReset;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature Tests برای APIهای امنیت کاربران، گروه‌ها و برچسب‌ها
 *
 * رعایت قانون ۴: احراز هویت، Rate Limiting
 * رعایت قانون ۶: فرمت پاسخ یکسان
 */
class UserSecurityApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $user;
    private UserGroup $group;
    private Tag $tag;
    private string $adminToken;
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        // ایجاد ادمین و کاربر عادی
        $this->admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'mobile' => '09120000001',
        ]);

        $this->user = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
            'mobile' => '09120000002',
        ]);

        // ایجاد گروه و برچسب
        $this->group = UserGroup::factory()->create([
            'name' => 'Test Group',
            'slug' => 'test-group',
        ]);

        $this->tag = Tag::factory()->create([
            'name' => 'Test Tag',
            'slug' => 'test-tag',
        ]);

        // دریافت توکن
        $this->adminToken = $this->admin->createToken('test-admin')->plainTextToken;
        $this->userToken = $this->user->createToken('test-user')->plainTextToken;
    }

    // =========================================================================
    // ======================= 2FA Tests ========================================
    // =========================================================================

    /** @test */
    public function user_can_enable_two_factor()
    {
        $response = $this->withToken($this->userToken)
            ->postJson("/api/users/{$this->user->id}/2fa/enable", [
                'secret' => 'JBSWY3DPEHPK3PXP',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '2FA با موفقیت فعال شد. لطفاً با کد تأیید، فعال‌سازی را نهایی کنید.',
            ]);

        $this->assertTrue($this->user->fresh()->two_factor_enabled);
    }

    /** @test */
    public function user_cannot_enable_two_factor_without_secret()
    {
        $response = $this->withToken($this->userToken)
            ->postJson("/api/users/{$this->user->id}/2fa/enable", []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
            ]);
    }

    /** @test */
    public function user_can_confirm_two_factor()
    {
        // ابتدا فعال‌سازی
        $this->user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $response = $this->withToken($this->userToken)
            ->postJson("/api/users/{$this->user->id}/2fa/confirm", [
                'code' => '000000',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '2FA با موفقیت تأیید و فعال شد.',
            ]);
    }

    /** @test */
    public function user_can_disable_two_factor()
    {
        // ابتدا فعال و تأیید
        $this->user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withToken($this->userToken)
            ->postJson("/api/users/{$this->user->id}/2fa/disable");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '2FA با موفقیت غیرفعال شد.',
            ]);

        $this->assertFalse($this->user->fresh()->two_factor_enabled);
    }

    // =========================================================================
    // ======================= Lock Tests =======================================
    // =========================================================================

    /** @test */
    public function user_can_view_lock_status()
    {
        $response = $this->withToken($this->userToken)
            ->getJson("/api/users/{$this->user->id}/lock-status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_locked' => false,
            ]);
    }

    /** @test */
    public function admin_can_unlock_user()
    {
        // قفل کردن کاربر
        $this->user->update([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(30),
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson("/api/users/{$this->user->id}/unlock");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'حساب کاربر با موفقیت از قفل خارج شد.',
            ]);

        $this->assertNull($this->user->fresh()->locked_until);
        $this->assertEquals(0, $this->user->fresh()->failed_login_attempts);
    }

    /** @test */
    public function normal_user_cannot_unlock_others()
    {
        $this->user->update([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(30),
        ]);

        $response = $this->withToken($this->userToken)
            ->postJson("/api/users/{$this->user->id}/unlock");

        $response->assertStatus(403);
    }

    // =========================================================================
    // ======================= Password Reset Tests =============================
    // =========================================================================

    /** @test */
    public function user_can_request_password_reset_code()
    {
        $response = $this->postJson('/api/password-reset/send-code', [
            'mobile' => $this->user->mobile,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'کد بازیابی به شماره موبایل شما ارسال شد.',
            ]);

        $this->assertDatabaseHas('password_resets', [
            'mobile' => $this->user->mobile,
        ]);
    }

    /** @test */
    public function password_reset_code_requires_valid_mobile()
    {
        $response = $this->postJson('/api/password-reset/send-code', [
            'mobile' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function user_can_reset_password_with_valid_code()
    {
        // شبیه‌سازی ارسال کد
        $code = '123456';
        $hashedCode = Hash::make($code);

        PasswordReset::create([
            'mobile' => $this->user->mobile,
            'token' => $hashedCode,
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/password-reset/reset', [
            'mobile' => $this->user->mobile,
            'code' => $code,
            'new_password' => 'newSecurePass123!',
            'new_password_confirmation' => 'newSecurePass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'رمز عبور با موفقیت بازنشانی شد.',
            ]);

        // بررسی اینکه رمز عبور تغییر کرده
        $this->assertTrue(
            Hash::check('newSecurePass123!', $this->user->fresh()->password)
        );
    }

    // =========================================================================
    // ======================= Groups Tests =====================================
    // =========================================================================

    /** @test */
    public function authenticated_user_can_list_groups()
    {
        $response = $this->withToken($this->userToken)
            ->getJson('/api/groups');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => ['data'],
            ]);
    }

    /** @test */
    public function admin_can_create_group()
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/groups', [
                'name' => 'New Group',
                'slug' => 'new-group',
                'description' => 'Test description',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'گروه با موفقیت ایجاد شد.',
            ]);

        $this->assertDatabaseHas('user_groups', [
            'name' => 'New Group',
            'slug' => 'new-group',
        ]);
    }

    /** @test */
    public function normal_user_cannot_create_group()
    {
        $response = $this->withToken($this->userToken)
            ->postJson('/api/groups', [
                'name' => 'Should Fail',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_update_group()
    {
        $response = $this->withToken($this->adminToken)
            ->putJson("/api/groups/{$this->group->id}", [
                'name' => 'Updated Group',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'گروه با موفقیت به‌روزرسانی شد.',
            ]);

        $this->assertEquals('Updated Group', $this->group->fresh()->name);
    }

    /** @test */
    public function admin_can_delete_group()
    {
        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/groups/{$this->group->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'گروه با موفقیت حذف شد.',
            ]);

        // Soft delete
        $this->assertSoftDeleted($this->group);
    }

    /** @test */
    public function admin_can_add_user_to_group()
    {
        $response = $this->withToken($this->adminToken)
            ->postJson("/api/groups/{$this->group->id}/users", [
                'user_id' => $this->user->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'کاربر با موفقیت به گروه اضافه شد.',
            ]);

        $this->assertDatabaseHas('user_group_user', [
            'user_group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function admin_can_remove_user_from_group()
    {
        // ابتدا کاربر را به گروه اضافه کن
        $this->group->users()->attach($this->user->id);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/groups/{$this->group->id}/users/{$this->user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'کاربر با موفقیت از گروه حذف شد.',
            ]);

        $this->assertDatabaseMissing('user_group_user', [
            'user_group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function admin_can_bulk_assign_users_to_group()
    {
        $user2 = User::factory()->create();

        $response = $this->withToken($this->adminToken)
            ->postJson("/api/groups/{$this->group->id}/users/bulk", [
                'user_ids' => [$this->user->id, $user2->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('user_group_user', [
            'user_group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('user_group_user', [
            'user_group_id' => $this->group->id,
            'user_id' => $user2->id,
        ]);
    }

    // =========================================================================
    // ======================= Tags Tests =======================================
    // =========================================================================

    /** @test */
    public function authenticated_user_can_list_tags()
    {
        $response = $this->withToken($this->userToken)
            ->getJson('/api/tags');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function admin_can_create_tag()
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/tags', [
                'name' => 'VIP',
                'slug' => 'vip',
                'color' => '#FF5733',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'برچسب با موفقیت ایجاد شد.',
            ]);

        $this->assertDatabaseHas('tags', [
            'name' => 'VIP',
            'slug' => 'vip',
        ]);
    }

    /** @test */
    public function normal_user_cannot_create_tag()
    {
        $response = $this->withToken($this->userToken)
            ->postJson('/api/tags', [
                'name' => 'Should Fail',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_assign_tags_to_themselves()
    {
        $response = $this->withToken($this->userToken)
            ->postJson("/api/users/{$this->user->id}/tags", [
                'tag_ids' => [$this->tag->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'برچسب‌ها با موفقیت به کاربر تخصیص یافتند.',
            ]);

        $this->assertDatabaseHas('taggables', [
            'tag_id' => $this->tag->id,
            'taggable_id' => $this->user->id,
            'taggable_type' => User::class,
        ]);
    }

    /** @test */
    public function user_can_remove_tags_from_themselves()
    {
        // ابتدا برچسب را تخصیص بده
        $this->user->tags()->attach($this->tag->id);

        $response = $this->withToken($this->userToken)
            ->deleteJson("/api/users/{$this->user->id}/tags", [
                'tag_ids' => [$this->tag->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'برچسب‌ها با موفقیت از کاربر حذف شدند.',
            ]);

        $this->assertDatabaseMissing('taggables', [
            'tag_id' => $this->tag->id,
            'taggable_id' => $this->user->id,
            'taggable_type' => User::class,
        ]);
    }

    /** @test */
    public function user_can_view_their_own_tags()
    {
        $this->user->tags()->attach($this->tag->id);

        $response = $this->withToken($this->userToken)
            ->getJson("/api/users/{$this->user->id}/tags");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_protected_routes()
    {
        $this->getJson('/api/groups')
            ->assertStatus(401);

        $this->getJson('/api/tags')
            ->assertStatus(401);

        $this->getJson('/api/users/1/lock-status')
            ->assertStatus(401);
    }

    /** @test */
    public function public_routes_do_not_require_authentication()
    {
        $this->postJson('/api/password-reset/send-code', [
            'mobile' => '09120000003',
        ])->assertStatus(200);
    }
}