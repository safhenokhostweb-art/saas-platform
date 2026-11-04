<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ProfileFieldSeeder::class,
            ModuleSeeder::class,
            ThemeProfileSeeder::class,
            MotionGraphicsDefaultSeeder::class,
        ]);

        // Theme catalog: use `php artisan theme-catalog:rebuild`, the one
        // canonical entry point (Phase 2b) — not a seeder in this list.
        // ProfessionalThemesSeeder was removed here and deleted (Phase 2b):
        // it stored page content under settings.layouts.*, a shape the
        // current pages-based rendering/release/quality-gate architecture
        // never reads, so every theme it produced had zero real pages.

        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
