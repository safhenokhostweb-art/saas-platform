<?php
namespace Database\Factories;

use App\Models\Infrastructure\RestoreLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RestoreLogFactory extends Factory
{
    protected $model = RestoreLog::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            RestoreLog::TYPE_PLATFORM_DATABASE,
            RestoreLog::TYPE_PLATFORM_STORAGE,
            RestoreLog::TYPE_TENANT_DATABASE,
            RestoreLog::TYPE_TENANT_STORAGE,
        ]);

        return [
            'restore_type'   => $type,
            'site_id'        => str_starts_with($type, 'tenant') ? null : null,
            'backup_file'    => 'saas-platform/database/db_backup_' . now()->format('Y-m-d_His') . '.sql.gz',
            'status'         => RestoreLog::STATUS_SUCCESS,
            'dry_run'        => false,
            'restore_token'  => Str::random(40),
            'executed_by'    => null,
            'started_at'     => now()->subMinutes(5),
            'completed_at'   => now(),
        ];
    }
}
