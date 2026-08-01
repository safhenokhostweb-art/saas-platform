<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Phase 20 — Part 2: Customer Portal (Site) controllers resolve "my
 * site" from the authenticated user, exactly like Tickets'
 * TicketPortalController does (auth()->user()->sites()->first()) — this
 * is a WEB request, not the tenant_site API context SiteTenantScope
 * covers, so it must be resolved explicitly here (same reasoning as
 * FiltersCrmListings' docblock).
 */
trait ResolvesCurrentSite
{
    protected function currentSiteId(Request $request): ?int
    {
        $user = $request->user();

        if ($user && method_exists($user, 'sites')) {
            $site = $user->sites()->where('is_active', true)->first();

            return $site?->id;
        }

        return null;
    }
}
