<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Services\LicenseServerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wave 5.4 — Regression tests for Wave 1 / P0-3 (license allow-list)
 * and Wave 2 / P0-5 (HMAC license hashing + lazy SHA256 upgrade).
 *
 * P0-3 — Domain allow-list:
 *   - PlatformLicense::isDomainAllowed() accepts the allowed domain
 *     and rejects others.
 *   - Allow-list is normalized (case-insensitive, trims scheme & trailing slash).
 *   - Empty allow-list means "any" (backwards compat behaviour).
 *   - LicenseServerService::validate() returns valid=false +
 *     error="domain_mismatch" when the request domain is not in the list.
 *
 * P0-5 — HMAC hashing + lazy upgrade:
 *   - issue() stores hash_hmac('sha256', key, app.key); legacy SHA256 hash
 *     is NOT used on newly issued keys.
 *   - findByKey() finds licenses by HMAC hash on first lookup.
 *   - findByKey() falls back to legacy SHA256 and lazy-rehashes the row
 *     to HMAC on success (transparent upgrade).
 *   - validate() accepts a legacy SHA256-hashed key and returns valid=true.
 */
class Wave2LicenseSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'تست مشتری لایسنس',
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'lic_' . uniqid() . '@example.com',
        ]);
    }

    private function makeSite(Customer $customer, string $domain = 'example.com'): Site
    {
        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت لایسنس',
            'domain' => $domain,
            'is_active' => true,
        ]);
    }

    // =====================================================================
    // P0-3 — PlatformLicense::isDomainAllowed()
    // =====================================================================

    public function test_allow_list_with_empty_value_accepts_any_domain(): void
    {
        $lic = new PlatformLicense();
        $lic->domain_allowed = null;
        $this->assertTrue($lic->isDomainAllowed('example.com'));
        $this->assertTrue($lic->isDomainAllowed('random.test'));

        $lic->domain_allowed = '';
        $this->assertTrue($lic->isDomainAllowed('example.com'));
    }

    public function test_allow_list_matches_case_insensitive_and_trims_scheme(): void
    {
        $lic = new PlatformLicense();
        $lic->domain_allowed = 'Example.com';

        $this->assertTrue($lic->isDomainAllowed('example.com'));
        $this->assertTrue($lic->isDomainAllowed('EXAMPLE.com'));
        $this->assertTrue($lic->isDomainAllowed('https://example.com'));
        $this->assertTrue($lic->isDomainAllowed('https://example.com/'));
    }

    public function test_allow_list_supports_multiple_domains_comma_separated(): void
    {
        $lic = new PlatformLicense();
        $lic->domain_allowed = 'foo.com, bar.com , baz.test';

        $this->assertTrue($lic->isDomainAllowed('foo.com'));
        $this->assertTrue($lic->isDomainAllowed('bar.com'));
        $this->assertTrue($lic->isDomainAllowed('baz.test'));
        $this->assertFalse($lic->isDomainAllowed('other.com'));
    }

    public function test_allow_list_rejects_unknown_domain(): void
    {
        $lic = new PlatformLicense();
        $lic->domain_allowed = 'mydomain.test';

        $this->assertFalse($lic->isDomainAllowed('attacker.test'));
        $this->assertFalse($lic->isDomainAllowed(''));
    }

    public function test_validate_returns_domain_mismatch_when_domain_not_in_list(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer, 'allowed.test');

        $service = app(LicenseServerService::class);
        $key = $service->issue($site, 'allowed.test', null, 'site_template');

        $bad = $service->validate($key, 'attacker.test');
        $this->assertFalse($bad['valid']);
        $this->assertSame('domain_mismatch', $bad['error']);

        $good = $service->validate($key, 'allowed.test');
        $this->assertTrue($good['valid']);
        $this->assertSame($site->id, $good['site_id']);
    }

    // =====================================================================
    // P0-5 — HMAC hashing + lazy SHA256 upgrade
    // =====================================================================

    public function test_issue_stores_hmac_sha256_hash_not_legacy_sha256(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer, 'hmac.test');

        $service = app(LicenseServerService::class);
        $key = $service->issue($site, 'hmac.test');

        $row = PlatformLicense::where('site_id', $site->id)->latest()->first();
        $this->assertNotNull($row);

        $appKey = (string) config('app.key');
        $expectedHmac = hash_hmac('sha256', $key, $appKey);
        $legacyHash = hash('sha256', $key);

        $this->assertSame($expectedHmac, $row->license_key_hash, 'new license must be stored with HMAC-SHA256 hash');
        $this->assertNotSame($legacyHash, $row->license_key_hash, 'new license must NOT use legacy SHA256 hash');
    }

    public function test_find_by_key_finds_hmac_issued_license(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer, 'find-hmac.test');
        $service = app(LicenseServerService::class);
        $key = $service->issue($site, 'find-hmac.test');

        $found = $service->findByKey($key);
        $this->assertNotNull($found);
        $this->assertSame($site->id, $found->site_id);
    }

    public function test_find_by_key_finds_legacy_sha256_license_and_upgrades_hash(): void
    {
        // Simulate a license issued with the OLD legacy SHA256 hashing.
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer, 'legacy.test');

        $key = 'AAAA-BBBB-CCCC-DDDD';
        $legacyHash = hash('sha256', $key);
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($key)), 0, 8);

        $license = PlatformLicense::create([
            'site_id' => $site->id,
            'license_key_prefix' => $prefix,
            'license_key_hash' => $legacyHash,
            'domain_allowed' => 'legacy.test',
            'expires_at' => null,
            'product_sku' => 'legacy_template',
            'issued_at' => now(),
        ]);

        // First lookup must succeed via the SHA256 fallback.
        $service = app(LicenseServerService::class);
        $found = $service->findByKey($key);

        $this->assertNotNull($found, 'legacy SHA256-hashed license must still be discoverable');
        $this->assertSame($license->id, $found->id);

        // After lookup, the row must be lazily upgraded to HMAC.
        $row = PlatformLicense::find($license->id);
        $expectedHmac = hash_hmac('sha256', $key, (string) config('app.key'));
        $this->assertSame($expectedHmac, $row->license_key_hash, 'legacy row must be lazy-upgraded to HMAC');
        $this->assertNotSame($legacyHash, $row->license_key_hash);

        // A second findByKey must still succeed (now using HMAC path).
        $foundAgain = $service->findByKey($key);
        $this->assertNotNull($foundAgain);
        $this->assertSame($license->id, $foundAgain->id);
    }

    public function test_validate_accepts_legacy_sha256_hashed_license(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer, 'legacy-validate.test');

        $key = 'LEGA-SHAX-XXXX-YYYY';
        $legacyHash = hash('sha256', $key);
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($key)), 0, 8);
        PlatformLicense::create([
            'site_id' => $site->id,
            'license_key_prefix' => $prefix,
            'license_key_hash' => $legacyHash,
            'domain_allowed' => 'legacy-validate.test',
            'expires_at' => null,
            'product_sku' => 'legacy_template',
            'issued_at' => now(),
        ]);

        $result = app(LicenseServerService::class)->validate($key, 'legacy-validate.test');
        $this->assertTrue($result['valid'], 'legacy-hashed license must validate successfully');
        $this->assertSame($site->id, $result['site_id']);
    }

    public function test_find_by_key_returns_null_for_unknown_key(): void
    {
        $service = app(LicenseServerService::class);
        $this->assertNull($service->findByKey('NOPE-NOPE-NOPE-NOPE'));
        $this->assertNull($service->findByKey('short'));
    }

    public function test_validate_returns_invalid_key_for_unknown_key(): void
    {
        $result = app(LicenseServerService::class)->validate('NOPE-NOPE-NOPE-NOPE', 'any.test');
        $this->assertFalse($result['valid']);
        $this->assertSame('invalid_key', $result['error']);
    }

    public function test_validate_returns_revoked_for_revoked_license(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer, 'revoked.test');
        $service = app(LicenseServerService::class);
        $key = $service->issue($site, 'revoked.test');

        $row = PlatformLicense::where('site_id', $site->id)->latest()->first();
        $row->update(['revoked_at' => now()]);

        $result = $service->validate($key, 'revoked.test');
        $this->assertFalse($result['valid']);
        $this->assertSame('revoked', $result['error']);
    }

    public function test_validate_returns_expired_for_expired_license(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer, 'expired.test');
        $service = app(LicenseServerService::class);
        $key = $service->issue($site, 'expired.test', now()->subDay());

        $result = $service->validate($key, 'expired.test');
        $this->assertFalse($result['valid']);
        $this->assertSame('expired', $result['error']);
    }
}
