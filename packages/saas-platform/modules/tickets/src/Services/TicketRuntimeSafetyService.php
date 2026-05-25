<?php

namespace SaaSPlatform\Modules\Tickets\Services;

/**
 * PHASE 7.1 — TASK 5: Ticket Runtime Safety Service
 *
 * Runtime checks that the module is operating correctly.
 * Runs on-demand (e.g., health endpoint, monitoring commands).
 */
class TicketRuntimeSafetyService
{
    public function check(): array
    {
        $errors   = [];
        $warnings = [];

        $this->checkTableExists($errors);
        $this->checkContractBindings($errors);
        $this->checkConfigPresent($errors, $warnings);

        return [
            'safe'     => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    protected function checkTableExists(array &$errors): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tickets')) {
            $errors[] = '[tickets] table missing — run php artisan migrate';
        }
    }

    protected function checkContractBindings(array &$errors): void
    {
        $contracts = [
            \SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface::class,
            \SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface::class,
        ];

        foreach ($contracts as $contract) {
            if (!app()->bound($contract)) {
                $errors[] = "Contract [{$contract}] is not bound in the service container.";
            }
        }
    }

    protected function checkConfigPresent(array &$errors, array &$warnings): void
    {
        if (!config('tickets.statuses')) {
            $warnings[] = 'config/tickets.php missing statuses key — using defaults';
        }
    }
}
