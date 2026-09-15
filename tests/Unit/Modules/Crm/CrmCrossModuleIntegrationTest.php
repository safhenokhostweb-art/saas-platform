<?php

namespace Tests\Unit\Modules\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Forms\Events\FormSubmissionCreated;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormField;
use SaaSPlatform\Modules\Forms\Models\FormSubmission;
use SaaSPlatform\Modules\Tickets\Events\TicketCreated;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 20 — Part 7/9: proves the guarded Event::listen() registration
 * in CrmServiceProvider::boot() actually works end-to-end, using the
 * REAL Forms/Tickets event classes (both modules are genuinely present
 * in this codebase) — not a duck-typed stub. CRM's own src/ still never
 * imports these classes (see CrmFormSubmissionListener/
 * CrmTicketCreatedListener's own docblocks); only this HOST-APP test
 * file does, which is allowed.
 */
class CrmCrossModuleIntegrationTest extends TestCase
{
    use MakesCrmTestSites;
    use RefreshDatabase;

    public function test_a_site_scoped_form_submission_with_an_email_creates_a_lead(): void
    {
        $site = $this->makeCrmTestSite();

        $form = Form::create(['title' => 'Contact Us', 'slug' => 'contact-us-' . uniqid(), 'is_active' => true]);
        $nameField = FormField::create(['form_id' => $form->id, 'name' => 'name', 'label' => 'Name', 'type' => 'text', 'position' => 1]);
        $emailField = FormField::create(['form_id' => $form->id, 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'position' => 2]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'site_id' => $site->id,
            'data' => ["field_{$nameField->id}" => 'Ali Rezaei', "field_{$emailField->id}" => 'ali@example.com'],
        ]);

        event(new FormSubmissionCreated($submission));

        $this->assertDatabaseHas('crm_leads', ['site_id' => $site->id, 'first_name' => 'Ali Rezaei', 'email' => 'ali@example.com']);
    }

    public function test_a_form_submission_with_no_email_or_phone_creates_no_lead(): void
    {
        $site = $this->makeCrmTestSite();

        $form = Form::create(['title' => 'Feedback', 'slug' => 'feedback-' . uniqid(), 'is_active' => true]);
        $messageField = FormField::create(['form_id' => $form->id, 'name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'position' => 1]);

        $submission = FormSubmission::create([
            'form_id' => $form->id, 'site_id' => $site->id, 'data' => ["field_{$messageField->id}" => 'Just a comment'],
        ]);

        event(new FormSubmissionCreated($submission));

        $this->assertSame(0, CrmLead::where('site_id', $site->id)->count());
    }

    public function test_a_high_priority_ticket_logs_an_activity_on_a_matching_contact(): void
    {
        $site = $this->makeCrmTestSite();
        $contact = CrmContact::create(['site_id' => $site->id, 'first_name' => 'Ali', 'phone' => '09120000000']);

        $ticket = Ticket::create([
            'ticket_number' => 'ST-TEST-' . uniqid(), 'description' => 'test',
            'subject' => 'Urgent billing issue', 'status' => 'open', 'priority' => 'high',
            'site_id' => $site->id, 'customer_mobile' => '09120000000',
        ]);

        event(new TicketCreated($ticket));

        $this->assertDatabaseHas('crm_suite_activities', [
            'subject_type' => CrmContact::class, 'subject_id' => $contact->id, 'type' => 'system',
        ]);
    }

    public function test_a_normal_priority_ticket_creates_no_activity(): void
    {
        $site = $this->makeCrmTestSite();
        CrmContact::create(['site_id' => $site->id, 'first_name' => 'Ali', 'phone' => '09120000000']);

        $ticket = Ticket::create([
            'ticket_number' => 'ST-TEST-' . uniqid(), 'description' => 'test',
            'subject' => 'Just a question', 'status' => 'open', 'priority' => 'normal',
            'site_id' => $site->id, 'customer_mobile' => '09120000000',
        ]);

        event(new TicketCreated($ticket));

        $this->assertDatabaseCount('crm_suite_activities', 0);
    }

    public function test_a_high_priority_ticket_with_no_matching_contact_creates_no_activity(): void
    {
        $site = $this->makeCrmTestSite();
        // No CrmContact exists with this phone number at all.

        $ticket = Ticket::create([
            'ticket_number' => 'ST-TEST-' . uniqid(), 'description' => 'test',
            'subject' => 'Urgent', 'status' => 'open', 'priority' => 'high',
            'site_id' => $site->id, 'customer_mobile' => '09129999999',
        ]);

        event(new TicketCreated($ticket));

        $this->assertDatabaseCount('crm_suite_activities', 0);
    }
}
