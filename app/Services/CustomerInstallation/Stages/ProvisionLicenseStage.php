<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Models\Customer;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Models\SiteResourceLimit;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Env\RemoteEnvFile;
use App\Services\LicenseServerService;
use App\Support\IranianMobileNumber;

class ProvisionLicenseStage extends AbstractStage
{
    public function name(): string { return 'PROVISION_LICENSE'; }
    public function label(): string { return 'صدور لایسنس'; }
    public function estimatedSeconds(): int { return 5; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $req = $ctx->request;

            // ADR-088: the onboarding wizard resolves (or creates) the
            // customer before dispatch and passes its id; one mobile owns
            // any number of sites. Older payloads still find-or-create by
            // mobile, but only by a real, normalised one: an empty phone
            // used to match every other phone-less customer and silently
            // merge unrelated sites onto one owner.
            $customer = $req->customerId !== null ? Customer::find($req->customerId) : null;
            if ($customer === null) {
                $mobile = IranianMobileNumber::normalize($req->phone);
                if ($mobile === null) {
                    return StageResult::failure(
                        $this->name(),
                        'A valid customer mobile number is required to attach the site to its owner.',
                        $this->elapsed()
                    );
                }
                $customer = Customer::firstOrCreate(
                    ['mobile' => $mobile],
                    ['name'   => $req->ownerName]
                );
            }

            $server = CustomerRemoteServer::find($ctx->installation->remote_server_id);
            $dbName = $ctx->getOutput('CREATE_DATABASE', 'db_name') ?? $req->dbName;
            $dbUser = $ctx->getOutput('CREATE_DATABASE', 'db_user') ?? $req->dbUser;
            $dbHost = $req->dbHost ?: '127.0.0.1';
            $dbPass = $req->dbPassword;

            $siteData = [
                'customer_id'      => $customer->id,
                'title'            => $req->company,
                'is_active'        => true,
                'lifecycle_status' => Site::LIFECYCLE_ACTIVE,
                'hosting_company'  => $server?->label ?? $server?->host,
                'db_name'          => $dbName,
                'db_user'          => $dbUser,
                'db_host'          => $dbHost,
            ];

            if (!empty($dbPass)) {
                $siteData['db_password_encrypted'] = app(CredentialVault::class)->encrypt($dbPass);
            }

            // Create or find the Site record using correct column names
            $site = Site::updateOrCreate(
                ['domain' => $req->domain],
                $siteData
            );

            // Link installation to site
            $ctx->installation->update(['site_id' => $site->id]);

            // Persist resource governance limits for this site
            $hardBytes = (int) ($req->diskGb * 1024 * 1024 * 1024);
            $softGb    = $req->softDiskGb ?? round($req->diskGb * 0.70, 2);
            $softBytes = (int) ($softGb * 1024 * 1024 * 1024);
            $ramBytes  = (int) ($req->ramMb * 1024 * 1024);

            SiteResourceLimit::updateOrCreate(
                ['site_id' => $site->id],
                [
                    'plan_name'             => $req->resourcePlan ?? 'basic',
                    'memory_limit_bytes'    => $ramBytes,
                    'disk_hard_limit_bytes' => $hardBytes,
                    'storage_limit_bytes'   => $hardBytes,
                    'disk_soft_limit_bytes' => $softBytes,
                    'cpu_cores_limit'       => (float) $req->cpuCores,
                    'bandwidth_limit_bytes' => (int) ($req->bandwidthGb * 1024 * 1024 * 1024),
                ]
            );

            // Issue the license via LicenseServerService so that a PlatformLicense record
            // is created with the correct HMAC-SHA256 hash. Without this record the
            // customer-site's EnsureLicenseValid middleware gets "invalid_key" from the
            // license validate API and the admin panel becomes inaccessible.
            $licenseKey = app(LicenseServerService::class)->issue(
                $site,
                $req->domain,
                null,          // no expiry by default
                'CIE-INSTALL'  // product SKU for CIE-provisioned installs
            );

            // Also store the key on the Site record for quick cross-reference.
            $site->update(['license_key' => $licenseKey]);

            // Write LICENSE_KEY into the remote .env as an upsert: replaced
            // whatever the line holds now (the __LICENSE_KEY__ placeholder,
            // empty, quoted) or appended when absent.
            //
            // Installation 52 (2026-11-17): the old check grepped for
            // `^LICENSE_KEY=CIE-`, but since 67cb40ed (2026-06-29) keys come
            // from LicenseServerService::issue() as XXXX-XXXX-XXXX-XXXX.
            // So it reported "placeholder replacement failed" even when the
            // replacement worked, and no installation passed this stage.
            // Verification is now the value itself, decoded as Laravel will.
            $envPath = $ctx->webRoot() . '/.env';
            $set = $ctx->connection->execute(RemoteEnvFile::set($envPath, 'LICENSE_KEY', $licenseKey));
            if ($set->failed() || str_contains((string) $set->stdout, RemoteEnvFile::SET_FAILED)) {
                return StageResult::failure(
                    $this->name(),
                    'کلید لایسنس (LICENSE_KEY) در فایل .env سایت نوشته نشد: ' . trim($set->output()),
                    $this->elapsed()
                );
            }

            $written = RemoteEnvFile::decode((string) $ctx->connection->execute(RemoteEnvFile::read($envPath, ['LICENSE_KEY']))->stdout);
            if ($written !== [] && ($written['LICENSE_KEY'] ?? null) !== $licenseKey) {
                return StageResult::failure(
                    $this->name(),
                    'کلید لایسنس (LICENSE_KEY) که در فایل .env سایت خوانده می‌شود با کلید صادرشده یکسان نیست.',
                    $this->elapsed()
                );
            }

            // Write PLATFORM_SITE_ID into .env so customer media and site services function
            $setSiteId = $ctx->connection->execute(RemoteEnvFile::set($envPath, 'PLATFORM_SITE_ID', (string) $site->id));
            if ($setSiteId->failed() || str_contains((string) $setSiteId->stdout, RemoteEnvFile::SET_FAILED)) {
                return StageResult::failure(
                    $this->name(),
                    'شناسه سایت (PLATFORM_SITE_ID) در فایل .env سایت نوشته نشد: ' . trim($setSiteId->output()),
                    $this->elapsed()
                );
            }

            return StageResult::success($this->name(), [
                'site_id'            => $site->id,
                'license_key_prefix' => substr($licenseKey, 0, 8),
                'domain'             => $req->domain,
                'license_stored'     => true,
            ], [
                'site_id' => $site->id,
            ], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['site_id'])) {
            try {
                Site::find($snapshot['site_id'])?->delete();
            } catch (\Throwable) {}
        }
        $ctx->installation->update(['site_id' => null]);
    }
}
