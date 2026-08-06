<?php

namespace Tests\Unit\Services\Infrastructure;

use App\Services\Infrastructure\TenantScopedTableResolver;
use Tests\TestCase;

class TenantScopedTableResolverTest extends TestCase
{
    public function test_lists_exactly_the_fourteen_proven_tenant_scoped_tables(): void
    {
        $resolver = new TenantScopedTableResolver();

        $this->assertSame([
            'sites', 'menus', 'menu_items', 'menu_revisions', 'menu_change_logs',
            'tickets', 'ticket_replies', 'ticket_activities', 'ticket_votes', 'ticket_fr_subscribers',
            'sms_campaigns', 'sms_campaign_recipients', 'theme_settings', 'form_templates',
        ], $resolver->tables());
    }

    public function test_sites_resolves_via_self_anchor(): void
    {
        $resolver = new TenantScopedTableResolver();

        $this->assertSame('id = 42', $resolver->whereClauseFor('sites', 42));
    }

    /** @dataProvider directSiteIdTables */
    public function test_direct_site_id_tables_resolve_with_a_plain_equality_clause(string $table): void
    {
        $resolver = new TenantScopedTableResolver();

        $this->assertSame('site_id = 7', $resolver->whereClauseFor($table, 7));
    }

    public static function directSiteIdTables(): array
    {
        return [
            ['menus'], ['tickets'], ['sms_campaigns'], ['theme_settings'], ['form_templates'],
        ];
    }

    public function test_menu_children_resolve_through_a_menus_subquery(): void
    {
        $resolver = new TenantScopedTableResolver();

        foreach (['menu_items', 'menu_revisions', 'menu_change_logs'] as $table) {
            $this->assertSame(
                'menu_id IN (SELECT id FROM menus WHERE site_id = 3)',
                $resolver->whereClauseFor($table, 3)
            );
        }
    }

    public function test_ticket_children_resolve_through_a_tickets_subquery(): void
    {
        $resolver = new TenantScopedTableResolver();

        foreach (['ticket_replies', 'ticket_activities', 'ticket_votes', 'ticket_fr_subscribers'] as $table) {
            $this->assertSame(
                'ticket_id IN (SELECT id FROM tickets WHERE site_id = 3)',
                $resolver->whereClauseFor($table, 3)
            );
        }
    }

    public function test_sms_campaign_recipients_resolves_through_a_sms_campaigns_subquery(): void
    {
        $resolver = new TenantScopedTableResolver();

        $this->assertSame(
            'campaign_id IN (SELECT id FROM sms_campaigns WHERE site_id = 5)',
            $resolver->whereClauseFor('sms_campaign_recipients', 5)
        );
    }

    public function test_unknown_table_is_rejected_rather_than_silently_guessed(): void
    {
        $resolver = new TenantScopedTableResolver();

        $this->assertFalse($resolver->isKnownTable('users'));
        $this->assertFalse($resolver->isKnownTable('orders'));

        $this->expectException(\InvalidArgumentException::class);
        $resolver->whereClauseFor('users', 1);
    }
}
