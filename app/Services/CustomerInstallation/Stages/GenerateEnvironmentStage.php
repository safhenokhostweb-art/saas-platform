<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class GenerateEnvironmentStage extends AbstractStage
{
    public function name(): string { return 'GENERATE_ENVIRONMENT'; }
    public function label(): string { return 'تولید فایل محیطی'; }
    public function estimatedSeconds(): int { return 3; }
    public function canRollback(): bool { return true; }

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

            $envLines = [
                "APP_NAME=\"{$req->company}\"",
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
                "CACHE_DRIVER=redis",
                "SESSION_DRIVER=redis",
                "QUEUE_CONNECTION=redis",
                "REDIS_HOST=127.0.0.1",
                "REDIS_PORT=6379",
                "",
                "MAIL_MAILER=" . ($req->smtp['mailer'] ?? 'smtp'),
                "MAIL_HOST=" . ($req->smtp['host'] ?? ''),
                "MAIL_PORT=" . ($req->smtp['port'] ?? '587'),
                "MAIL_USERNAME=" . ($req->smtp['username'] ?? ''),
                "MAIL_PASSWORD=" . ($req->smtp['password'] ?? ''),
                "MAIL_ENCRYPTION=" . ($req->smtp['encryption'] ?? 'tls'),
                "",
                "PLATFORM_URL={$platformUrl}",
                "LICENSE_KEY=__LICENSE_KEY__",
                "",
                "SITE_TIMEZONE={$req->timezone}",
                "SITE_LANGUAGE={$req->language}",
                "SITE_CURRENCY={$req->currency}",
                "",
                "MODULE_EDUCATION_ENABLED=" . (in_array('education', $req->modules) ? 'true' : 'false'),
                "MODULE_FORMS_ENABLED=" . (in_array('forms', $req->modules) ? 'true' : 'false'),
                "MODULE_TICKETS_ENABLED=" . (in_array('tickets', $req->modules) ? 'true' : 'false'),
                "",
                "ADMIN_NAME=\"{$req->ownerName}\"",
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

            // Store admin credentials in installation metadata for platform admin retrieval
            $ctx->installation->update([
                'metadata' => [
                    'admin_email'             => $req->email,
                    'admin_mobile'            => $req->phone,
                    'admin_name'              => $req->ownerName,
                    'admin_password'          => $adminPassword,
                    'first_login_required'    => true,
                ],
            ]);

            return StageResult::success($this->name(), [
                'env_path'      => $envPath,
                'app_key_set'   => true,
                'app_url'       => $appUrl,
            ], ['env_path' => $envPath], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['env_path'])) {
            $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot['env_path']));
        }
    }
}
