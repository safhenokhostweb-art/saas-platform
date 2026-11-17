<?php

namespace Tests\Unit\Services\CustomerInstallation\Dns;

use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

/**
 * 2026-11-17, installation 50: solar.safhenokhost.ir ran 18 stages and only
 * failed at ISSUE_SSL, because its A record pointed at the server's outbound
 * NAT address. These cases pin what the DNS preflight lets through before
 * anything is uploaded or created.
 */
class DomainDnsPreflightTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_IP = '87.107.12.81';

    private function preflight(FakeDnsResolver $dns): DomainDnsPreflight
    {
        return new DomainDnsPreflight($dns);
    }

    private function arvanManaged(bool $proxy = false): void
    {
        DnsProviderSetting::create([
            'provider' => 'arvan', 'root_domain' => 'safhenokhost.ir', 'is_active' => true,
            'cloud_proxy' => $proxy, 'api_token_encrypted' => 'token-present',
        ]);
    }

    public function test_custom_domain_without_any_record_is_rejected_with_persian_message(): void
    {
        $result = $this->preflight(new FakeDnsResolver(DnsLookup::notFound()))
            ->check('shop.example.ir', self::SERVER_IP, true);

        $this->assertFalse($result->ok);
        $this->assertSame(DomainDnsPreflight::FAIL_NOT_REGISTERED, $result->code);
        $this->assertStringContainsString('هنوز در DNS ثبت نشده است', $result->error);
        $this->assertStringContainsString(self::SERVER_IP, $result->error);
    }

    public function test_custom_domain_pointing_elsewhere_is_rejected_when_ssl_is_requested(): void
    {
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->set('shop.example.ir', DnsLookup::resolved(['205.252.135.188']));

        $result = $this->preflight($dns)->check('shop.example.ir', self::SERVER_IP, true);

        $this->assertFalse($result->ok);
        $this->assertSame(DomainDnsPreflight::FAIL_WRONG_IP, $result->code);
        $this->assertStringContainsString('به IP این سرور متصل نیست', $result->error);
        $this->assertStringContainsString('205.252.135.188', $result->error);
        $this->assertStringContainsString('SSL', $result->error);
    }

    public function test_custom_domain_pointing_elsewhere_is_allowed_without_ssl(): void
    {
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->set('shop.example.ir', DnsLookup::resolved(['5.6.7.8']));

        $result = $this->preflight($dns)->check('shop.example.ir', self::SERVER_IP, false);

        $this->assertTrue($result->ok);
        $this->assertSame(DomainDnsPreflight::PASS_NO_SSL_ELSEWHERE, $result->code);
    }

    public function test_custom_domain_pointing_to_the_server_passes(): void
    {
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))
            ->set('shop.example.ir', DnsLookup::resolved([self::SERVER_IP], 'edge.host.example'));

        $result = $this->preflight($dns)->check('https://Shop.Example.ir/', self::SERVER_IP, true);

        $this->assertTrue($result->ok, (string) $result->error);
        $this->assertSame(DomainDnsPreflight::PASS_POINTS_HERE, $result->code);
        $this->assertSame('shop.example.ir', $result->domain);
    }

    public function test_custom_domain_behind_arvan_cdn_edge_passes_with_ssl(): void
    {
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))
            ->set('shop.example.ir', DnsLookup::resolved(['185.143.233.120', '94.101.182.5']));

        $result = $this->preflight($dns)->check('shop.example.ir', self::SERVER_IP, true);

        $this->assertTrue($result->ok, (string) $result->error);
        $this->assertNotEmpty($result->notes);
    }

    public function test_wildcard_only_answer_is_not_mistaken_for_a_registered_subdomain(): void
    {
        // *.example.ir answers every name with a parking IP: the subdomain has no record of its own.
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->setWildcard('example.ir', DnsLookup::resolved(['5.6.7.8']));

        $result = $this->preflight($dns)->check('solar.example.ir', self::SERVER_IP, false);

        $this->assertFalse($result->ok);
        $this->assertSame(DomainDnsPreflight::FAIL_WILDCARD_ONLY, $result->code);
        $this->assertStringContainsString('Wildcard', $result->error);
        $this->assertStringContainsString('*.example.ir', $result->error);
        $this->assertCount(2, $dns->queries, 'the domain and one random sibling probe');
        $this->assertStringStartsWith('cie-dns-probe-', $dns->queries[1]);
    }

    public function test_wildcard_that_points_to_the_server_is_accepted(): void
    {
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->setWildcard('example.ir', DnsLookup::resolved([self::SERVER_IP]));

        $result = $this->preflight($dns)->check('solar.example.ir', self::SERVER_IP, true);

        $this->assertTrue($result->ok, (string) $result->error);
    }

    public function test_explicit_record_differing_from_the_wildcard_is_judged_on_its_own(): void
    {
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))
            ->setWildcard('example.ir', DnsLookup::resolved(['5.6.7.8']))
            ->set('solar.example.ir', DnsLookup::resolved([self::SERVER_IP]));

        $this->assertTrue($this->preflight($dns)->check('solar.example.ir', self::SERVER_IP, true)->ok);
    }

    public function test_unreachable_resolver_does_not_block(): void
    {
        $result = $this->preflight(new FakeDnsResolver(DnsLookup::unknown()))->check('shop.example.ir', self::SERVER_IP, true);

        $this->assertTrue($result->ok);
        $this->assertSame(DomainDnsPreflight::PASS_UNVERIFIED, $result->code);
    }

    public function test_platform_subdomain_passes_without_any_public_lookup_because_configure_dns_creates_it(): void
    {
        $this->arvanManaged();
        $dns = new FakeDnsResolver(DnsLookup::notFound());

        $result = $this->preflight($dns)->check('solar.safhenokhost.ir', self::SERVER_IP, true);

        $this->assertTrue($result->ok, (string) $result->error);
        $this->assertTrue($result->managedByPlatform);
        $this->assertSame(DomainDnsPreflight::PASS_MANAGED, $result->code);
        $this->assertSame(DnsLookup::NOT_QUERIED, $result->lookup->status);
        // A negative answer cached by the resolver would hide the record CONFIGURE_DNS creates next.
        $this->assertSame([], $dns->queries);
    }

    public function test_platform_subdomain_needs_a_known_server_ip(): void
    {
        $this->arvanManaged();

        $result = $this->preflight(new FakeDnsResolver(DnsLookup::notFound()))
            ->check('solar.safhenokhost.ir', null, true);

        $this->assertFalse($result->ok);
        $this->assertSame(DomainDnsPreflight::FAIL_NO_SERVER_IP, $result->code);
        $this->assertStringContainsString('IP عمومی سرور مقصد مشخص نیست', $result->error);
    }

    public function test_platform_subdomain_under_a_wildcard_is_not_judged_by_the_wildcard(): void
    {
        $this->arvanManaged(proxy: true);
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->setWildcard('safhenokhost.ir', DnsLookup::resolved(['5.6.7.8']));

        $this->assertTrue($this->preflight($dns)->check('solar.safhenokhost.ir', self::SERVER_IP, true)->ok);
        $this->assertSame([], $dns->queries);
    }

    public function test_inactive_provider_makes_platform_subdomains_follow_custom_domain_rules(): void
    {
        DnsProviderSetting::create(['provider' => 'arvan', 'root_domain' => 'safhenokhost.ir', 'is_active' => true]);

        $result = $this->preflight(new FakeDnsResolver(DnsLookup::notFound()))
            ->check('solar.safhenokhost.ir', self::SERVER_IP, true);

        $this->assertFalse($result->ok, 'without a ready provider nothing will create the record');
        $this->assertSame(DomainDnsPreflight::FAIL_NOT_REGISTERED, $result->code);
    }

    public function test_arvan_edge_membership_uses_the_official_ranges(): void
    {
        $this->assertTrue(DomainDnsPreflight::isArvanEdge('185.143.235.255'));
        $this->assertTrue(DomainDnsPreflight::isArvanEdge('94.101.182.31'));
        $this->assertFalse(DomainDnsPreflight::isArvanEdge('94.101.182.32'));
        $this->assertFalse(DomainDnsPreflight::isArvanEdge('205.252.135.188'));
        $this->assertFalse(DomainDnsPreflight::isArvanEdge('not-an-ip'));
    }
}
