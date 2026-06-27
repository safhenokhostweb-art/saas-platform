<?php

namespace App\Console\Commands;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\SshRemoteDriver;
use Illuminate\Console\Command;

/**
 * Acceptance test for the Customer Installation Engine.
 *
 * Runs on a real (or local) VPS and measures all pre-install checks.
 * Does NOT perform actual installation — use this to certify a target server
 * before scheduling a real deployment, or after adding a new server.
 *
 * Usage:
 *   php artisan customer-install:acceptance-test {server_id}
 *   php artisan customer-install:acceptance-test {server_id} --full
 */
class CustomerInstallAcceptanceCommand extends Command
{
    protected $signature = 'customer-install:acceptance-test
                            {server : ID of the CustomerRemoteServer to test}
                            {--full : Run full pre-install checks (disk, extensions, MySQL)}';

    protected $description = 'Acceptance test for Customer Installation Engine — validates target server readiness';

    public function __construct(private readonly CredentialVault $vault)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $serverId = (int) $this->argument('server');

        $server = CustomerRemoteServer::find($serverId);
        if (!$server) {
            $this->error("Server #{$serverId} not found.");
            return Command::FAILURE;
        }

        $this->info("CIE Acceptance Test — Server: {$server->host}:{$server->port} ({$server->label})");
        $this->line(str_repeat('─', 70));

        $driver = new SshRemoteDriver($server, $this->vault);

        $results  = [];
        $allPass  = true;
        $totalMs  = 0;

        // ── A: SSH Connection ──────────────────────────────────────────────
        $start = $this->microMs();
        try {
            $driver->connect();
            $ms = $this->microMs() - $start;
            $totalMs += $ms;
            $results[] = $this->row('SSH Connection', 'PASS', "Connected in {$ms}ms", $ms);
        } catch (\Throwable $e) {
            $results[] = $this->row('SSH Connection', 'FAIL', $e->getMessage(), 0);
            $allPass = false;
            $this->printTable($results);
            $driver->disconnect();
            return Command::FAILURE;
        }

        // ── B: Command Execution ───────────────────────────────────────────
        $start  = $this->microMs();
        $result = $driver->execute('echo "cie-ok" && uname -m');
        $ms     = $this->microMs() - $start;
        $totalMs += $ms;
        if (str_contains($result->stdout, 'cie-ok')) {
            $results[] = $this->row('Command Execution', 'PASS', trim($result->stdout), $ms);
        } else {
            $results[] = $this->row('Command Execution', 'FAIL', 'echo test failed', $ms);
            $allPass = false;
        }

        // ── C: PHP Version ────────────────────────────────────────────────
        $start  = $this->microMs();
        $result = $driver->execute('php -v 2>&1 | head -1');
        $ms     = $this->microMs() - $start;
        $totalMs += $ms;
        if (preg_match('/PHP ([\d.]+)/', $result->stdout, $m)) {
            $version = $m[1];
            $status  = version_compare($version, '8.1', '>=') ? 'PASS' : 'FAIL';
            if ($status === 'FAIL') $allPass = false;
            $results[] = $this->row('PHP Version', $status, "PHP {$version}", $ms);
        } else {
            $results[] = $this->row('PHP Version', 'FAIL', 'PHP not found', $ms);
            $allPass = false;
        }

        if ($this->option('full')) {

            // ── D: Disk Space ─────────────────────────────────────────────
            $start  = $this->microMs();
            $result = $driver->execute("df -BG /var/www 2>/dev/null | awk 'NR==2{print $4}' | tr -d 'G'");
            $ms     = $this->microMs() - $start;
            $totalMs += $ms;
            $gbFree = (int) trim($result->stdout);
            $status = $gbFree >= 2 ? 'PASS' : 'WARN';
            $results[] = $this->row('Disk Space (/var/www)', $status, "{$gbFree}GB free (min 2GB)", $ms);

            // ── E: PHP Extensions ─────────────────────────────────────────
            $start    = $this->microMs();
            $result   = $driver->execute('php -m 2>/dev/null');
            $ms       = $this->microMs() - $start;
            $totalMs += $ms;
            $required = config('customer_installation.required_php_extensions', ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'curl', 'zip', 'redis']);
            $present  = array_map('strtolower', array_filter(
                array_map('trim', explode("\n", $result->stdout)),
                fn($e) => !empty($e) && $e[0] !== '['
            ));
            $missing = array_values(array_filter($required, fn($ext) => !in_array(strtolower($ext), $present)));
            $status  = empty($missing) ? 'PASS' : 'FAIL';
            if ($status === 'FAIL') $allPass = false;
            $results[] = $this->row(
                'PHP Extensions',
                $status,
                empty($missing) ? 'All required extensions present' : 'Missing: ' . implode(', ', $missing),
                $ms
            );

            // ── F: MySQL Access ───────────────────────────────────────────
            $start  = $this->microMs();
            $result = $driver->execute("mysql -u root -e 'SELECT 1;' 2>/dev/null && echo 'mysql-ok' || echo 'mysql-fail'");
            $ms     = $this->microMs() - $start;
            $totalMs += $ms;
            $status = str_contains($result->stdout, 'mysql-ok') ? 'PASS' : 'WARN';
            $results[] = $this->row('MySQL Root Access', $status, str_contains($result->stdout, 'mysql-ok') ? 'OK' : 'Password auth required', $ms);

            // ── G: Supervisor ─────────────────────────────────────────────
            $start  = $this->microMs();
            $result = $driver->execute('which supervisorctl 2>/dev/null && supervisorctl status 2>/dev/null | head -3 || echo "not-installed"');
            $ms     = $this->microMs() - $start;
            $totalMs += $ms;
            $supervisorFound = !str_contains($result->stdout, 'not-installed') && !empty(trim($result->stdout));
            $results[] = $this->row('Supervisor', $supervisorFound ? 'PASS' : 'WARN', $supervisorFound ? 'Installed' : 'Not installed — queue worker needs manual setup', $ms);

            // ── H: Redis ──────────────────────────────────────────────────
            $start  = $this->microMs();
            $result = $driver->execute('redis-cli ping 2>/dev/null || echo redis-unavailable');
            $ms     = $this->microMs() - $start;
            $totalMs += $ms;
            $status = strtoupper(trim($result->stdout)) === 'PONG' ? 'PASS' : 'WARN';
            $results[] = $this->row('Redis', $status, trim($result->stdout), $ms);

            // ── I: Write Permission ───────────────────────────────────────
            $start  = $this->microMs();
            $testFile = '/var/www/.cie_acceptance_' . uniqid();
            $result = $driver->execute("echo test > {$testFile} && rm -f {$testFile} && echo writable || echo not-writable");
            $ms     = $this->microMs() - $start;
            $totalMs += $ms;
            $status = str_contains($result->stdout, 'writable') ? 'PASS' : 'FAIL';
            if ($status === 'FAIL') $allPass = false;
            $results[] = $this->row('/var/www Writable', $status, trim($result->stdout), $ms);
        }

        $driver->disconnect();

        // ── Print results ──────────────────────────────────────────────────
        $this->printTable($results);

        $this->line('');
        $this->line("Total check time : {$totalMs}ms");
        $this->line("Overall result   : " . ($allPass ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'));
        $this->line('');

        if ($allPass) {
            $this->info("✓ Server {$server->host} is READY for Customer Installation.");
            return Command::SUCCESS;
        }

        $this->error("✗ Server {$server->host} has FAILING checks. Fix them before deploying.");
        return Command::FAILURE;
    }

    private function printTable(array $rows): void
    {
        $this->line('');
        $headers = ['Check', 'Status', 'Detail', 'Time (ms)'];
        $this->table($headers, array_map(fn($r) => [
            $r['check'],
            $r['status'] === 'PASS' ? "<fg=green>{$r['status']}</>" : ($r['status'] === 'WARN' ? "<fg=yellow>{$r['status']}</>" : "<fg=red>{$r['status']}</>"),
            substr($r['detail'], 0, 60),
            $r['ms'],
        ], $rows));
    }

    private function row(string $check, string $status, string $detail, int $ms): array
    {
        return compact('check', 'status', 'detail', 'ms');
    }

    private function microMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
