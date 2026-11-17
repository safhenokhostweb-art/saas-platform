<?php

namespace App\Services\CustomerInstallation\Dns;

final class DnsPreflightResult
{
    /** @param list<string> $notes */
    private function __construct(
        public readonly bool $ok,
        public readonly string $code,
        public readonly ?string $error,
        public readonly string $domain,
        public readonly ?string $serverIp,
        public readonly DnsLookup $lookup,
        public readonly bool $managedByPlatform,
        public readonly array $notes = [],
    ) {}

    /** @param list<string> $notes */
    public static function pass(string $code, string $domain, ?string $serverIp, DnsLookup $lookup, bool $managed, array $notes = []): self
    {
        return new self(true, $code, null, $domain, $serverIp, $lookup, $managed, $notes);
    }

    public static function fail(string $code, string $error, string $domain, ?string $serverIp, DnsLookup $lookup, bool $managed): self
    {
        return new self(false, $code, $error, $domain, $serverIp, $lookup, $managed);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ok'                  => $this->ok,
            'code'                => $this->code,
            'domain'              => $this->domain,
            'server_ip'           => $this->serverIp,
            'dns_status'          => $this->lookup->status,
            'resolved_ips'        => $this->lookup->ips,
            'cname'               => $this->lookup->cname,
            'managed_by_platform' => $this->managedByPlatform,
            'notes'               => $this->notes,
        ];
    }
}
