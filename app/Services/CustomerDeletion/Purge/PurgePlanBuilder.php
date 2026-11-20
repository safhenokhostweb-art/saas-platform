<?php

namespace App\Services\CustomerDeletion\Purge;

use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Guards\DatabaseNameGuard;
use App\Services\CustomerDeletion\Guards\DomainGuard;
use App\Services\CustomerDeletion\Guards\ObjectPrefixGuard;
use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;
use App\Services\CustomerDeletion\Guards\RemotePathGuard;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\WebRootResolver;

/**
 * ADR-090 — the immutable inventory of a site purge.
 *
 * Built once for the dry-run preview and again on submit; the two must hash
 * identically, so the operator confirms exactly what will run. The stored
 * plan is then the only source of destructive targets: DeleteContext reads
 * web root, database names and certificate names from it instead of
 * re-resolving live (mutable) server configuration.
 *
 * Every target carries an action:
 *   delete — passed its guard and will be removed automatically;
 *   manual — failed a guard or cannot be removed safely by the platform;
 *            the run skips it and lists it as "manual action required".
 */
class PurgePlanBuilder
{
    public const VERSION = 1;

    private const VPS_TYPES = ['ssh', 'docker', 'fake'];

    public function __construct(private readonly WebRootResolver $webRoots = new WebRootResolver()) {}

    /** The installation a site purge acts on: the newest one not already deleted. */
    public static function installationFor(Site $site): ?CustomerInstallation
    {
        return CustomerInstallation::query()
            ->where('site_id', $site->id)
            ->where('status', '!=', 'deleted')
            ->orderByDesc('id')
            ->first();
    }

    public function forSite(Site $site, bool $deleteBackups, bool $deleteMedia): array
    {
        $installation = self::installationFor($site);

        $plan = $installation
            ? $this->forInstallation($installation, $deleteBackups, $deleteMedia, $site)
            : $this->emptyPlan($site, $deleteBackups, $deleteMedia);

        if (!$installation) {
            $plan['blockers'][] = 'این سایت هیچ نصب فعالی روی سرور مقصد ندارد؛ حذف کامل ممکن نیست. از «حذف از پنل» استفاده کنید.';
        }

        return $plan;
    }

    public function forInstallation(CustomerInstallation $installation, bool $deleteBackups, bool $deleteMedia, ?Site $site = null): array
    {
        $site ??= $installation->site_id ? Site::find($installation->site_id) : null;
        $server = CustomerRemoteServer::find($installation->remote_server_id);
        $slug   = DeleteContext::resolveSlug($installation);
        $type   = (string) ($server?->type ?? '');
        $isVps  = in_array($type, self::VPS_TYPES, true);
        $siteId = $site?->id ?? $installation->site_id;

        [$dbName, $dbUser] = DeleteContext::resolveDbCredentials($installation);

        $plan = [
            'version'      => self::VERSION,
            'site'         => [
                'id'     => $siteId,
                'title'  => $site?->title,
                'domain' => $site?->domain ?? $installation->customer_domain,
            ],
            'installation' => [
                'id'     => $installation->id,
                'slug'   => $slug,
                'status' => $installation->status,
            ],
            'server'       => $server ? [
                'id'    => $server->id,
                'label' => $server->label,
                'host'  => $server->host,
                'type'  => $server->type,
            ] : null,
            'web_root'     => $this->webRoot($installation, $server, $slug, $isVps),
            'nginx'        => $isVps ? [
                'available' => "/etc/nginx/sites-available/{$slug}",
                'enabled'   => "/etc/nginx/sites-enabled/{$slug}",
                'action'    => 'delete',
            ] : ['action' => 'not_applicable', 'reason' => 'vhost توسط پنل هاست مدیریت می‌شود'],
            'services'     => $isVps ? [
                'cron'       => "/etc/cron.d/{$slug}",
                'supervisor' => "/etc/supervisor/conf.d/{$slug}_worker.conf",
            ] : [],
            'database'     => [
                'name'   => $dbName,
                'user'   => $dbUser,
                'action' => $this->databaseAction($dbName, $dbUser),
            ],
            'certificates' => $isVps
                ? $this->certificates($installation, $site)
                : [],
            'backups'      => [
                'delete'       => $deleteBackups,
                'final_backup' => !$deleteBackups,
                'prefixes'     => $siteId ? ObjectPrefixGuard::backupPrefixes((int) $siteId) : [],
            ],
            'media'        => [
                'delete'   => $deleteMedia,
                'prefixes' => $siteId ? ObjectPrefixGuard::mediaPrefixes((int) $siteId) : [],
            ],
            'platform'     => [
                // platform_licenses keeps only license_key_prefix/hash; rows belong to the site via site_id.
                'licenses'     => $siteId ? PlatformLicense::where('site_id', $siteId)->count() : 0,
                'site_domains' => $siteId ? SiteDomain::where('site_id', $siteId)->count() : 0,
                'customer'     => 'retained',
            ],
            'blockers'     => [],
        ];

        if (!$server) {
            $plan['blockers'][] = 'سرور مقصد این نصب دیگر وجود ندارد.';
        }
        if (in_array($installation->status, ['pending', 'running'], true)) {
            $plan['blockers'][] = 'نصب این سایت در حال اجراست؛ ابتدا آن را متوقف کنید.';
        }
        if (($deleteBackups || $deleteMedia) && !$siteId) {
            $plan['blockers'][] = 'شناسهٔ سایت برای حذف بکاپ/مدیای آروان معلوم نیست.';
        }
        $active = CustomerDeletion::where('installation_id', $installation->id)
            ->whereIn('status', ['pending', 'running', 'failed'])
            ->first();
        if ($active) {
            $plan['blockers'][] = "یک فرآیند حذف (#{$active->id}) برای این نصب از قبل باز است؛ از صفحهٔ پیشرفت آن ادامه دهید.";
        }

        return $plan;
    }

    public static function hash(array $plan): string
    {
        return hash('sha256', json_encode(self::sortKeys($plan), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Everything the run will leave for a human, for the preview. */
    public static function manualItems(array $plan): array
    {
        $items = [];
        if (($plan['web_root']['action'] ?? null) === 'manual') {
            $items[] = 'پوشهٔ سایت: ' . ($plan['web_root']['path'] ?? '?') . ' — ' . ($plan['web_root']['reason'] ?? '');
        }
        if (($plan['database']['action'] ?? null) === 'manual') {
            $items[] = 'پایگاه داده: ' . ($plan['database']['reason'] ?? '');
        }
        foreach ($plan['certificates'] ?? [] as $cert) {
            if ($cert['action'] === 'manual') {
                $items[] = 'گواهی ' . $cert['name'] . ': ' . $cert['reason'];
            }
        }

        return $items;
    }

    private function webRoot(CustomerInstallation $installation, ?CustomerRemoteServer $server, string $slug, bool $isVps): array
    {
        $recorded = CustomerInstallationLog::where('installation_id', $installation->id)
            ->where('stage', 'EXTRACT_PACKAGE')
            ->where('event', 'completed')
            ->orderByDesc('id')
            ->first()?->output['web_root'] ?? null;

        $driverType = DriverType::tryFrom((string) $server?->type) ?? DriverType::SSH;
        $path       = is_string($recorded) && $recorded !== ''
            ? rtrim($recorded, '/')
            : $this->webRoots->resolveForDriverType($driverType, $slug, $server);
        $source     = is_string($recorded) && $recorded !== '' ? 'install_log' : 'resolver';

        try {
            if ($isVps) {
                RemotePathGuard::assertVpsSiteRoot($path, $slug);
            } else {
                RemotePathGuard::assertSharedHostingSiteRoot($path, (string) $server?->username);
                if ($source !== 'install_log') {
                    throw new PurgeGuardViolation('مسیر در لاگ نصب ثبت نشده و از تنظیمات فعلی سرور حدس زده شده است.');
                }
                if ($this->sharedWithAnotherInstallation($installation, $path)) {
                    throw new PurgeGuardViolation('نصب دیگری روی همین سرور از همین مسیر استفاده می‌کند.');
                }
            }
        } catch (PurgeGuardViolation $e) {
            return ['path' => $path, 'source' => $source, 'action' => 'manual', 'reason' => $e->getMessage()];
        }

        return ['path' => $path, 'source' => $source, 'action' => 'delete'];
    }

    private function sharedWithAnotherInstallation(CustomerInstallation $installation, string $path): bool
    {
        $others = CustomerInstallation::where('remote_server_id', $installation->remote_server_id)
            ->where('id', '!=', $installation->id)
            ->where('status', '!=', 'deleted')
            ->pluck('id');

        if ($others->isEmpty()) {
            return false;
        }

        return CustomerInstallationLog::whereIn('installation_id', $others)
            ->where('stage', 'EXTRACT_PACKAGE')
            ->where('event', 'completed')
            ->get()
            ->contains(fn ($log) => rtrim((string) ($log->output['web_root'] ?? ''), '/') === $path);
    }

    private function databaseAction(?string $dbName, ?string $dbUser): string
    {
        try {
            if ($dbName !== null) {
                DatabaseNameGuard::assertDatabase($dbName);
            }
            if ($dbUser !== null) {
                DatabaseNameGuard::assertUser($dbUser);
            }
        } catch (PurgeGuardViolation) {
            return 'manual';
        }

        return $dbName === null && $dbUser === null ? 'not_applicable' : 'delete';
    }

    private function certificates(CustomerInstallation $installation, ?Site $site): array
    {
        $names = array_filter([
            $installation->customer_domain,
            $site?->domain,
            ...($site ? SiteDomain::where('site_id', $site->id)->pluck('domain')->all() : []),
        ]);

        $out = [];
        foreach (array_values(array_unique(array_map(fn ($n) => strtolower(trim((string) $n)), $names))) as $name) {
            try {
                $out[] = ['name' => DomainGuard::assertCertificateName($name), 'action' => 'delete'];
            } catch (PurgeGuardViolation $e) {
                $out[] = ['name' => $name, 'action' => 'manual', 'reason' => $e->getMessage()];
            }
        }

        return $out;
    }

    private function emptyPlan(Site $site, bool $deleteBackups, bool $deleteMedia): array
    {
        return [
            'version'      => self::VERSION,
            'site'         => ['id' => $site->id, 'title' => $site->title, 'domain' => $site->domain],
            'installation' => null,
            'server'       => null,
            'backups'      => ['delete' => $deleteBackups, 'final_backup' => false, 'prefixes' => ObjectPrefixGuard::backupPrefixes($site->id)],
            'media'        => ['delete' => $deleteMedia, 'prefixes' => ObjectPrefixGuard::mediaPrefixes($site->id)],
            'blockers'     => [],
        ];
    }

    private static function sortKeys(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = self::sortKeys($v);
            }
        }

        return $value;
    }
}
