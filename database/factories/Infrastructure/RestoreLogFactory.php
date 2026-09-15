<?php

namespace Database\Factories\Infrastructure;

use App\Models\Infrastructure\RestoreLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TEST-HARNESS-DB-01: RestoreLog (App\Models\Infrastructure\RestoreLog) had
 * no factory at all — RestoreWizardTest::test_abort_clears_lock_for_pending_restore
 * and its siblings were the only callers of RestoreLog::factory(), so this
 * gap was masked behind that test class's earlier User/Site fixture bugs
 * until those were fixed.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Infrastructure\RestoreLog>
 */
class RestoreLogFactory extends Factory
{
    protected $model = RestoreLog::class;

    public function definition(): array
    {
        return [
            'restore_type'   => RestoreLog::TYPE_PLATFORM_DATABASE,
            'site_id'        => null,
            'backup_file'    => 'platform/db-' . fake()->unique()->numerify('########') . '.sql.gz',
            'status'         => RestoreLog::STATUS_PENDING,
            'dry_run'        => false,
            'restore_token'  => fake()->unique()->uuid(),
        ];
    }
}
