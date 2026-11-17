<?php

namespace App\Services\CustomerInstallation\Dns;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;

/**
 * The public IPv4 address visitors and the ACME CA must reach a target
 * server at: the address customer DNS records point to.
 *
 * It is deliberately never the server's outbound address. Installation 50
 * (2026-11-17) asked the server `curl api.ipify.org`, got its upstream NAT
 * (205.252.135.x, a different value on each call), and pointed the A record
 * there. eth0 is 87.107.12.81, so Let's Encrypt timed out. The address the
 * platform itself connects to (CustomerRemoteServer::host) is reachable by
 * definition, so it wins. Otherwise a public address bound to an interface
 * on the server is used.
 */
class ServerPublicIpResolver
{
    public function __construct(private readonly DnsResolver $dns) {}

    /** Without SSH: the server record's host (IP, or hostname resolved publicly). */
    public function forServer(?CustomerRemoteServer $server): ?string
    {
        if ($server === null) {
            return null;
        }

        $host = trim((string) $server->host);
        $host = (string) (parse_url(str_contains($host, '://') ? $host : "ssh://{$host}", PHP_URL_HOST) ?: $host);
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIpv4($host) ? $host : null;
        }

        if ($host === '' || !str_contains($host, '.')) {
            return null;
        }

        foreach ($this->dns->lookupA($host)->ips as $ip) {
            if (self::isPublicIpv4($ip)) {
                return $ip;
            }
        }

        return null;
    }

    /** During the pipeline: forServer(), then a public address bound on the server. */
    public function forContext(InstallationContext $ctx): ?string
    {
        $ip = $this->forServer(CustomerRemoteServer::find($ctx->installation->remote_server_id));
        if ($ip !== null) {
            return $ip;
        }

        try {
            $result = $ctx->connection->execute("ip -4 -o addr show scope global 2>/dev/null | awk '{print \$4}'");
        } catch (\Throwable) {
            return null;
        }

        foreach (preg_split('/\s+/', trim((string) $result->stdout)) ?: [] as $cidr) {
            $ip = explode('/', $cidr)[0];
            if (self::isPublicIpv4($ip)) {
                return $ip;
            }
        }

        return null;
    }

    public static function isPublicIpv4(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
