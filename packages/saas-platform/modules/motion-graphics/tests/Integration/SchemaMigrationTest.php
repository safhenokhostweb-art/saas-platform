<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class SchemaMigrationTest extends TestCase
{
    public function test_all_module_tables_and_unique_constraints_exist(): void
    {
        foreach (['motion_groups', 'motion_categories', 'motion_tags', 'motion_graphics', 'motion_graphic_tag', 'motion_graphic_versions', 'motion_graphic_deployments'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' is missing');
        }
    }

    public function test_every_declared_index_name_is_within_mysql_limit(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_13_000001_create_motion_graphics_tables.php');
        preg_match_all('/(?:index|unique|primary)\([^;]*?,\s*\'([^\']+)\'\)/s', $migration, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $name) $this->assertLessThanOrEqual(64, strlen($name), $name);
    }

    public function test_migration_can_roll_back_and_run_again_in_isolation(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations';
        Artisan::call('migrate:rollback', ['--path' => $path, '--realpath' => true, '--force' => true]);
        $this->assertFalse(Schema::hasTable('motion_graphics'));
        Artisan::call('migrate', ['--path' => $path, '--realpath' => true, '--force' => true]);
        $this->assertTrue(Schema::hasTable('motion_graphics'));
    }
}
