<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SAAS ZERO-FAILURE PUNCH LIST (Task 2A) + Git Packaging Audit gap-closure:
 * `groups.store` was missing the `admin` middleware any non-admin Sanctum
 * user could have created a group; `tags.store` had no route registered at
 * all despite TagApiController::store() being fully implemented (a genuine
 * route regression). Both were fixed in routes/api.php; this is the
 * regression coverage that was missing at the time.
 */
class GroupsTagsStoreAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_store_route_requires_admin(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($nonAdmin, 'sanctum')
            ->postJson('/api/groups', ['name' => 'رد شده'])
            ->assertForbidden();
        $this->assertDatabaseMissing('user_groups', ['name' => 'رد شده']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/groups', ['name' => 'گروه مجاز'])
            ->assertCreated();
        $this->assertDatabaseHas('user_groups', ['name' => 'گروه مجاز']);
    }

    public function test_tag_store_route_exists_and_requires_admin(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($nonAdmin, 'sanctum')
            ->postJson('/api/tags', ['name' => 'رد شده'])
            ->assertForbidden();
        $this->assertDatabaseMissing('tags', ['name' => 'رد شده']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/tags', ['name' => 'برچسب مجاز']);
        $response->assertCreated();
        $this->assertDatabaseHas('tags', ['name' => 'برچسب مجاز']);
    }
}
