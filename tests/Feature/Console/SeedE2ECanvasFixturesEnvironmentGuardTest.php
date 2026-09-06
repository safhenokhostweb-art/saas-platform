<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * e2e:seed-canvas-fixtures creates a real is_admin=true super-admin user
 * with a hardcoded, publicly-known password (App\Console\Commands\
 * SeedE2ECanvasFixtures.php) — a real risk if ever invoked outside a
 * throwaway local/testing database. This locks the fail-closed guard: any
 * environment other than local/testing must refuse with a non-zero exit
 * code and perform ZERO writes, never merely warn.
 */
class SeedE2ECanvasFixturesEnvironmentGuardTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE_MOBILE = '09120000091';

    public function test_it_refuses_to_run_outside_local_and_testing_environments(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('e2e:seed-canvas-fixtures')->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['mobile' => self::FIXTURE_MOBILE]);
        $this->assertDatabaseMissing('extensions', ['vendor_slug' => 'e2etest']);
    }

    public function test_it_runs_in_the_testing_environment(): void
    {
        $this->app['env'] = 'testing';

        $this->artisan('e2e:seed-canvas-fixtures')->assertExitCode(0);

        $this->assertTrue(User::withTrashed()->where('mobile', self::FIXTURE_MOBILE)->exists());
    }
}
