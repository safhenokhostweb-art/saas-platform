<?php

namespace Tests\Unit\Services\CustomerInstallation\Dns;

use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\PublicDnsResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicDnsResolverTest extends TestCase
{
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
}
