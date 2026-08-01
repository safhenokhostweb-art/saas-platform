<?php

namespace Tests\Unit\Modules\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteNotificationDispatcherInterface;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;
use SaaSPlatform\Modules\Crm\Models\CrmTask;
use SaaSPlatform\Modules\Crm\Services\CrmTaskReminderService;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 20 — Part 6/9: dispatches via SiteNotificationDispatcherInterface
 * (the generic extension point, Part 7) — never the platform's concrete
 * SMS/PlatformSiteNotification classes directly, so this test binds a
 * simple recording fake, exactly like any other interface-based test.
 */
class CrmTaskReminderServiceTest extends TestCase
{
    use MakesCrmTestSites;
    use RefreshDatabase;

    private function fakeDispatcher(): object
    {
        return new class implements SiteNotificationDispatcherInterface {
            public array $calls = [];

            public function notifySiteAdmin(int $siteId, string $title, string $message): void
            {
                $this->calls[] = compact('siteId', 'title', 'message');
            }
        };
    }

    public function test_sends_a_reminder_for_a_task_whose_remind_at_has_passed(): void
    {
        $site = $this->makeCrmTestSite();
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        $contact = CrmContact::create(['site_id' => $site->id, 'first_name' => 'A']);
        $contact->tasks()->create(['site_id' => $site->id, 'title' => 'Call back', 'remind_at' => now()->subMinute()]);

        $counts = (new CrmTaskReminderService())->run();

        $this->assertSame(1, $counts['deadline']);
        $this->assertCount(1, $fake->calls);
    }

    public function test_does_not_send_the_same_reminder_twice(): void
    {
        $site = $this->makeCrmTestSite();
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        $contact = CrmContact::create(['site_id' => $site->id, 'first_name' => 'A']);
        $contact->tasks()->create(['site_id' => $site->id, 'title' => 'Call back', 'remind_at' => now()->subMinute()]);

        (new CrmTaskReminderService())->run();
        (new CrmTaskReminderService())->run(); // second run must be a no-op for the same task

        $this->assertCount(1, $fake->calls);
    }

    public function test_sends_a_reminder_for_an_overdue_task(): void
    {
        $site = $this->makeCrmTestSite();
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        $contact = CrmContact::create(['site_id' => $site->id, 'first_name' => 'A']);
        $contact->tasks()->create(['site_id' => $site->id, 'title' => 'Overdue', 'due_at' => now()->subDay()]);

        $counts = (new CrmTaskReminderService())->run();

        $this->assertSame(1, $counts['overdue']);
    }

    public function test_sends_a_reminder_for_an_opportunity_with_no_recent_activity(): void
    {
        $site = $this->makeCrmTestSite();
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        $pipeline = CrmPipeline::create(['site_id' => $site->id, 'name' => 'Default']);
        $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Open']);
        $opportunity = CrmOpportunity::create([
            'site_id' => $site->id, 'name' => 'Stale Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id,
        ]);
        // created_at isn't mass-assignable (not in $fillable) — backdate it directly.
        $opportunity->forceFill(['created_at' => now()->subDays(20)])->save();

        $counts = (new CrmTaskReminderService())->run();

        $this->assertSame(1, $counts['stale_opportunity']);
        $this->assertNotNull($opportunity->fresh()->stale_notified_at);
    }
}
