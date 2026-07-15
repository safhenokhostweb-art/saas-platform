<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Enums\DriverType;

/**
 * Phase 14-F1 — single place that knows how a web root path is derived per
 * hosting type. InstallationContext::webRoot() delegates here so that no
 * Stage ever needs to branch on driver type itself (they only ever call
 * $ctx->webRoot()) — the same "never hardcode hosting-type checks outside
 * the capability/path layer" principle DriverCapabilities already documents
 * for capabilities.
 *
 * Resolution order:
 *   1. CustomerRemoteServer::$path, if the admin explicitly set one (this
 *      column already existed — added in Phase "PHASE-003D.5" for exactly
 *      this purpose, "web root on remote" — and the admin form already
 *      collects it for cpanel/directadmin targets — but nothing read it
 *      until now).
 *   2. Otherwise, a hosting-type default:
 *      - VPS/SSH/Docker/SFTP/unknown: /var/www/{slug} (unchanged behavior).
 *      - cPanel/DirectAdmin: /home/{cpanel_username}/public_html — the
 *        account's primary-domain document root. This is a simplification:
 *        an addon domain or subdomain may live at a different path
 *        (~/public_html/{subdomain} or ~/{domain}) — cPanel/WHM decides
 *        that when the domain is provisioned, which this pipeline does not
 *        yet do (see Phase 14-F audit, Part 4). Until that's implemented,
 *        an admin who needs a non-default path should set
 *        CustomerRemoteServer::path explicitly (resolution order #1).
 */
class WebRootResolver
{
    public function resolve(DeploymentDriverInterface $connection, string $slug, ?CustomerRemoteServer $server): string
    {
        if ($server && !empty($server->path)) {
            return rtrim($server->path, '/');
        }

        return match ($connection->driverType()) {
            DriverType::CPANEL, DriverType::DIRECTADMIN => $this->sharedHostingDefault($server, $slug),
            default => "/var/www/{$slug}",
        };
    }

    private function sharedHostingDefault(?CustomerRemoteServer $server, string $slug): string
    {
        $username = $server->username ?? null;

        // No username on record is not expected in practice (the admin form
        // requires it for cpanel/directadmin), but fail into a still-
        // deterministic, still-namespaced path rather than a generic one
        // that could collide across customers.
        return '/home/' . ($username !== null && $username !== '' ? $username : $slug) . '/public_html';
    }
}
