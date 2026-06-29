<?php

namespace App\Services\CustomerInstallation\Drivers;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\Common\PrivateKey as SshPrivateKey;
use RuntimeException;

class SshRemoteDriver implements RemoteConnectionInterface
{
    private ?SSH2 $ssh  = null;
    private ?SFTP $sftp = null;
    private int $reconnectAttempts = 0;
    private const MAX_RECONNECTS = 2;

    public function __construct(
        private readonly CustomerRemoteServer $server,
        private readonly CredentialVault $vault,
    ) {}

    public function connect(): void
    {
        $timeout = config('customer_installation.ssh.timeout_connect', 10);

        $ssh = new SSH2($this->server->host, $this->server->port, $timeout);

        if ($this->server->isKeyAuth()) {
            $privateKey = $this->vault->decrypt($this->server->private_key_encrypted);
            $key = PublicKeyLoader::load($privateKey);
            if (!($key instanceof SshPrivateKey)) {
                throw new RuntimeException('کلید خصوصی معتبر نیست — کلید عمومی ذخیره شده است. از صفحه ویرایش مقصد، کلید خصوصی را وارد کنید.');
            }
            if (!$ssh->login($this->server->username, $key)) {
                throw new RuntimeException("SSH key authentication failed for {$this->server->host}");
            }
        } else {
            $password = $this->vault->decrypt($this->server->password_encrypted);
            if (!$ssh->login($this->server->username, $password)) {
                throw new RuntimeException("SSH password authentication failed for {$this->server->host}");
            }
        }

        $this->verifyFingerprint($ssh);

        $ssh->setTimeout(config('customer_installation.ssh.timeout_execute', 600));
        $this->ssh = $ssh;
    }

    public function testConnection(): ConnectionTestResult
    {
        $start = microtime(true);
        $warnings = [];

        try {
            $this->connect();

            $os = trim($this->execute('cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d \'"\'')->stdout);

            $phpResult = $this->execute('php -v 2>&1 | head -1');
            $phpVersion = '';
            if ($phpResult->succeeded()) {
                preg_match('/PHP ([\d.]+)/', $phpResult->stdout, $m);
                $phpVersion = $m[1] ?? 'unknown';
            }

            $extResult = $this->execute('php -m 2>/dev/null');
            $extensions = $extResult->succeeded()
                ? array_filter(array_map('trim', explode("\n", $extResult->stdout)), fn($e) => !empty($e) && $e[0] !== '[')
                : [];

            $dfResult = $this->execute('df -B1 /var/www 2>/dev/null || df -B1 /tmp');
            $diskFree = 0;
            if ($dfResult->succeeded()) {
                preg_match('/(\d+)\s+\d+%/', $dfResult->stdout, $d);
                $diskFree = isset($d[1]) ? (int) $d[1] : 0;
            }

            $mysqlResult = $this->execute('mysql --version 2>/dev/null');
            $mysqlAccessible = $mysqlResult->succeeded() && str_contains($mysqlResult->stdout, 'mysql');

            $supervisorResult = $this->execute('which supervisorctl 2>/dev/null');
            $supervisorPresent = $supervisorResult->succeeded() && !empty(trim($supervisorResult->stdout));

            $pingMs = (int) round((microtime(true) - $start) * 1000);
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

    private function doExecute(string $command, array $env = []): CommandResult
    {
        $start = microtime(true);

        $envString = '';
        foreach ($env as $key => $value) {
            $envString .= 'export ' . escapeshellarg($key) . '=' . escapeshellarg($value) . '; ';
        }

        $output    = $this->ssh->exec($envString . $command);
        $exitCode  = $this->ssh->getExitStatus();
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        // Reset reconnect counter on successful execution
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

        // Brief pause before reconnect to let server recover
        usleep(800000); // 0.8 seconds
        $this->connect();
    }

    public function upload(string $localPath, string $remotePath, ?callable $progress = null): void
    {
        $chunkSize   = config('customer_installation.upload.chunk_size_bytes', 4 * 1024 * 1024);
        $maxAttempts = max(1, (int) config('customer_installation.upload.retry_attempts', 3));
        $retryDelayMs = (int) config('customer_installation.upload.retry_delay_ms', 2000);
        $fileSize    = filesize($localPath);

        // Optimization: if source file is accessible on the remote server at the same path
        // (same-server deployment), use shell cp for instant transfer instead of SFTP.
        // This avoids hairpin NAT / SFTP loopback issues when platform and target are co-located.
        $fastCopy = $this->execute(
            'test -f ' . escapeshellarg($localPath) . ' && cp ' . escapeshellarg($localPath) . ' ' . escapeshellarg($remotePath) . ' && echo __ok__'
        );
        if (str_contains($fastCopy->stdout, '__ok__')) {
            if ($progress) {
                $progress(100, $fileSize, $fileSize);
            }
            return;
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $sftp     = $this->getSftp();
            $uploaded = 0;
            $fp       = null;

            try {
                // Resume check: if remote file already has the correct size, skip upload.
                $remoteStat = $sftp->stat($remotePath);
                if ($remoteStat !== false && isset($remoteStat['size']) && (int) $remoteStat['size'] === $fileSize) {
                    if ($progress) {
                        $progress(100, $fileSize, $fileSize);
                    }
                    return; // already complete — skip
                }

                // Partial file on remote: delete before re-upload.
                if ($remoteStat !== false) {
                    $sftp->delete($remotePath);
                }

                $fp = fopen($localPath, 'rb');
                if ($fp === false) {
                    throw new RuntimeException("Cannot open local file: {$localPath}");
                }

                $ok = $sftp->put(
                    $remotePath,
                    function (int $size) use (&$fp, $chunkSize, $fileSize, &$uploaded, $progress): string {
                        $data = fread($fp, $chunkSize);
                        if ($data === false || $data === '') {
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

                fclose($fp);
                $fp = null;

                if (!$ok) {
                    throw new RuntimeException("SFTP put() returned false after {$uploaded}/{$fileSize} bytes");
                }

                return; // success

            } catch (\Throwable $e) {
                if ($fp !== null) {
                    fclose($fp);
                }

                // Clean up the partial remote file so the next attempt starts fresh.
                try {
                    $this->getSftp()->delete($remotePath);
                } catch (\Throwable) {}

                $lastException = $e;

                if ($attempt < $maxAttempts && $this->isConnectionDropped($e)) {
                    // Force SFTP reconnect before next attempt.
                    try {
                        $this->sftp?->disconnect();
                    } catch (\Throwable) {}
                    $this->sftp = null;
                    usleep($retryDelayMs * 1000);
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new RuntimeException('Upload failed after all retry attempts');
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
        $this->ssh = null;
    }

    private function getSftp(): SFTP
    {
        if ($this->sftp) {
            return $this->sftp;
        }

        // Use the upload timeout (default 1800s) so that large file transfers
        // don't hit the default phpseclib3 10-second per-operation timeout.
        $uploadTimeout = (int) config('customer_installation.ssh.timeout_upload', 1800);

        $sftp = new SFTP($this->server->host, $this->server->port, $uploadTimeout);
        $sftp->setTimeout($uploadTimeout);

        if ($this->server->isKeyAuth()) {
            $key = PublicKeyLoader::load($this->vault->decrypt($this->server->private_key_encrypted));
            if (!$sftp->login($this->server->username, $key)) {
                throw new RuntimeException('SFTP key auth failed');
            }
        } else {
            $password = $this->vault->decrypt($this->server->password_encrypted);
            if (!$sftp->login($this->server->username, $password)) {
                throw new RuntimeException('SFTP password auth failed');
            }
        }

        $this->sftp = $sftp;
        return $this->sftp;
    }

    private function verifyFingerprint(SSH2 $ssh): void
    {
        if (!$this->server->key_fingerprint) {
            return; // First connection — fingerprint not yet stored
        }

        $serverKey = $ssh->getServerPublicHostKey();
        if (!$serverKey) {
            return;
        }

        $fingerprint = hash('sha256', $serverKey);
        if ($fingerprint !== $this->server->key_fingerprint) {
            throw new RuntimeException(
                "SSH host key fingerprint mismatch for {$this->server->host}. " .
                "Expected: {$this->server->key_fingerprint}, Got: {$fingerprint}. " .
                "Possible MITM attack — aborting."
            );
        }
    }
}
