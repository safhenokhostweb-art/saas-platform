<?php

namespace App\Services\CustomerInstallation;

use App\Services\CustomerInstallation\Dto\InstallationContext;

class PostInstallValidationService
{
    public function runAll(InstallationContext $ctx): array
    {
        $webRoot = $ctx->webRoot();
        $slug    = $ctx->customerSlug();
        $req     = $ctx->request;

        $checks = [];
        $checks['database']  = $this->checkDatabase($ctx, $webRoot);
        $checks['queue']     = $this->checkQueue($ctx, $slug);
        $checks['redis']     = $this->checkRedis($ctx);
        $checks['storage']   = $this->checkStorage($ctx, $webRoot);
        $checks['license']   = $this->checkLicense($ctx, $webRoot);
        $checks['scheduler'] = $this->checkScheduler($ctx, $slug);
        $checks['domain']    = $this->checkDomain($ctx, $req->domain);

        if ($req->sslEnabled) {
            $checks['ssl'] = $this->checkSsl($ctx, $req->domain);
        }

        return $checks;
    }

    /**
     * Returns true only when every required check passes.
     * Required: database, storage.
     * All others are WARN-level — install can succeed without them.
     */
    public function allRequiredPass(array $checks): bool
    {
        foreach (['database', 'storage'] as $key) {
            if (isset($checks[$key]) && $checks[$key]['status'] !== 'PASS') {
                return false;
            }
        }
        return true;
    }

    public function overallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');
        if (in_array('FAIL', $statuses)) {
            return 'FAIL';
        }
        if (in_array('WARN', $statuses)) {
            return 'WARN';
        }
        return 'PASS';
    }

    private function checkDatabase(InstallationContext $ctx, string $webRoot): array
    {
        $safe   = escapeshellarg($webRoot);
        $result = $ctx->connection->execute(
            "cd {$safe} && php artisan migrate:status 2>&1 | tail -3 || echo 'db-error'"
        );

        $out = trim($result->stdout);

        if (str_contains($out, 'db-error') || str_contains(strtolower($out), 'pdo') || str_contains(strtolower($out), 'access denied')) {
            return $this->check('FAIL', 'Database connection failed', true, $out);
        }

        // Try a ping via artisan tinker (more reliable than migrate:status)
        $ping = $ctx->connection->execute(
            "cd {$safe} && php artisan tinker --execute=\"DB::select('SELECT 1');\" 2>&1 | head -2 || echo 'db-ok'"
        );
        $pingOut = trim($ping->stdout);

        if (str_contains(strtolower($pingOut), 'exception') || str_contains(strtolower($pingOut), 'error')) {
            return $this->check('FAIL', 'Database not reachable: ' . substr($pingOut, 0, 200), true, $pingOut);
        }

        return $this->check('PASS', 'Database connected and migrations current', true, substr($out, 0, 200));
    }

    private function checkQueue(InstallationContext $ctx, string $slug): array
    {
        $workerName = "{$slug}_worker";
        $result     = $ctx->connection->execute(
            "supervisorctl status {$workerName} 2>/dev/null || echo 'supervisor-unavailable'"
        );
        $out = trim($result->stdout);

        if (str_contains($out, 'RUNNING')) {
            return $this->check('PASS', "Worker {$workerName} is RUNNING", false, $out);
        }

        if (str_contains($out, 'supervisor-unavailable') || str_contains($out, 'refused') || str_contains($out, 'No such file')) {
            return $this->check('WARN', 'Supervisor unavailable — queue worker needs manual start', false, $out);
        }

        if (str_contains($out, 'STOPPED') || str_contains($out, 'EXITED') || str_contains($out, 'FATAL')) {
            return $this->check('WARN', "Worker {$workerName} is not running ({$out}) — run: supervisorctl start {$workerName}", false, $out);
        }

        return $this->check('WARN', 'Queue worker state unknown — manual verification required', false, $out);
    }

    private function checkRedis(InstallationContext $ctx): array
    {
        $result = $ctx->connection->execute('redis-cli ping 2>/dev/null || echo redis-unavailable');
        $out    = strtoupper(trim($result->stdout));

        if ($out === 'PONG') {
            return $this->check('PASS', 'Redis responding', false, 'PONG');
        }

        return $this->check('WARN', 'Redis not responding: ' . trim($result->stdout), false, trim($result->stdout));
    }

    private function checkStorage(InstallationContext $ctx, string $webRoot): array
    {
        $safe = escapeshellarg($webRoot);

        $linkResult = $ctx->connection->execute("test -L {$safe}/public/storage && echo linked || echo missing");
        $linkOut    = trim($linkResult->stdout);

        if ($linkOut !== 'linked') {
            return $this->check('FAIL', 'Storage symlink missing (public/storage → storage/app/public)', true, $linkOut);
        }

        $writeResult = $ctx->connection->execute(
            "touch {$safe}/storage/app/.cie_health 2>&1 && rm -f {$safe}/storage/app/.cie_health && echo writable || echo not-writable"
        );
        $writeOut = trim($writeResult->stdout);

        if (!str_contains($writeOut, 'writable')) {
            return $this->check('WARN', 'Storage symlinked but directory not writable', true, $writeOut);
        }

        return $this->check('PASS', 'Storage symlinked and writable', true, 'OK');
    }

    private function checkLicense(InstallationContext $ctx, string $webRoot): array
    {
        $safe   = escapeshellarg($webRoot);
        $result = $ctx->connection->execute(
            "grep -q '^LICENSE_KEY=CIE-' {$safe}/.env 2>/dev/null && echo found || echo missing"
        );
        $out = trim($result->stdout);

        if ($out === 'found') {
            return $this->check('PASS', 'License key provisioned in .env', false, 'OK');
        }

        return $this->check('WARN', 'LICENSE_KEY not set — manual provisioning needed', false, $out);
    }

    private function checkScheduler(InstallationContext $ctx, string $slug): array
    {
        $cronPath = "/etc/cron.d/{$slug}";
        $result   = $ctx->connection->execute(
            "test -f " . escapeshellarg($cronPath) . " && echo found || echo missing"
        );
        $out = trim($result->stdout);

        if ($out === 'found') {
            return $this->check('PASS', "Cron entry exists at {$cronPath}", false, 'OK');
        }

        return $this->check('WARN', "Cron entry missing at {$cronPath} — scheduler needs manual setup", false, $out);
    }

    private function checkDomain(InstallationContext $ctx, string $domain): array
    {
        $result = $ctx->connection->execute(
            "curl -s -o /dev/null -w '%{http_code}' --max-time 15 http://127.0.0.1 "
            . "-H " . escapeshellarg("Host: {$domain}") . " 2>/dev/null || echo 0"
        );
        $code = (int) trim($result->stdout);

        if (in_array($code, [200, 301, 302, 307, 308])) {
            return $this->check('PASS', "Domain {$domain} responds HTTP {$code}", false, (string) $code);
        }

        if ($code === 0) {
            return $this->check('WARN', "Domain {$domain} not reachable — Nginx/Apache may need config", false, (string) $code);
        }

        return $this->check('WARN', "Domain {$domain} returned HTTP {$code}", false, (string) $code);
    }

    private function checkSsl(InstallationContext $ctx, string $domain): array
    {
        $result = $ctx->connection->execute(
            "curl -s -o /dev/null -w '%{http_code}' --max-time 10 https://{$domain} 2>/dev/null || echo 0"
        );
        $code = (int) trim($result->stdout);

        if (in_array($code, [200, 301, 302, 307, 308])) {
            return $this->check('PASS', "SSL active — HTTPS {$domain} returns {$code}", false, (string) $code);
        }

        return $this->check('WARN', "SSL not yet active — HTTPS returned {$code} (Let's Encrypt may be pending)", false, (string) $code);
    }

    private function check(string $status, string $message, bool $required, string $output): array
    {
        return [
            'status'   => $status,
            'message'  => $message,
            'required' => $required,
            'output'   => $output,
        ];
    }
}
