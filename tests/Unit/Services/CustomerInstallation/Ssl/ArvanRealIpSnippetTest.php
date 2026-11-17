<?php

namespace Tests\Unit\Services\CustomerInstallation\Ssl;

use App\Services\CustomerInstallation\Ssl\ArvanRealIpSnippet;
use App\Services\CustomerInstallation\Ssl\NginxVhostTemplate;
use PHPUnit\Framework\TestCase;

/**
 * The real-IP snippet that customer vhosts include was never provisioned, and
 * installation 49 failed `nginx -t` (2026-11-17). These tests pin the central
 * source and the idempotent install command NginxVhostWriter runs.
 */
class ArvanRealIpSnippetTest extends TestCase
{
    public function test_template_include_points_at_the_provisioned_path(): void
    {
        $snippet = new ArvanRealIpSnippet();

        $this->assertSame('include snippets/arvan-cdn-real-ip.conf;', $snippet->includeDirective());
        $this->assertSame('/etc/nginx/' . ArvanRealIpSnippet::INCLUDE_PATH, ArvanRealIpSnippet::REMOTE_PATH);

        $config = (new NginxVhostTemplate())->render('t.example.test', '/var/www/t', '/run/php/php8.3-fpm.sock', null);
        $this->assertStringContainsString($snippet->includeDirective(), $config);
    }

    public function test_ranges_are_exactly_arvans_published_list(): void
    {
        // https://www.arvancloud.ir/en/ips.txt, fetched 2026-11-17. A change
        // here widens or narrows who may set Ar-Real-Ip: update deliberately.
        $this->assertSame([
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
        ], ArvanRealIpSnippet::RANGES);
    }

    public function test_every_range_is_a_canonical_ipv4_network(): void
    {
        foreach (ArvanRealIpSnippet::RANGES as $range) {
            [$ip, $bits] = explode('/', $range);
            $this->assertNotFalse(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4), $range);
            $this->assertGreaterThanOrEqual(8, (int) $bits, $range);
            $this->assertLessThanOrEqual(32, (int) $bits, $range);
            $mask = $bits === '0' ? 0 : (~0 << (32 - (int) $bits)) & 0xFFFFFFFF;
            $this->assertSame(ip2long($ip), ip2long($ip) & $mask, "{$range} has host bits set");
        }
    }

    public function test_render_trusts_every_arvan_range_with_the_arvan_header(): void
    {
        $content = (new ArvanRealIpSnippet())->render();

        $this->assertSame(count(ArvanRealIpSnippet::RANGES), substr_count($content, 'set_real_ip_from '));
        foreach (ArvanRealIpSnippet::RANGES as $range) {
            $this->assertMatchesRegularExpression('#^\d{1,3}(\.\d{1,3}){3}/\d{1,2}$#', $range);
            $this->assertStringContainsString("set_real_ip_from {$range};\n", $content);
        }
        $this->assertStringContainsString("real_ip_header Ar-Real-Ip;\n", $content);
        $this->assertStringContainsString("real_ip_recursive on;\n", $content);
        $this->assertStringEndsWith("\n", $content);
        $this->assertStringNotContainsString("\r", $content);
    }

    public function test_ensure_command_never_overwrites_and_writes_render_output_atomically(): void
    {
        $snippet = new ArvanRealIpSnippet();
        $command = $snippet->ensureCommand();

        $this->assertStringStartsWith("if test -s '/etc/nginx/snippets/arvan-cdn-real-ip.conf'; then echo present;", $command);
        $this->assertStringContainsString("mkdir -p '/etc/nginx/snippets'", $command);
        $this->assertStringContainsString("> '/etc/nginx/snippets/arvan-cdn-real-ip.conf.tmp'", $command);
        $this->assertStringContainsString("mv -f '/etc/nginx/snippets/arvan-cdn-real-ip.conf.tmp' '/etc/nginx/snippets/arvan-cdn-real-ip.conf'", $command);
        $this->assertStringContainsString("'" . base64_encode($snippet->render()) . "'", $command);
        $this->assertStringNotContainsString('nginx -t', $command);
    }

    public function test_ensure_command_uses_posix_single_quotes_on_every_host_os(): void
    {
        // escapeshellarg() would emit double quotes on Windows, where $x expands.
        $command = (new ArvanRealIpSnippet())->ensureCommand("/srv/it's \$x/a.conf");

        $this->assertStringStartsWith("if test -s '/srv/it'\\''s \$x/a.conf'; then", $command);
        $this->assertStringContainsString("mkdir -p '/srv/it'\\''s \$x'", $command);
        $this->assertStringNotContainsString('"', $command);
    }

    public function test_ensure_command_is_idempotent_in_a_real_posix_shell(): void
    {
        $sh = $this->findShell();
        if ($sh === null) {
            $this->markTestSkipped('No POSIX sh with base64 available on this host.');
        }

        $dir  = sys_get_temp_dir() . '/arvan-snippet-' . bin2hex(random_bytes(4));
        $path = str_replace('\\', '/', $dir) . '/snippets/arvan-cdn-real-ip.conf';
        $snippet = new ArvanRealIpSnippet();

        try {
            [$code, $out] = $this->runShell($sh, $snippet->ensureCommand($path));
            $this->assertSame(0, $code, $out);
            $this->assertSame('installed', trim($out));
            $this->assertSame($snippet->render(), file_get_contents($path));
            $this->assertFileDoesNotExist($path . '.tmp');

            [$code, $out] = $this->runShell($sh, $snippet->ensureCommand($path));
            $this->assertSame(0, $code, $out);
            $this->assertSame('present', trim($out));

            // An operator-maintained snippet is left untouched.
            file_put_contents($path, "set_real_ip_from 10.0.0.0/8;\n");
            [$code, $out] = $this->runShell($sh, $snippet->ensureCommand($path));
            $this->assertSame(0, $code, $out);
            $this->assertSame('present', trim($out));
            $this->assertSame("set_real_ip_from 10.0.0.0/8;\n", file_get_contents($path));
        } finally {
            @unlink($path);
            @unlink($path . '.tmp');
            @rmdir(dirname($path));
            @rmdir($dir);
        }
    }

    public function test_ensure_command_fails_when_the_target_cannot_be_written(): void
    {
        $sh = $this->findShell();
        if ($sh === null) {
            $this->markTestSkipped('No POSIX sh with base64 available on this host.');
        }

        // The parent "directory" is a regular file, so mkdir -p must fail.
        $blocker = tempnam(sys_get_temp_dir(), 'arvan-blocker-');
        $path = str_replace('\\', '/', $blocker) . '/snippets/arvan-cdn-real-ip.conf';

        try {
            [$code, $out] = $this->runShell($sh, (new ArvanRealIpSnippet())->ensureCommand($path));
            $this->assertNotSame(0, $code);
            $this->assertStringNotContainsString('installed', $out);
            $this->assertStringNotContainsString('present', $out);
        } finally {
            @unlink($blocker);
        }
    }

    private function findShell(): ?string
    {
        foreach (['sh', 'C:/Program Files/Git/usr/bin/sh.exe', 'C:/Program Files/Git/bin/sh.exe'] as $candidate) {
            [$code, $out] = $this->runShell($candidate, 'echo ok | base64 | base64 -d');
            if ($code === 0 && trim($out) === 'ok') {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array{0: int, 1: string} */
    private function runShell(string $sh, string $command): array
    {
        $process = @proc_open([$sh, '-c', $command], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return [127, ''];
        }
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out];
    }
}
