<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Profile;
use App\Models\User;
use App\Services\Infrastructure\StorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression coverage for the MediaStorage refactor (see
 * docs/storage-regression/STORAGE_REGRESSION_REPORT.md):
 *
 * - Existing avatars (legacy "uploads/..." DB convention) keep resolving.
 * - New avatar uploads succeed and update the DB path.
 * - Upload failures are surfaced to the user instead of being swallowed.
 */
class ProfileAvatarStorageRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), '1');
        Storage::fake('uploads');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    /**
     * database\factories\UserFactory still inserts an 'email' column that
     * migration 2026_05_30_000001_remove_email_from_users_and_password_reset_tokens
     * dropped from `users` — a pre-existing, storage-refactor-unrelated
     * mismatch that makes User::factory()->create() fail against the current
     * schema (see STORAGE_REGRESSION_REPORT.md). Build users directly here
     * instead of touching the shared factory.
     */
    private function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'mobile'   => '09' . random_int(100000000, 999999999),
            'password' => bcrypt('password'),
            'is_admin' => false,
        ], $attributes));
    }

    public function test_existing_legacy_avatar_still_resolves_to_a_working_url(): void
    {
        $user = $this->makeUser();
        Profile::create(['user_id' => $user->id, 'mobile' => $user->mobile, 'avatar' => 'uploads/avatars/old.jpg']);
        Storage::disk('uploads')->put('avatars/old.jpg', 'binary-data');

        $url = $user->fresh('profile')->profile->avatar_url;

        $this->assertStringEndsWith('avatars/old.jpg', $url);
    }

    public function test_customer_profile_avatar_upload_succeeds_and_updates_db_path(): void
    {
        $user = $this->makeUser();
        Profile::create(['user_id' => $user->id, 'mobile' => $user->mobile]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();

        $profile = $user->fresh('profile')->profile;
        $this->assertStringStartsWith('uploads/avatars/', $profile->avatar);
        Storage::disk('uploads')->assertExists(substr($profile->avatar, strlen('uploads/')));
    }

    public function test_customer_profile_avatar_upload_failure_is_visible_and_other_fields_still_save(): void
    {
        $user = $this->makeUser();
        Profile::create(['user_id' => $user->id, 'mobile' => $user->mobile]);

        $this->app->bind(StorageManager::class, function () {
            return new class extends StorageManager {
                public function storeUploadedFile(\Illuminate\Http\UploadedFile $file, string $directory, string $disk, ?string $filename = null): string
                {
                    throw new \RuntimeException('simulated disk failure');
                }
            };
        });

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'full_name' => 'Regression Tester',
            'avatar'    => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('avatar');

        // Other fields must not be lost just because the avatar upload failed.
        $profile = $user->fresh('profile')->profile;
        $this->assertSame('Regression Tester', $profile->full_name);
        $this->assertNull($profile->avatar);
    }

    public function test_admin_profile_avatar_upload_failure_is_reported_in_json_response(): void
    {
        $admin = $this->makeUser(['is_admin' => true]);

        $this->app->bind(StorageManager::class, function () {
            return new class extends StorageManager {
                public function storeUploadedFile(\Illuminate\Http\UploadedFile $file, string $directory, string $disk, ?string $filename = null): string
                {
                    throw new \RuntimeException('simulated disk failure');
                }
            };
        });

        $response = $this->actingAs($admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->put(route('admin.profile.update'), [
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertNotEmpty($response->json('avatar_error'));
    }
}
