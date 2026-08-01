<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Site;

/**
 * Phase 20 — Part 9: crm_*.site_id carries a real DB foreign key to
 * `sites` (matching the Tickets module's own migrations, unlike SEO
 * Advanced's unconstrained site_id) — any test inserting a CRM row with
 * an explicit site_id needs a real Site row first. Factored out since
 * this exact helper was duplicated across several Phase 19/20 test
 * files (CrmTenantIsolationTest, CrmInstallationTest, ...).
 */
trait MakesCrmTestSites
{
    private function makeCrmTestSite(): Site
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        return Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
        ]);
    }
}
