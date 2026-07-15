<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use SaaSPlatform\Modules\MotionGraphics\Contracts\ClockContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAiProviderContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAuditLoggerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPreviewGeneratorContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGroup;
use SaaSPlatform\Modules\MotionGraphics\Providers\MotionGraphicsServiceProvider;
use SaaSPlatform\Modules\MotionGraphics\Tests\Fakes\{FakeAiProvider, FakePermissionGateway, FakePlatformAdminResolver, FakePreviewGenerator, FakeSiteResolver, FrozenClock, RecordingAuditLogger, RecordingDeploymentGateway, TestActor};

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected TestActor $admin;
    protected TestActor $tenant;

    protected function getPackageProviders($app): array { return [MotionGraphicsServiceProvider::class]; }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));
        $driver = getenv('MOTION_TEST_DB_CONNECTION') ?: 'sqlite';
        $app['config']->set('database.default', $driver);
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => getenv('MOTION_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('MOTION_TEST_DB_PORT') ?: '3306',
            'database' => getenv('MOTION_TEST_DB_DATABASE') ?: 'motion_graphics_module_test',
            'username' => getenv('MOTION_TEST_DB_USERNAME') ?: 'root',
            'password' => getenv('MOTION_TEST_DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]);
        foreach (['enabled', 'publishing_enabled', 'ai_enabled'] as $flag) $app['config']->set('motion-graphics.'.$flag, true);
        foreach (['routes_enabled', 'admin_ui_enabled', 'runtime_api_enabled', 'customer_runtime_enabled'] as $flag) $app['config']->set('motion-graphics.'.$flag, false);
        $app->singleton(PlatformAdminResolverContract::class, FakePlatformAdminResolver::class);
        $app->singleton(MotionPermissionGatewayContract::class, FakePermissionGateway::class);
        $app->singleton(SiteResolverContract::class, FakeSiteResolver::class);
        $app->singleton(MotionAiProviderContract::class, FakeAiProvider::class);
        $app->singleton(MotionPreviewGeneratorContract::class, FakePreviewGenerator::class);
        $app->singleton(MotionAuditLoggerContract::class, RecordingAuditLogger::class);
        $app->singleton(MotionDeploymentGatewayContract::class, RecordingDeploymentGateway::class);
        $app->singleton(ClockContract::class, FrozenClock::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new TestActor('platform-admin-1', true);
        $this->tenant = new TestActor('tenant-user-1', false);
    }

    protected function group(string $ownership = MotionGraphic::OWNERSHIP_GLOBAL, ?string $site = null): MotionGroup
    {
        $number = MotionGroup::query()->count();
        return MotionGroup::query()->create([
            'name' => 'Business '.$number, 'slug' => 'business-'.$number,
            'ownership_type' => $ownership, 'owner_site_identifier' => $site,
            'sort_order' => 0, 'is_active' => true,
        ]);
    }

    protected function svgInput(string $title = 'Source'): array
    {
        return [
            'engine' => 'svg-js', 'asset_type' => 'svg',
            'source_definition' => ['title' => $title, 'style' => ['color' => '#000000', 'speed' => 1]],
            'files' => [['relative_path' => 'animation.svg', 'contents' => '<svg xmlns=\'http://www.w3.org/2000/svg\'><title>'.$title.'</title></svg>', 'mime' => 'image/svg+xml']],
            'dependencies' => [],
        ];
    }

    protected function motionData(string $slug, ?MotionGroup $group = null, string $ownership = MotionGraphic::OWNERSHIP_GLOBAL, ?string $site = null): array
    {
        return [
            'name' => ucfirst($slug), 'slug' => $slug, 'ownership_type' => $ownership,
            'owner_site_identifier' => $site, 'group_id' => ($group ?? $this->group($ownership, $site))->id,
            'engine' => 'svg-js', 'asset_type' => 'svg',
            'editable_schema_json' => [
                'type' => 'object', 'required' => ['title', 'style'],
                'properties' => [
                    'title' => ['type' => 'string'],
                    'style' => ['type' => 'object', 'required' => ['color', 'speed'], 'properties' => [
                        'color' => ['type' => 'string'], 'speed' => ['type' => 'number', 'minimum' => 0.1, 'maximum' => 5],
                    ]],
                ],
            ],
            'runtime_configuration' => ['loop' => true],
            'dimensions' => ['width' => 800, 'height' => 600],
            'accessibility' => ['label' => 'Decorative business motion'],
        ];
    }
}
