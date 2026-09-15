<?php

namespace Tests\Unit\Modules\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmTask;
use Tests\TestCase;

/**
 * Phase 19 — Part 6: Activity (timeline, including Note as type=note),
 * Task (including remind_at absorbing "Reminder"), and Attachment —
 * all polymorphic against Contact/Company/Lead/Opportunity.
 */
class CrmActivityTaskAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_an_activity_against_a_contact_appears_in_its_timeline(): void
    {
        $contact = CrmContact::create(['first_name' => 'Ali']);

        $contact->activities()->create(['type' => 'call', 'subject' => 'Intro call', 'occurred_at' => now()]);

        $this->assertSame(1, $contact->activities()->count());
        $this->assertSame('call', $contact->activities()->first()->type);
    }

    public function test_note_is_stored_as_an_activity_of_type_note_not_a_separate_table(): void
    {
        $contact = CrmContact::create(['first_name' => 'Ali']);

        $contact->activities()->create(['type' => 'note', 'body' => 'Called back tomorrow']);

        $this->assertDatabaseHas('crm_suite_activities', ['subject_id' => $contact->id, 'type' => 'note', 'body' => 'Called back tomorrow']);
        $this->assertFalse(Schema::hasTable('crm_notes'));
    }

    public function test_activities_have_no_updated_at_column_behavior(): void
    {
        $contact = CrmContact::create(['first_name' => 'Ali']);
        $activity = $contact->activities()->create(['type' => 'note', 'body' => 'x']);

        $this->assertNull($activity->updated_at);
    }

    public function test_task_complete_sets_status_and_completed_at(): void
    {
        $contact = CrmContact::create(['first_name' => 'Ali']);
        $task = $contact->tasks()->create(['title' => 'Follow up', 'due_at' => now()->addDay()]);

        $this->assertSame(CrmTask::STATUS_PENDING, $task->status);

        $task->complete();

        $this->assertSame(CrmTask::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_reminder_is_a_column_on_task_not_a_separate_entity(): void
    {
        $contact = CrmContact::create(['first_name' => 'Ali']);
        $task = $contact->tasks()->create(['title' => 'Call back', 'remind_at' => now()->addHours(2)]);

        $this->assertNotNull($task->remind_at);
        $this->assertFalse(Schema::hasTable('crm_reminders'));
    }

    public function test_due_soon_scope_only_returns_pending_tasks_due_within_window(): void
    {
        $contact = CrmContact::create(['first_name' => 'Ali']);
        $contact->tasks()->create(['title' => 'Soon', 'due_at' => now()->addDays(2)]);
        $contact->tasks()->create(['title' => 'Far', 'due_at' => now()->addDays(30)]);
        $completed = $contact->tasks()->create(['title' => 'Done', 'due_at' => now()->addDay()]);
        $completed->complete();

        $this->assertSame(1, CrmTask::dueSoon(7)->count());
    }

    public function test_attachment_is_attachable_to_a_contact(): void
    {
        $contact = CrmContact::create(['first_name' => 'Ali']);

        $contact->attachments()->create([
            'disk' => 'local', 'path' => 'crm-attachments/file.pdf', 'original_name' => 'file.pdf', 'size_bytes' => 100,
        ]);

        $this->assertSame(1, $contact->attachments()->count());
        $this->assertSame('file.pdf', $contact->attachments()->first()->original_name);
    }
}
