<?php

namespace App\Services\Infrastructure;

/**
 * Explicit allowlist of customer_site tables with a proven tenant-scoping
 * path, and the WHERE clause that expresses it.
 *
 * customer_site is a single shared database — most of its ~126 tables were
 * never given a site_id column (or any FK path to one). Only these 14 are
 * provably resolvable to a single tenant today. Every other table is
 * intentionally NOT backed up here rather than guessed at with an invalid
 * global --where; see docs from the 2026-08 tenant-backup-integrity audit.
 *
 * Listed parent-before-child for readability; import order doesn't matter
 * — mysqldump's own FOREIGN_KEY_CHECKS=0/1 preamble/postamble makes each
 * table's CREATE TABLE + INSERT block self-contained regardless of order.
 */
class TenantScopedTableResolver
{
    private const TABLES = [
        'sites'                   => 'id = :siteId',
        'menus'                   => 'site_id = :siteId',
        'menu_items'              => 'menu_id IN (SELECT id FROM menus WHERE site_id = :siteId)',
        'menu_revisions'          => 'menu_id IN (SELECT id FROM menus WHERE site_id = :siteId)',
        'menu_change_logs'        => 'menu_id IN (SELECT id FROM menus WHERE site_id = :siteId)',
        'tickets'                 => 'site_id = :siteId',
        'ticket_replies'          => 'ticket_id IN (SELECT id FROM tickets WHERE site_id = :siteId)',
        'ticket_activities'       => 'ticket_id IN (SELECT id FROM tickets WHERE site_id = :siteId)',
        'ticket_votes'            => 'ticket_id IN (SELECT id FROM tickets WHERE site_id = :siteId)',
        'ticket_fr_subscribers'   => 'ticket_id IN (SELECT id FROM tickets WHERE site_id = :siteId)',
        'sms_campaigns'           => 'site_id = :siteId',
        'sms_campaign_recipients' => 'campaign_id IN (SELECT id FROM sms_campaigns WHERE site_id = :siteId)',
        'theme_settings'          => 'site_id = :siteId',
        'form_templates'          => 'site_id = :siteId',
    ];

    /** @return list<string> */
    public function tables(): array
    {
        return array_keys(self::TABLES);
    }

    public function isKnownTable(string $table): bool
    {
        return array_key_exists($table, self::TABLES);
    }

    public function whereClauseFor(string $table, int $siteId): string
    {
        if (!$this->isKnownTable($table)) {
            throw new \InvalidArgumentException("No tenant-scope resolution registered for table '{$table}'.");
        }

        return str_replace(':siteId', (string) $siteId, self::TABLES[$table]);
    }
}
