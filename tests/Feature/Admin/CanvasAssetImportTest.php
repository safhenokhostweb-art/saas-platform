<?php

namespace Tests\Feature\Admin;

use App\Models\CanvasAssetVersion;
use App\Models\CanvasButtonPack;
use App\Models\CanvasIconPack;
use App\Models\User;
use App\Services\AssetProvisioning\CanvasAssetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CanvasAssetImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_command_imports_assets_successfully(): void
    {
        $fixturePath = database_path('seeders/fixtures/canvas_sample_assets.json');

        $this->artisan('canvas:import-assets', ['file' => $fixturePath])
            ->expectsOutputToContain('ورود دارایی‌ها با موفقیت انجام شد.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('canvas_button_packs', [
            'slug' => 'modern-gradients',
            'title' => 'پکیج گرادیانت‌های مدرن',
            'current_version' => '1.0.0',
        ]);

        $this->assertDatabaseHas('canvas_button_presets', [
            'slug' => 'sunset-glow',
        ]);

        $this->assertDatabaseHas('canvas_icon_packs', [
            'slug' => 'finance-duotone',
            'title' => 'آیکن‌های مالی و بانکی',
            'current_version' => '1.0.0',
        ]);

        $this->assertDatabaseHas('canvas_icon_items', [
            'slug' => 'wallet',
        ]);

        $this->assertDatabaseHas('canvas_asset_versions', [
            'asset_kind' => 'button_pack',
            'asset_slug' => 'modern-gradients',
            'version' => '1.0.0',
        ]);

        $this->assertDatabaseHas('canvas_asset_versions', [
            'asset_kind' => 'icon_pack',
            'asset_slug' => 'finance-duotone',
            'version' => '1.0.0',
        ]);
    }

    public function test_admin_can_import_json_via_controller(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $jsonPayload = json_encode([
            'button_packs' => [
                [
                    'slug' => 'neon-glow',
                    'title' => 'دکمه نئونی',
                    'version' => '1.0.0',
                    'changelog' => 'نسخه اول نئونی',
                ],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('assets.json', $jsonPayload);

        $response = $this->actingAs($admin)->post(route('admin.canvas-assets.import-json'), [
            'json_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('canvas_button_packs', [
            'slug' => 'neon-glow',
            'title' => 'دکمه نئونی',
        ]);

        $this->assertDatabaseHas('canvas_asset_versions', [
            'asset_kind' => 'button_pack',
            'asset_slug' => 'neon-glow',
            'version' => '1.0.0',
        ]);
    }
}
