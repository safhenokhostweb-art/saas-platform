<?php

namespace Tests\Unit\Services\CustomerInstallation\Dns;

use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\PublicDnsResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicDnsResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('customer_installation.dns.doh_unreachable');
    }

    public function test_a_records_are_collected_and_the_cname_is_reported(): void
    {
        Http::fake(['dns.google/*' => Http::response(['Status' => 0, 'Answer' => [
            ['name' => 'shop.example.ir.', 'type' => 5, 'data' => 'Edge.Host.Example.'],
            ['name' => 'edge.host.example.', 'type' => 1, 'data' => '87.107.12.81'],
            ['name' => 'edge.host.example.', 'type' => 1, 'data' => '5.6.7.8'],
        ]])]);

        $lookup = (new PublicDnsResolver())->lookupA('shop.example.ir');

        $this->assertSame(DnsLookup::RESOLVED, $lookup->status);
        $this->assertSame(['5.6.7.8', '87.107.12.81'], $lookup->ips);
        $this->assertSame('edge.host.example', $lookup->cname);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'name=shop.example.ir') && str_contains($request->url(), 'type=A'));
    }

    public function test_nxdomain_is_a_definitive_not_found(): void
    {
        Http::fake(['dns.google/*' => Http::response(['Status' => 3])]);

        $this->assertSame(DnsLookup::NOT_FOUND, (new PublicDnsResolver())->lookupA('nope.example.ir')->status);
    }

    public function test_noerror_without_a_records_is_not_found(): void
    {
        Http::fake(['dns.google/*' => Http::response(['Status' => 0, 'Answer' => [
            ['name' => 'x.example.ir.', 'type' => 16, 'data' => '"txt"'],
        ]])]);

        $this->assertSame(DnsLookup::NOT_FOUND, (new PublicDnsResolver())->lookupA('x.example.ir')->status);
    }

    public function test_servfail_is_inconclusive(): void
    {
        Http::fake(['dns.google/*' => Http::response(['Status' => 2])]);

        $this->assertSame(DnsLookup::UNKNOWN, (new PublicDnsResolver())->lookupA('x.example.ir')->status);
    }

    /** Production (2026-11-17): dns.google times out from the server, the system resolver works. */
    public function test_unreachable_doh_is_remembered_so_later_lookups_skip_it(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('cURL error 28: Connection timed out after 2001 milliseconds');
        });

        $first = (new PublicDnsResolver())->lookupA('first.invalid');
        (new PublicDnsResolver())->lookupA('second.invalid');
        (new PublicDnsResolver())->lookupA('third.invalid');

        $this->assertSame(1, $attempts, 'only the first lookup may try DoH');
        $this->assertTrue((bool) Cache::get('customer_installation.dns.doh_unreachable'));
        $this->assertNotSame(DnsLookup::RESOLVED, $first->status, 'the system resolver answered instead (.invalid never resolves)');
    }
}
