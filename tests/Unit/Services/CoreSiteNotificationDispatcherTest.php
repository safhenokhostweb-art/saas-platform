<?php

namespace Tests\Unit\Services;

use App\Models\PlatformSiteNotification;
use App\Services\CoreSiteNotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteNotificationDispatcherInterface;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Regression coverage for the 2026-08 production incident where
 * SiteNotificationDispatcherInterface failed to resolve hourly via
 * crm:send-task-reminders because the deployed vendor/core-utils was
 * stale relative to the app code. These tests exercise the real
 * container binding (no fake override) so a stale/missing autoload
 * of the interface, or a broken AppServiceProvider binding, fails
 * the suite instead of only surfacing in production.
 */
class CoreSiteNotificationDispatcherTest extends TestCase
{
    use MakesCrmTestSites;
    use RefreshDatabase;

    public function test_the_interface_and_its_implementation_autoload(): void
    {
        $this->assertTrue(interface_exists(SiteNotificationDispatcherInterface::class));
        $this->assertTrue(class_exists(CoreSiteNotificationDispatcher::class));
        $this->assertTrue(is_subclass_of(CoreSiteNotificationDispatcher::class, SiteNotificationDispatcherInterface::class));
    }

    public function test_the_container_resolves_the_real_dispatcher_from_the_default_binding(): void
    {
        $dispatcher = $this->app->make(SiteNotificationDispatcherInterface::class);

        $this->assertInstanceOf(CoreSiteNotificationDispatcher::class, $dispatcher);
    }

    public function test_crm_reminder_command_runs_end_to_end_with_the_real_dispatcher(): void
    {
        $site = $this->makeCrmTestSite();
        $contact = \SaaSPlatform\Modules\Crm\Models\CrmContact::create(['site_id' => $site->id, 'first_name' => 'A']);
        $contact->tasks()->create(['site_id' => $site->id, 'title' => 'Call back', 'remind_at' => now()->subMinute()]);

        $exitCode = $this->artisan('crm:send-task-reminders')->run();

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas(PlatformSiteNotification::class, ['site_id' => $site->id]);
    }
}
