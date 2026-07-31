<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Site;

/**
 * Minimal Customer + Site fixtures for Motion Graphics persistence
 * tests. No SiteFactory/CustomerFactory exists in this repo, so this
 * creates the smallest valid rows directly.
 */
trait CreatesMotionTestSite
{
    protected function createSite(string $title = 'Test Site'): Site
    {
        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'mobile' => '0912'.random_int(1000000, 9999999),
        ]);

        return Site::query()->create([
            'customer_id' => $customer->id,
            'title' => $title,
        ]);
    }
}
