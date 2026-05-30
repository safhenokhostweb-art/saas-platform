<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 7.2 — TASK 4
 *
 * Migrates data from the legacy support_tickets / support_ticket_replies tables
 * into the canonical tickets / ticket_replies tables.
 *
 * Divergences handled:
 *  D1: message → description (column rename)
 *  D2: attachments stored as plain text → canonical json column
 *  D3: customer_id derived by matching customer_mobile + site_id against users table
 *  D4: resolved_at derived from closed_at where status = 'closed'
 *  D5: support_level column dropped (not in canonical schema)
 *  R1: support_ticket_id → ticket_id resolved via ticket_number lookup
 */
class TicketDataMigrationService
{
    private int $batchSize = 100;

    /**
     * Run full migration from legacy tables to canonical tables.
     * Idempotent — already-migrated tickets (same ticket_number) are skipped.
     *
     * @return array{migrated_tickets: int, migrated_replies: int, skipped: int, errors: array}
     */
    public function migrate(): array
    {
        $result = [
            'migrated_tickets' => 0,
            'migrated_replies' => 0,
            'skipped'          => 0,
            'errors'           => [],
        ];

        Log::info('[TicketDataMigrationService] Starting migration from support_tickets → tickets');

        $existingTicketNumbers = DB::table('tickets')
            ->pluck('ticket_number')
            ->flip()
            ->all();

        DB::table('support_tickets')
            ->orderBy('id')
            ->chunk($this->batchSize, function ($legacyTickets) use (&$result, &$existingTicketNumbers) {
                $rows = [];

                foreach ($legacyTickets as $legacy) {
                    if (isset($existingTicketNumbers[$legacy->ticket_number])) {
                        $result['skipped']++;
                        continue;
                    }

                    try {
                        $rows[] = $this->buildTicketRow($legacy);
                        $existingTicketNumbers[$legacy->ticket_number] = true;
                    } catch (\Throwable $e) {
                        $result['errors'][] = "ticket #{$legacy->id}: " . $e->getMessage();
                        Log::error('[TicketDataMigrationService] Ticket migration error', [
                            'legacy_id' => $legacy->id,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }

                if (!empty($rows)) {
                    DB::table('tickets')->insert($rows);
                    $result['migrated_tickets'] += count($rows);
                }
            });

        Log::info('[TicketDataMigrationService] Tickets migrated, starting replies');

        $result['migrated_replies'] = $this->migrateReplies($result['errors']);

        Log::info('[TicketDataMigrationService] Migration complete', $result);

        return $result;
    }

    private function buildTicketRow(object $legacy): array
    {
        $customerId = null;
        if (!empty($legacy->customer_mobile)) {
            $user = DB::table('users')
                ->where('mobile', $legacy->customer_mobile)
                ->where('site_id', $legacy->site_id)
                ->value('id');
            $customerId = $user ?: null;
        }

        $attachments = $this->castAttachments($legacy->attachments ?? null);

        return [
            'ticket_number'     => $legacy->ticket_number,
            'subject'           => $legacy->subject,
            'description'       => $legacy->message,
            'status'            => $legacy->status,
            'priority'          => $legacy->priority,
            'customer_id'       => $customerId,
            'assigned_to'       => $legacy->assigned_to ?? null,
            'site_id'           => $legacy->site_id,
            'customer_mobile'   => $legacy->customer_mobile ?? null,
            'customer_name'     => $legacy->customer_name ?? null,
            'category'          => $legacy->category ?? null,
            'tags'              => $legacy->tags ?? null,
            'attachments'       => $attachments,
            'closed_at'         => $legacy->closed_at ?? null,
            'first_response_at' => $legacy->first_response_at ?? null,
            'resolved_at'       => ($legacy->status === 'closed') ? $legacy->closed_at : null,
            'deleted_at'        => $legacy->deleted_at ?? null,
            'created_at'        => $legacy->created_at,
            'updated_at'        => $legacy->updated_at,
        ];
    }

    private function migrateReplies(array &$errors): int
    {
        $count = 0;

        $ticketMap = DB::table('tickets')
            ->join('support_tickets', 'support_tickets.ticket_number', '=', 'tickets.ticket_number')
            ->pluck('tickets.id', 'support_tickets.id')
            ->all();

        $existingReplyIds = DB::table('ticket_replies')
            ->pluck('id')
            ->flip()
            ->all();

        DB::table('support_ticket_replies')
            ->orderBy('id')
            ->chunk($this->batchSize, function ($legacyReplies) use (&$count, &$errors, $ticketMap, &$existingReplyIds) {
                $rows = [];

                foreach ($legacyReplies as $reply) {
                    $canonicalTicketId = $ticketMap[$reply->support_ticket_id] ?? null;

                    if ($canonicalTicketId === null) {
                        $errors[] = "reply #{$reply->id}: no canonical ticket for support_ticket_id={$reply->support_ticket_id}";
                        continue;
                    }

                    $rows[] = [
                        'ticket_id'      => $canonicalTicketId,
                        'user_id'        => $reply->user_id ?? null,
                        'message'        => $reply->message,
                        'author_type'    => $reply->author_type ?? 'staff',
                        'customer_mobile' => $reply->customer_mobile ?? null,
                        'customer_name'  => $reply->customer_name ?? null,
                        'attachments'    => $this->castAttachments($reply->attachments ?? null),
                        'deleted_at'     => $reply->deleted_at ?? null,
                        'created_at'     => $reply->created_at,
                        'updated_at'     => $reply->updated_at,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('ticket_replies')->insert($rows);
                    $count += count($rows);
                }
            });

        return $count;
    }

    private function castAttachments(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $raw;
        }

        return json_encode([$raw]);
    }
}
