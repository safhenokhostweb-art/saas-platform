<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Unit;

use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\MotionGraphics\Providers\MotionGraphicsServiceProvider;

final class PackageDefaultsTest extends TestCase
{
    protected function getPackageProviders($app): array { return [MotionGraphicsServiceProvider::class]; }

    public function test_package_is_disabled_and_does_not_activate_routes_or_migrations(): void
    {
        $this->assertFalse(config('motion-graphics.enabled'));
        $this->assertFalse(config('motion-graphics.routes_enabled'));
        $this->assertFalse(config('motion-graphics.admin_ui_enabled'));
        $this->assertFalse(config('motion-graphics.runtime_api_enabled'));
        $this->assertFalse(config('motion-graphics.publishing_enabled'));
        $this->assertFalse(config('motion-graphics.ai_enabled'));
        $this->assertFalse(config('motion-graphics.customer_runtime_enabled'));
        $this->assertFalse(config('motion-graphics.real_storage_enabled'));
        $this->assertFalse(Route::has('motion-graphics.admin.index'));
    }
}
