<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerDeletion\Guards\DomainGuard;
use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;

/**
 * STAGE: REMOVE_SSL (ADR-090)
 *
 * Deletes the Let's Encrypt lineages VpsSslProvisioner issued for this site
 * (`--cert-name {domain}`: the install domain, the current domain and every
 * site_domains row), so certbot's renewal timer stops trying — and failing —
 * to renew a certificate whose webroot is gone.
 *
 * A lineage is deleted only when all of these hold:
 *  - the frozen plan listed it with action `delete` (DomainGuard passed);
 *  - /etc/letsencrypt/renewal/{name}.conf exists;
 *  - that renewal config's webroot is THIS site's `{webRoot}/public`
 *    (otherwise it belongs to something else → manual);
 *  - no remaining Nginx config references /etc/letsencrypt/live/{name}/
 *    (runs after REMOVE_WEBSERVER, so this site's own vhost is already gone).
 * VPS only: on cPanel/DirectAdmin AutoSSL owns certificates.
 */
class RemoveSslCertificateStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_SSL'; }
    public function label(): string { return 'حذف گواهی SSL'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 10; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            if (!$ctx->connection->capabilities()->supportsWebServerConfiguration) {
                return DeleteStageResult::skipped($this->name(), 'گواهی SSL روی این نوع هاست توسط پنل (AutoSSL) مدیریت می‌شود');
            }
            if ($ctx->certificates === []) {
                return DeleteStageResult::skipped($this->name(), 'هیچ گواهی‌ای در پلن حذف ثبت نشده است');
            }

            $bin     = escapeshellcmd((string) config('customer_installation.ssl.certbot_bin', 'certbot'));
            $webroot = rtrim($ctx->webRoot, '/') . '/public';
            $deleted = [];
            $absent  = [];
            $manual  = [];
            $failed  = [];

            foreach ($ctx->certificates as $cert) {
                $name = (string) ($cert['name'] ?? '');
                if (($cert['action'] ?? null) !== 'delete') {
                    $manual[] = "{$name}: " . ($cert['reason'] ?? 'در پلن برای حذف خودکار تأیید نشده');
                    continue;
                }

                try {
                    $name = DomainGuard::assertCertificateName($name);
                } catch (PurgeGuardViolation $e) {
                    $manual[] = "{$name}: {$e->getMessage()}";
                    continue;
                }

                $renewal = "/etc/letsencrypt/renewal/{$name}.conf";
                if (!$this->remoteFileExists($ctx, $renewal)) {
                    $absent[] = $name;
                    continue;
                }

                $ownsWebroot = $this->remoteExec($ctx,
                    'grep -qF -- ' . escapeshellarg($webroot) . ' ' . escapeshellarg($renewal) . ' && echo owned || echo foreign'
                );
                if (!str_contains($ownsWebroot, 'owned')) {
                    $manual[] = "{$name}: webroot این گواهی مسیر این سایت ({$webroot}) نیست؛ ممکن است متعلق به سایت دیگری باشد.";
                    continue;
                }

                $inUse = $this->remoteExec($ctx,
                    'grep -rlsF -- ' . escapeshellarg("/etc/letsencrypt/live/{$name}/") . ' /etc/nginx 2>/dev/null | head -n 1'
                );
                if ($inUse !== '') {
                    $manual[] = "{$name}: هنوز در پیکربندی Nginx ({$inUse}) استفاده می‌شود.";
                    continue;
                }

                $this->remoteExec($ctx, "{$bin} delete --cert-name " . escapeshellarg($name) . ' --non-interactive 2>&1');

                if ($this->remoteFileExists($ctx, $renewal)) {
                    $failed[] = $name;
                } else {
                    $deleted[] = $name;
                }
            }

            $output = compact('deleted', 'absent', 'manual', 'failed');

            if ($failed !== []) {
                return DeleteStageResult::failure($this->name(), 'certbot delete برای این گواهی‌ها اثر نکرد: ' . implode('، ', $failed));
            }
            if ($manual !== []) {
                return DeleteStageResult::manual(
                    $this->name(),
                    'این گواهی‌ها خودکار حذف نشدند: ' . implode(' | ', $manual),
                    $output,
                );
            }

            return DeleteStageResult::success($this->name(), $output);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $checks = [];
        foreach ($ctx->certificates as $cert) {
            if (($cert['action'] ?? null) !== 'delete') {
                continue;
            }
            $renewal = "/etc/letsencrypt/renewal/{$cert['name']}.conf";
            $checks[] = ['check' => "Certificate {$cert['name']} removed", 'passed' => !$this->remoteFileExists($ctx, $renewal), 'detail' => $renewal];
        }

        return $checks;
    }

    private function remoteFileExists(DeleteContext $ctx, string $path): bool
    {
        return str_contains($this->remoteExec($ctx, 'test -f ' . escapeshellarg($path) . ' && echo yes || echo no'), 'yes');
    }
}
