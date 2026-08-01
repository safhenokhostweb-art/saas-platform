<?php

namespace SaaSPlatform\Modules\Forms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaaSPlatform\Modules\Forms\Models\FormSubmission;

/**
 * Phase 20 — Part 7: canonical form-submission event, modeled directly on
 * the Tickets module's own TicketCreated event
 * (packages/saas-platform/modules/tickets/src/Events/TicketCreated.php).
 * Dispatched only for site-scoped submissions (see
 * FormFrontendController::submitForSite()) — a form has no notion of a
 * "lead" outside a tenant context. Any module (e.g. CRM) may listen for
 * this without Forms knowing or depending on it — see
 * CrmServiceProvider::boot()'s guarded Event::listen() for the consumer
 * side of this extension point.
 */
class FormSubmissionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly FormSubmission $submission,
    ) {}
}
