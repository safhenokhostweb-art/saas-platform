<?php

namespace Tests\Support\CustomerDeletion;

use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CommandResult;

/**
 * ADR-090 test double: a FakeRemoteDriver with a tiny simulated server, so
 * purge stages can be asserted on outcomes (what is gone, what survived)
 * rather than only on the command strings they emit.
 *
 * Simulated: path existence (test -e/-f/-d, readlink -f), rm -rf / rm -f, mv,
 * `nginx -t` validity, certbot delete, grep over file contents, and a set of
 * databases / users for the mysql probes. Every other command falls through
 * to FakeRemoteDriver (logged, exit 0, empty stdout).
 */
class FakePurgeServer extends FakeRemoteDriver
{
    /** @var array<string, string> path => contents ('' for directories) */
    public array $files = [];

    public array $databases = [];
    public array $dbUsers = [];

    /** nginx -t result; a callable receives $this. */
    public $nginxValid = true;

    public function put(string $path, string $contents = ''): self
    {
        $this->files[$path] = $contents;

        return $this;
    }

    public function exists(string $path): bool
    {
        $path = rtrim($path, '/');
        if (array_key_exists($path, $this->files)) {
            return true;
        }
        foreach (array_keys($this->files) as $p) {
            if (str_starts_with($p, $path . '/')) {
                return true;
            }
        }

        return false;
    }

    public function execute(string $command, array $env = []): CommandResult
    {
        parent::execute($command, $env); // keeps the command log
        $args = $this->quotedArgs($command);

        if (preg_match('/^test -[efd] /', $command)) {
            return $this->out($this->exists($args[0]) ? 'yes' : 'no');
        }
        if (str_starts_with($command, 'readlink -f -- ')) {
            return $this->out($this->exists($args[0]) ? rtrim($args[0], '/') : '');
        }
        if (str_starts_with($command, 'du -sb ')) {
            return $this->out('4096');
        }
        if (str_starts_with($command, 'rm -rf ') || str_starts_with($command, 'rm -f ')) {
            foreach ($args as $path) {
                $this->remove($path);
            }

            return $this->out('');
        }
        if (str_starts_with($command, 'mv -f -- ')) {
            // Arguments look like 'quoted' or 'quoted'/suffix (backup dir + name).
            // (escapeshellarg quotes with ' on Linux and " on Windows.)
            preg_match_all('/([\'"])(.*?)\1(\S*)/', $command, $mm, PREG_SET_ORDER);
            $from = ($mm[0][2] ?? '') . ($mm[0][3] ?? '');
            $to   = ($mm[1][2] ?? '') . ($mm[1][3] ?? '');
            if ($from !== '' && $to !== '' && $this->exists($from)) {
                $this->files[$to] = $this->files[$from] ?? '';
                $this->remove($from);
            }

            return $this->out('');
        }
        if (str_starts_with($command, 'nginx -t')) {
            $valid = is_callable($this->nginxValid) ? ($this->nginxValid)($this) : (bool) $this->nginxValid;

            return new CommandResult(exitCode: $valid ? 0 : 1, stdout: $valid ? 'syntax is ok' : 'emerg', stderr: '', durationMs: 1);
        }
        if (preg_match('/certbot\'? delete --cert-name /', $command) || str_contains($command, ' delete --cert-name ')) {
            $name = $args[0] ?? '';
            $this->remove("/etc/letsencrypt/renewal/{$name}.conf");
            $this->remove("/etc/letsencrypt/live/{$name}");
            $this->remove("/etc/letsencrypt/archive/{$name}");

            return $this->out('Deleted');
        }
        if (str_starts_with($command, 'grep -qF -- ')) {
            [$needle, $file] = [$args[0] ?? '', $args[1] ?? ''];
            $owned = array_key_exists($file, $this->files) && str_contains($this->files[$file], $needle);

            return $this->out($owned ? 'owned' : 'foreign');
        }
        if (str_starts_with($command, 'grep -rlsF -- ')) {
            $needle = $args[0] ?? '';
            foreach ($this->files as $path => $contents) {
                if (str_starts_with($path, '/etc/nginx/') && str_contains($contents, $needle)) {
                    return $this->out($path);
                }
            }

            return $this->out('');
        }
        if (str_contains($command, 'DROP DATABASE IF EXISTS')) {
            preg_match('/DROP DATABASE IF EXISTS `([^`]+)`/', $command, $m);
            $this->databases = array_values(array_diff($this->databases, [$m[1] ?? '']));
        }
        if (str_contains($command, 'DROP USER IF EXISTS')) {
            preg_match("/DROP USER IF EXISTS '([^']+)'/", $command, $m);
            $this->dbUsers = array_values(array_diff($this->dbUsers, [$m[1] ?? '']));
        }
        if (str_contains($command, 'SHOW DATABASES LIKE')) {
            preg_match("/LIKE '([^']+)'/", str_replace(["'\\''", '"'], ["'", ''], $command), $m);

            return $this->out(in_array($m[1] ?? '', $this->databases, true) ? "Database\n" . $m[1] : '');
        }
        if (str_contains($command, 'FROM mysql.user WHERE User=')) {
            preg_match("/User='([^']+)'/", str_replace(["'\\''", '"'], ["'", ''], $command), $m);

            return $this->out(in_array($m[1] ?? '', $this->dbUsers, true) ? "User\n" . $m[1] : '');
        }

        return new CommandResult(exitCode: 0, stdout: '', stderr: '', durationMs: 1);
    }

    private function remove(string $path): void
    {
        $path = rtrim($path, '/');
        unset($this->files[$path]);
        foreach (array_keys($this->files) as $p) {
            if (str_starts_with($p, $path . '/')) {
                unset($this->files[$p]);
            }
        }
    }

    /**
     * Values of the escapeshellarg() arguments, in order: single-quoted on
     * Linux (production), double-quoted on Windows (local test runs).
     */
    private function quotedArgs(string $command): array
    {
        preg_match_all('/\'([^\']*)\'|"([^"]*)"/', $command, $m, PREG_SET_ORDER);

        return array_map(fn ($match) => ($match[2] ?? '') !== '' ? $match[2] : $match[1], $m);
    }

    private function out(string $stdout): CommandResult
    {
        return new CommandResult(exitCode: 0, stdout: $stdout, stderr: '', durationMs: 1);
    }
}
