<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Models\InstallationCredential;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Env\DotenvValue;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class GenerateEnvironmentStage extends AbstractStage
{
    public function __construct(private readonly CredentialVault $vault) {}

    public function name(): string { return 'GENERATE_ENVIRONMENT'; }
    public function label(): string { return 'تولید فایل محیطی'; }
    public function estimatedSeconds(): int { return 3; }
    public function canRollback(): bool { return true; }

    /** Quote a .env value only when it would not survive unquoted (see DotenvValue). */
    private static function envValue(string $value): string
    {
        return DotenvValue::encode($value);
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $req = $ctx->request;
            $webRoot = $ctx->webRoot();
            $envPath = $webRoot . '/.env';

            $appKey        = 'base64:' . base64_encode(random_bytes(32));
            $appUrl        = ($req->sslEnabled ? 'https' : 'http') . '://' . $req->domain;
            $platformUrl   = rtrim(config('app.url', 'https://panel.safhenokhost.ir'), '/');
            $adminPassword = Str::random(16);

            // Phase 14-E — capability-aware fallback (not a skip/fail: the
            // site can run correctly on file-based cache/session and a
            // non-Redis queue, just without Redis-backed performance).
            // Hardcoding redis here — as before this phase — wrote a config
            // that silently could never connect on any driver without
            // supportsRedis (cPanel, DirectAdmin, SFTP).
            //
            // Phase 14-G1 — queue driver must match what ConfigureQueueStage
            // actually provisions for this driver, not just what's fastest.
            // supportsQueue (Redis-backed persistent worker, VPS/Docker) ->
            // redis. supportsQueueWorker without supportsQueue (cron-based
            // `queue:work --stop-when-empty`, cPanel — see
            // CpanelQueueProvisioner) -> database: a cron worker polling a
            // `sync` connection never has anything to consume, since `sync`
            // runs jobs inline and never enqueues them at all. Neither
            // capability -> sync (no worker will ever run, so there's
            // nothing to poll a queue table for).
            $capabilities   = $ctx->connection->capabilities();
            $cacheDriver    = $capabilities->supportsRedis ? 'redis' : 'file';
            $sessionDriver  = $capabilities->supportsRedis ? 'redis' : 'file';
            $queueDriver    = match (true) {
                $capabilities->supportsQueue       => 'redis',
                $capabilities->supportsQueueWorker  => 'database',
                default                             => 'sync',
            };

            $envLines = [
                // User-supplied values go through envValue(): a raw `#`, `"`,
                // `$` or space would be cut or misread by phpdotenv.
                "APP_NAME=" . self::envValue((string) $req->company),
                "APP_ENV=production",
                "APP_KEY={$appKey}",
                "APP_DEBUG=false",
                "APP_URL={$appUrl}",
                "LOG_CHANNEL=stack",
                "LOG_LEVEL=error",
                "",
                "DB_CONNECTION=mysql",
                "DB_HOST=127.0.0.1",
                "DB_PORT=3306",
                "DB_DATABASE=__DB_NAME__",
                "DB_USERNAME=__DB_USER__",
                "DB_PASSWORD=__DB_PASS__",
                "",
                "BROADCAST_DRIVER=log",
                "CACHE_DRIVER={$cacheDriver}",
                "SESSION_DRIVER={$sessionDriver}",
                "QUEUE_CONNECTION={$queueDriver}",
                "REDIS_HOST=127.0.0.1",
                "REDIS_PORT=6379",
                "",
                "MAIL_MAILER=" . ($req->smtp['mailer'] ?? 'smtp'),
                "MAIL_HOST=" . ($req->smtp['host'] ?? ''),
                "MAIL_PORT=" . ($req->smtp['port'] ?? '587'),
                "MAIL_USERNAME=" . self::envValue((string) ($req->smtp['username'] ?? '')),
                "MAIL_PASSWORD=" . self::envValue((string) ($req->smtp['password'] ?? '')),
                "MAIL_ENCRYPTION=" . ($req->smtp['encryption'] ?? 'tls'),
                "",
                "PLATFORM_URL={$platformUrl}",
                "PLATFORM_SITE_ID=" . ($ctx->installation->site_id ? (string) $ctx->installation->site_id : ""),
                "LICENSE_KEY=__LICENSE_KEY__",
                "LICENSE_VERIFY_SSL=false",
                "FILESYSTEM_DISK=local",
                // ADR-088: the shared secret ThemeInstallGateway signs its
                // theme push with (customer-site ProvisionSecretMiddleware
                // refuses every push while it is empty). Without it a fresh
                // site could never receive the theme bound at onboarding.
                "CUSTOMER_PROVISION_SECRET=" . self::envValue((string) config('services.customer_provision_secret', '')),
                "",
                "SITE_TIMEZONE={$req->timezone}",
                "SITE_LANGUAGE={$req->language}",
                "SITE_CURRENCY={$req->currency}",
                "",
                "MODULE_EDUCATION_ENABLED=" . (in_array('education', $req->modules) ? 'true' : 'false'),
                "MODULE_FORMS_ENABLED=" . (in_array('forms', $req->modules) ? 'true' : 'false'),
                "MODULE_TICKETS_ENABLED=" . (in_array('tickets', $req->modules) ? 'true' : 'false'),
                "",
                "ADMIN_NAME=" . self::envValue((string) $req->ownerName),
                "ADMIN_EMAIL={$req->email}",
                "ADMIN_MOBILE={$req->phone}",
                "ADMIN_PASSWORD={$adminPassword}",
            ];

            $envContent = implode("\n", $envLines) . "\n";

            // Write via heredoc through SSH to avoid quoting issues
            $encoded = base64_encode($envContent);
            $result = $ctx->connection->execute(
                "echo " . escapeshellarg($encoded) . " | base64 -d > " . escapeshellarg($envPath)
            );

            if ($result->failed()) {
                return StageResult::failure($this->name(), 'Failed to write .env: ' . $result->output(), $this->elapsed());
            }

            // Phase 13-O9a — the password itself is never written to
            // metadata (plaintext JSON that ends up in every daily platform
            // backup, local and offsite). It's stored encrypted via
            // CredentialVault; metadata only keeps a reference to the
            // InstallationCredential row.
            //
            // Phase 13-O9c — updateOrCreate (keyed on installation_id+type,
            // now unique-constrained) instead of create(), so a retried
            // stage run overwrites the same row rather than leaving stale
            // duplicate credentials behind.
            $credential = InstallationCredential::updateOrCreate(
                ['installation_id' => $ctx->installation->id, 'type' => 'admin_password'],
                ['encrypted_value' => $this->vault->encrypt($adminPassword)]
            );

            $ctx->installation->update([
                'metadata' => [
                    'admin_email'          => $req->email,
                    'admin_mobile'         => $req->phone,
                    'admin_name'           => $req->ownerName,
                    'first_login_required' => true,
                    'credential_ids'       => [
                        'admin_password' => $credential->id,
                    ],
                ],
            ]);

            return StageResult::success($this->name(), [
                'env_path'      => $envPath,
                'app_key_set'   => true,
                'app_url'       => $appUrl,
                'cache_driver'  => $cacheDriver,
                'session_driver'=> $sessionDriver,
                'queue_driver'  => $queueDriver,
            ], ['env_path' => $envPath, 'credential_type' => 'admin_password'], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['env_path'])) {
            $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot['env_path']));
        }

        // Phase 13-O9c — revoke, not delete: the InstallationCredential row
        // is kept (installation_id/type/created_at stay available for
        // audit), only the metadata pointer is removed. Without this, the
        // .env is gone but the UI still resolved credential_ids.admin_password
        // to a row whose password no longer matches anything live.
        $type = $snapshot['credential_type'] ?? null;
        if ($type === null) {
            return;
        }

        $installation = $ctx->installation->fresh();
        $metadata = $installation->metadata ?? [];

        if (isset($metadata['credential_ids'][$type])) {
            unset($metadata['credential_ids'][$type]);
            $installation->update(['metadata' => $metadata]);
        }
    }
}
