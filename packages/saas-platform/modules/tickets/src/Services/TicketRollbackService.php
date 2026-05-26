<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 7.2 — TASK 4
 *
 * Rolls back a failed or partial migration by safely truncating the canonical
 * tickets and ticket_replies tables. Legacy tables are NEVER touched.
 *
 * Safe because:
 *  - Legacy routes still serve support_tickets during rollback
 *  - Canonical tables are repopulated on the next migration run
 *  - Both tables carry 0 rows after rollback — no partial state
 */
class TicketRollbackService
{
    /**
     * Truncate canonical tickets and replies tables.
     * Requires explicit confirmation string to prevent accidental calls.
     *
     * @param  string $confirm  Must equal 'CONFIRM_ROLLBACK' to proceed
     * @return array{rolled_back: bool, deleted_tickets: int, deleted_replies: int, message: string}
     */
    public function rollback(string $confirm): array
    {
        if ($confirm !== 'CONFIRM_ROLLBACK') {
            return [
                'rolled_back'      => false,
                'deleted_tickets'  => 0,
                'deleted_replies'  => 0,
                'message'          => 'Rollback aborted: confirmation string mismatch.',
            ];
        }

        Log::warning('[TicketRollbackService] Starting rollback — truncating canonical tables');

        $deletedReplies = 0;
        $deletedTickets = 0;

        try {
            DB::transaction(function () use (&$deletedReplies, &$deletedTickets) {
                $deletedReplies = DB::table('ticket_replies')->count();
                DB::table('ticket_replies')->delete();

                $deletedTickets = DB::table('tickets')->count();
                DB::table('tickets')->delete();
            });
        } catch (\Throwable $e) {
            Log::error('[TicketRollbackService] Rollback failed', ['error' => $e->getMessage()]);

            return [
                'rolled_back'      => false,
                'deleted_tickets'  => 0,
                'deleted_replies'  => 0,
                'message'          => 'Rollback failed: ' . $e->getMessage(),
            ];
        }

        Log::warning('[TicketRollbackService] Rollback complete', [
            'deleted_tickets' => $deletedTickets,
            'deleted_replies' => $deletedReplies,
        ]);

        return [
            'rolled_back'      => true,
            'deleted_tickets'  => $deletedTickets,
            'deleted_replies'  => $deletedReplies,
            'message'          => "Rollback complete. {$deletedTickets} tickets and {$deletedReplies} replies removed from canonical tables.",
        ];
    }

    /**
     * Check whether a rollback is safe to perform.
     * Returns false if legacy tables are empty (rollback would leave no data anywhere).
     */
    public function isSafe(): bool
    {
        $legacyCount = DB::table('support_tickets')->whereNull('deleted_at')->count();

        return $legacyCount > 0;
    }

    /**
     * Preview what would be removed without actually doing it.
     *
     * @return array{canonical_tickets: int, canonical_replies: int, legacy_tickets: int, legacy_replies: int}
     */
    public function dryRun(): array
    {
        return [
            'canonical_tickets'  => DB::table('tickets')->count(),
            'canonical_replies'  => DB::table('ticket_replies')->count(),
            'legacy_tickets'     => DB::table('support_tickets')->count(),
            'legacy_replies'     => DB::table('support_ticket_replies')->count(),
        ];
    }
}
