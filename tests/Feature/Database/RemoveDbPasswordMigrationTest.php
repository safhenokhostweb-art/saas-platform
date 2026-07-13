<?php

namespace Tests\Feature\Database;

use App\Models\Customer;
use App\Models\Site;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 13-O12e — the migration that drops sites.db_password embeds an
 * unconditional safety guard: it refuses to run if even one site still has
 * a plaintext value, regardless of whether an operator remembered to run
 * `sites:migrate-db-passwords --verify` first. This test exercises that
 * guard directly by re-adding the legacy column (simulating a database
 * where migration ran before the backfill completed) and re-invoking the
 * migration's up().
 */
class RemoveDbPasswordMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        if (Schema::hasColumn('sites', 'db_password')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropColumn('db_password');
            });
        }

        // The ALTER TABLE above triggers an implicit commit in MySQL, which
        // ends RefreshDatabase's per-test transaction early — so its
        // rollback can no longer undo rows this test inserted afterward.
        // Clean up explicitly so later tests don't inherit leaked rows.
        Site::query()->delete();
        Customer::query()->delete();

        parent::tearDown();
    }

    private function migrationInstance(): object
    {
        return require database_path('migrations/2026_07_14_110000_remove_db_password_from_sites_table.php');
    }

    public function test_migration_refuses_to_drop_column_when_plaintext_still_exists(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('db_password')->nullable();
        });

        $customer = Customer::create(['name' => 'Migration Guard Co', 'mobile' => '09121234567']);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'Guard Test Site']);
        DB::table('sites')->where('id', $site->id)->update(['db_password' => 'StillPlaintext']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Refusing to drop sites\.db_password/');

        $this->migrationInstance()->up();
    }

    public function test_migration_allows_drop_when_column_present_but_empty(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('db_password')->nullable();
        });

        $customer = Customer::create(['name' => 'Migration Guard Co 2', 'mobile' => '09121234568']);
        Site::create(['customer_id' => $customer->id, 'title' => 'Guard Pass Site']);

        $this->migrationInstance()->up();

        $this->assertFalse(Schema::hasColumn('sites', 'db_password'));
    }
}
