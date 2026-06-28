<?php

namespace App\Services\CustomerInstallation\Drivers;

use App\Models\DeploymentTarget;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\ValidationResult;
use App\Services\CustomerInstallation\Enums\DriverType;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;
use RuntimeException;

/**
 * SSH deployment driver for VPS / dedicated servers.
 *
 * Uses phpseclib3 for SSH2 + SFTP transport.
 * Supports RSA key authentication and password authentication.
 * Includes automatic reconnect on dropped connections (MAX_RECONNECTS attempts).
 * Host key fingerprint pinning on first connect, verified on subsequent connects.
 */
class SshDeploymentDriver implements DeploymentDriverInterface
{
    private ?SSH2 $ssh  = null;
    private ?SFTP $sftp = null;
    private int $reconnectAttempts = 0;
    private const MAX_RECONNECTS = 2;

    public function __construct(
        private readonly DeploymentTarget $target,
        private readonly CredentialVault  $vault,
    ) {}

    // ─── DeploymentDriverInterface ────────────────────────────────────────────

    public function driverType(): DriverType
    {
        return DriverType::SSH;
    }

    public function capabilities(): DriverCapabilities
    {
        return DriverCapabilities::forVps();
    }

    public function validate(): ValidationResult
    {
        $errors = [];

        if (empty($this->target->host)) {
            $errors[] = 'Host is required';
        }

        if (empty($this->target->username)) {
            $errors[] = 'Username is required';
        }

        if ($this->target->isKeyAuth()) {
            if (empty($this->target->private_key_encrypted)) {
                $errors[] = 'SSH private key is required for key authentication';
            }
        } else {
            if (empty($this->target->password_encrypted)) {
                $errors[] = 'Password is required for password authentication';
            }
        }

        if (!empty($errors)) {
            return ValidationResult::fail($errors);
        }

        return ValidationResult::pass();
    }

    public function rollbackStage(string $stageName, array $snapshot): void
    {
        // Stage-specific rollback logic resides in each Stage class.
        // SSH driver has no driver-level cleanup beyond the session lifecycle.
    }

    // ─── RemoteConnectionInterface ────────────────────────────────────────────

    public function connect(): void
    {
        $timeout = config('customer_installation.ssh.timeout_connect', 10);

        $ssh = new SSH2($this->target->host, $this->target->port, $timeout);

        if ($this->target->isKeyAuth()) {
            $privateKey = $this->vault->decrypt($this->target->private_key_encrypted);
            $key = PublicKeyLoader::load($privateKey);
            if (!$ssh->login($this->target->username, $key)) {
                throw new RuntimeException("SSH key authentication failed for {$this->target->host}");
            }
        } else {
            $password = $this->vault->decrypt($this->target->password_encrypted);
            if (!$ssh->login($this->target->username, $password)) {
                throw new RuntimeException("SSH password authentication failed for {$this->target->host}");
            }
        }

        $this->verifyFingerprint($ssh);

        $ssh->setTimeout(config('customer_installation.ssh.timeout_execute', 600));
        $this->ssh = $ssh;
    }

    public function testConnection(): ConnectionTestResult
    {
        $start    = microtime(true);
        $warnings = [];

        try {
            $this->connect();

            $os = trim(
                $this->execute('cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d \'"\'')->stdout
            );

            $phpResult  = $this->execute('php -v 2>&1 | head -1');
            $phpVersion = '';
            if ($phpResult->succeeded()) {
                preg_match('/PHP ([\d.]+)/', $phpResult->stdout, $m);
                $phpVersion = $m[1] ?? 'unknown';
            }

            $extResult  = $this->execute('php -m 2>/dev/null');
            $extensions = $extResult->succeeded()
                ? array_filter(
                    array_map('trim', explode("\n", $extResult->stdout)),
                    fn($e) => !empty($e) && $e[0] !== '['
                )
                : [];

            $dfResult = $this->execute('df -B1 /var/www 2>/dev/null || df -B1 /tmp');
            $diskFree = 0;
            if ($dfResult->succeeded()) {
                preg_match('/(\d+)\s+\d+%/', $dfResult->stdout, $d);
                $diskFree = isset($d[1]) ? (int) $d[1] : 0;
            }

            $mysqlResult    = $this->execute('mysql --version 2>/dev/null');
            $mysqlAccessible = $mysqlResult->succeeded() && str_contains($mysqlResult->stdout, 'mysql');

            $supervisorResult  = $this->execute('which supervisorctl 2>/dev/null');
            $supervisorPresent = $supervisorResult->succeeded() && !empty(trim($supervisorResult->stdout));

            $pingMs      = (int) round((microtime(true) - $start) * 1000);
            $fingerprint = $this->ssh->getServerPublicHostKey() ?? null;

            return new ConnectionTestResult(
                connected:         true,
                os:                $os,
                phpVersion:        $phpVersion,
                phpExtensions:     array_values($extensions),
                diskFreeBytes:     $diskFree,
                mysqlAccessible:   $mysqlAccessible,
                supervisorPresent: $supervisorPresent,
                pingMs:            $pingMs,
                warnings:          $warnings,
                fingerprint:       $fingerprint ? hash('sha256', $fingerprint) : null,
                error:             null,
            );
        } catch (\Throwable $e) {
            return ConnectionTestResult::failure($e->getMessage());
        } finally {
            $this->disconnect();
        }
    }

    public function execute(string $command, array $env = []): CommandResult
    {
        if (!$this->ssh) {
            throw new RuntimeException('SSH not connected. Call connect() first.');
        }

        try {
            return $this->doExecute($command, $env);
        } catch (\Throwable $e) {
            if ($this->isConnectionDropped($e) && $this->reconnectAttempts < self::MAX_RECONNECTS) {
                $this->reconnectAttempts++;
                $this->reconnect();
                return $this->doExecute($command, $env);
            }
            throw $e;
        }
    }

    public function upload(string $localPath, string $remotePath, ?callable $progress = null): void
    {
        $sftp      = $this->getSftp();
        $chunkSize = config('customer_installation.upload.chunk_size_bytes', 4 * 1024 * 1024);
        $fileSize  = filesize($localPath);
        $uploaded  = 0;

        $sftp->put(
            $remotePath,
            function (int $size) use ($localPath, $chunkSize, $fileSize, &$uploaded, $progress): string {
                static $fp = null;
                if ($fp === null) {
                    $fp = fopen($localPath, 'rb');
                    if ($fp === false) {
                        throw new RuntimeException("Cannot open local file: {$localPath}");
                    }
                }
                $data = fread($fp, $chunkSize);
                if ($data === false || $data === '') {
                    fclose($fp);
                    return '';
                }
                $uploaded += strlen($data);
                if ($progress && $fileSize > 0) {
                    $pct = (int) round($uploaded / $fileSize * 100);
                    $progress($pct, $uploaded, $fileSize);
                }
                return $data;
            },
            SFTP::SOURCE_CALLBACK
        );
    }

    public function download(string $remotePath, string $localPath): void
    {
        $sftp = $this->getSftp();
        if (!$sftp->get($remotePath, $localPath)) {
            throw new RuntimeException("SFTP download failed: {$remotePath}");
        }
    }

    public function fileExists(string $remotePath): bool
    {
        $sftp = $this->getSftp();
        return $sftp->file_exists($remotePath);
    }

    public function diskFree(string $path): int
    {
        $result = $this->execute("df -B1 " . escapeshellarg($path) . " 2>/dev/null | awk 'NR==2{print $4}'");
        return $result->succeeded() ? (int) trim($result->stdout) : 0;
    }

    public function disconnect(): void
    {
        try {
            $this->sftp?->disconnect();
            $this->ssh?->disconnect();
        } catch (\Throwable) {}

        $this->sftp = null;
        $this->ssh  = null;
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    private function doExecute(string $command, array $env = []): CommandResult
    {
        $start = microtime(true);

        $envString = '';
        foreach ($env as $key => $value) {
            $envString .= 'export ' . escapeshellarg($key) . '=' . escapeshellarg($value) . '; ';
        }

        $output     = $this->ssh->exec($envString . $command);
        $exitCode   = $this->ssh->getExitStatus();
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $this->reconnectAttempts = 0;

        return new CommandResult(
            exitCode:   is_int($exitCode) ? $exitCode : 0,
            stdout:     $output ?? '',
            stderr:     '',
            durationMs: $durationMs,
        );
    }

    private function isConnectionDropped(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'disconnected')
            || str_contains($msg, 'connection reset')
            || str_contains($msg, 'broken pipe')
            || str_contains($msg, 'timed out')
            || str_contains($msg, 'connection closed')
            || str_contains($msg, 'bad file descriptor');
    }

    private function reconnect(): void
    {
        try {
            $this->sftp?->disconnect();
            $this->ssh?->disconnect();
        } catch (\Throwable) {}

        $this->sftp = null;
        $this->ssh  = null;

        usleep(800_000); // 0.8 s — allow server to recover before reconnect
        $this->connect();
    }

    private function getSftp(): SFTP
    {
        if ($this->sftp) {
            return $this->sftp;
        }

        $sftp = new SFTP($this->target->host, $this->target->port);

        if ($this->target->isKeyAuth()) {
            $key = PublicKeyLoader::load($this->vault->decrypt($this->target->private_key_encrypted));
            if (!$sftp->login($this->target->username, $key)) {
                throw new RuntimeException('SFTP key auth failed');
            }
        } else {
            $password = $this->vault->decrypt($this->target->password_encrypted);
            if (!$sftp->login($this->target->username, $password)) {
                throw new RuntimeException('SFTP password auth failed');
            }
        }

        $this->sftp = $sftp;
        return $this->sftp;
    }

    private function verifyFingerprint(SSH2 $ssh): void
    {
        if (!$this->target->key_fingerprint) {
            return; // first connection — fingerprint not yet stored
        }

        $serverKey = $ssh->getServerPublicHostKey();
        if (!$serverKey) {
            return;
        }

        $fingerprint = hash('sha256', $serverKey);
        if ($fingerprint !== $this->target->key_fingerprint) {
            throw new RuntimeException(
                "SSH host key fingerprint mismatch for {$this->target->host}. " .
                "Expected: {$this->target->key_fingerprint}, Got: {$fingerprint}. " .
                "Possible MITM attack — aborting."
            );
        }
    }
}
