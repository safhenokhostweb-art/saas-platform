<?php

namespace SaaSPlatform\Modules\Tickets\Services;

/**
 * PHASE 7.1 — TASK 5: Ticket Update Validation Service
 *
 * Pre-update checks before applying a module update.
 * Follows Forms module pattern exactly.
 */
class TicketUpdateValidationService
{
    public function validate(string $targetVersion): array
    {
        $errors   = [];
        $warnings = [];

        $this->checkDatabaseIntegrity($errors, $warnings);
        $this->checkOpenTicketsImpact($errors, $warnings);
        $this->checkSlaConfiguration($errors, $warnings);

        return [
            'valid'    => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    protected function checkDatabaseIntegrity(array &$errors, array &$warnings): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tickets')) {
            $errors[] = 'Table [tickets] does not exist. Run migrations first.';
        }
        if (!\Illuminate\Support\Facades\Schema::hasTable('ticket_replies')) {
            $errors[] = 'Table [ticket_replies] does not exist.';
        }
    }

    protected function checkOpenTicketsImpact(array &$errors, array &$warnings): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tickets')) {
            return;
        }
        $openCount = \SaaSPlatform\Modules\Tickets\Models\Ticket::whereIn('status', [
            \SaaSPlatform\Modules\Tickets\Models\Ticket::STATUS_OPEN,
            \SaaSPlatform\Modules\Tickets\Models\Ticket::STATUS_IN_PROGRESS,
        ])->count();

        if ($openCount > 0) {
            $warnings[] = "{$openCount} open/in-progress ticket(s) will remain accessible during update.";
        }
    }

    protected function checkSlaConfiguration(array &$errors, array &$warnings): void
    {
        if (config('feature-flags.tickets.sla') && !config('tickets.sla.first_response_minutes')) {
            $warnings[] = 'SLA is enabled but first_response_minutes is not configured.';
        }
    }
}
