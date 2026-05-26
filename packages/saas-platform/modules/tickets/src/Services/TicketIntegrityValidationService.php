<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 7.2 — TASK 4
 *
 * Validates the integrity of the canonical tickets / ticket_replies tables
 * against the legacy support_tickets / support_ticket_replies tables after migration.
 *
 * Run before cutting over active routes to the canonical system.
 */
class TicketIntegrityValidationService
{
    /**
     * Run all integrity checks.
     *
     * @return array{passed: bool, checks: array<string, array{passed: bool, message: string, details: array}>}
     */
    public function validate(): array
    {
        $checks = [];

        $checks['tables_exist']         = $this->checkTablesExist();
        $checks['ticket_count_match']   = $this->checkTicketCountMatch();
        $checks['reply_count_match']    = $this->checkReplyCountMatch();
        $checks['ticket_number_parity'] = $this->checkTicketNumberParity();
        $checks['orphaned_replies']     = $this->checkOrphanedReplies();
        $checks['null_descriptions']    = $this->checkNullDescriptions();
        $checks['site_id_integrity']    = $this->checkSiteIdIntegrity();
        $checks['status_distribution']  = $this->checkStatusDistribution();

        $allPassed = collect($checks)->every(fn($c) => $c['passed']);

        return [
            'passed' => $allPassed,
            'checks' => $checks,
        ];
    }

    private function checkTablesExist(): array
    {
        $missing = [];
        foreach (['tickets', 'ticket_replies'] as $table) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }
        return [
            'passed'  => empty($missing),
            'message' => empty($missing) ? 'Canonical tables exist' : 'Missing tables: ' . implode(', ', $missing),
            'details' => $missing,
        ];
    }

    private function checkTicketCountMatch(): array
    {
        $legacyCount    = DB::table('support_tickets')->whereNull('deleted_at')->count();
        $canonicalCount = DB::table('tickets')->whereNull('deleted_at')->count();

        return [
            'passed'  => $canonicalCount >= $legacyCount,
            'message' => "Legacy: {$legacyCount} active tickets — Canonical: {$canonicalCount} active tickets",
            'details' => ['legacy' => $legacyCount, 'canonical' => $canonicalCount, 'delta' => $canonicalCount - $legacyCount],
        ];
    }

    private function checkReplyCountMatch(): array
    {
        $legacyCount    = DB::table('support_ticket_replies')->whereNull('deleted_at')->count();
        $canonicalCount = DB::table('ticket_replies')->whereNull('deleted_at')->count();

        return [
            'passed'  => $canonicalCount >= $legacyCount,
            'message' => "Legacy: {$legacyCount} replies — Canonical: {$canonicalCount} replies",
            'details' => ['legacy' => $legacyCount, 'canonical' => $canonicalCount, 'delta' => $canonicalCount - $legacyCount],
        ];
    }

    private function checkTicketNumberParity(): array
    {
        $legacyNumbers    = DB::table('support_tickets')->pluck('ticket_number')->sort()->values()->all();
        $canonicalNumbers = DB::table('tickets')->pluck('ticket_number')->sort()->values()->all();

        $missing = array_diff($legacyNumbers, $canonicalNumbers);

        return [
            'passed'  => empty($missing),
            'message' => empty($missing)
                ? 'All ticket numbers present in canonical table'
                : count($missing) . ' ticket numbers missing from canonical table',
            'details' => array_values($missing),
        ];
    }

    private function checkOrphanedReplies(): array
    {
        $orphaned = DB::table('ticket_replies')
            ->leftJoin('tickets', 'tickets.id', '=', 'ticket_replies.ticket_id')
            ->whereNull('tickets.id')
            ->count();

        return [
            'passed'  => $orphaned === 0,
            'message' => $orphaned === 0 ? 'No orphaned replies' : "{$orphaned} replies have no parent ticket",
            'details' => ['orphaned_count' => $orphaned],
        ];
    }

    private function checkNullDescriptions(): array
    {
        $nullCount = DB::table('tickets')->whereNull('description')->orWhere('description', '')->count();

        return [
            'passed'  => $nullCount === 0,
            'message' => $nullCount === 0 ? 'All canonical tickets have a description' : "{$nullCount} tickets have empty description",
            'details' => ['empty_description_count' => $nullCount],
        ];
    }

    private function checkSiteIdIntegrity(): array
    {
        $orphanedSites = DB::table('tickets')
            ->leftJoin('sites', 'sites.id', '=', 'tickets.site_id')
            ->whereNotNull('tickets.site_id')
            ->whereNull('sites.id')
            ->count();

        return [
            'passed'  => $orphanedSites === 0,
            'message' => $orphanedSites === 0 ? 'All site_id references valid' : "{$orphanedSites} tickets reference non-existent sites",
            'details' => ['orphaned_site_count' => $orphanedSites],
        ];
    }

    private function checkStatusDistribution(): array
    {
        $legacy = DB::table('support_tickets')
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $canonical = DB::table('tickets')
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $mismatched = [];
        foreach ($legacy as $status => $count) {
            if (($canonical[$status] ?? 0) < $count) {
                $mismatched[$status] = ['legacy' => $count, 'canonical' => $canonical[$status] ?? 0];
            }
        }

        return [
            'passed'  => empty($mismatched),
            'message' => empty($mismatched) ? 'Status distribution matches' : 'Status count mismatch for: ' . implode(', ', array_keys($mismatched)),
            'details' => $mismatched,
        ];
    }
}
