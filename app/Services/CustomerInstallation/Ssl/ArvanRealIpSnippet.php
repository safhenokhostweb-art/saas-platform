<?php

namespace App\Services\CustomerInstallation\Ssl;

/**
 * Central source of the ArvanCloud real-IP nginx snippet that every customer
 * vhost includes (see NginxVhostTemplate).
 *
 * The vhost only carries `include snippets/arvan-cdn-real-ip.conf;`, so the
 * snippet is a server-level prerequisite. Nothing provisioned it, and on
 * 2026-11-17 installation 49 failed `nginx -t` in CONFIGURE_WEB_SERVER with
 * `open() "/etc/nginx/snippets/arvan-cdn-real-ip.conf" failed`.
 * NginxVhostWriter now runs ensureCommand() before it writes the vhost, so a
 * new server gets the snippet on its first installation.
 *
 * An existing snippet is never overwritten: an operator may have updated the
 * ranges on the server, and other vhosts may already depend on it.
 */
final class ArvanRealIpSnippet
{
    public const REMOTE_PATH = '/etc/nginx/snippets/arvan-cdn-real-ip.conf';

    /** Path relative to the nginx prefix, as used in the include directive. */
    public const INCLUDE_PATH = 'snippets/arvan-cdn-real-ip.conf';

    public const STATUS_PRESENT = 'present';
    public const STATUS_INSTALLED = 'installed';

    /**
     * ArvanCloud CDN edge ranges, exactly as published at
     * https://www.arvancloud.ir/en/ips.txt (fetched 2026-11-17, 14 ranges).
     * Only these peers may set Ar-Real-Ip. Any wider range would let a
     * client in it spoof its address. When Arvan publishes a change, update
     * this list and the server files (existing snippets are never
     * overwritten by the installer).
     */
    public const RANGES = [
        '185.143.232.0/22',
        '188.229.116.16/30',
        '94.101.182.0/27',
        '2.144.3.128/28',
        '37.32.16.0/27',
        '37.32.17.0/27',
        '37.32.18.0/27',
        '37.32.19.0/27',
        '185.215.232.0/22',
        '178.131.120.48/28',
        '94.101.183.0/28',
        '78.157.36.112/28',
        '95.38.61.80/28',
        '193.24.119.0/29',
    ];

    public const REAL_IP_HEADER = 'Ar-Real-Ip';

    public function includeDirective(): string
    {
        return 'include ' . self::INCLUDE_PATH . ';';
    }

    public function render(): string
    {
        $lines = [
            '# ArvanCloud CDN edge ranges: official list https://www.arvancloud.ir/en/ips.txt',
            '# (fetched 2026-11-17, 14 ranges). Re-check that list before changing this file.',
        ];
        foreach (self::RANGES as $range) {
            $lines[] = "set_real_ip_from {$range};";
        }
        $lines[] = 'real_ip_header ' . self::REAL_IP_HEADER . ';';
        $lines[] = 'real_ip_recursive on;';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Idempotent POSIX-sh command: prints STATUS_PRESENT and changes nothing
     * when a non-empty snippet already exists. Otherwise it writes render()
     * atomically (temp file + mv) with mode 644 and prints STATUS_INSTALLED.
     * Exits non-zero when the write fails.
     */
    public function ensureCommand(string $path = self::REMOTE_PATH): string
    {
        $target  = self::posixQuote($path);
        $dir     = self::posixQuote(dirname($path));
        $tmp     = self::posixQuote($path . '.tmp');
        $encoded = self::posixQuote(base64_encode($this->render()));

        return "if test -s {$target}; then echo " . self::STATUS_PRESENT . '; '
            . "else mkdir -p {$dir} && echo {$encoded} | base64 -d > {$tmp} && chmod 644 {$tmp} && mv -f {$tmp} {$target} && echo " . self::STATUS_INSTALLED . '; fi';
    }

    /**
     * The command runs in the remote POSIX shell. escapeshellarg() quotes
     * for the local OS, and on Windows that means double quotes, so quote
     * explicitly instead.
     */
    private static function posixQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}
