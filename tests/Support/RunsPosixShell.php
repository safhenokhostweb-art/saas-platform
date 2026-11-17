<?php

namespace Tests\Support;

/**
 * Runs remote-style shell commands in a real local POSIX sh (Git's sh.exe
 * on Windows), so tests can prove what a command does to a file instead of
 * asserting on its text.
 */
trait RunsPosixShell
{
    private ?string $posixShell = null;

    private bool $posixShellProbed = false;

    protected function requirePosixShell(): string
    {
        if (!$this->posixShellProbed) {
            $this->posixShellProbed = true;
            foreach (['sh', 'C:/Program Files/Git/usr/bin/sh.exe', 'C:/Program Files/Git/bin/sh.exe'] as $candidate) {
                [$code, $out] = $this->runInShell("printf 'a|b\\n' | sed 's|a|x|'; printf ok | base64 | base64 -d; echo; awk 'BEGIN { print ENVIRON[\"HOME\"] != \"\" }'", $candidate);
                if ($code === 0 && preg_match('/^x\|b\nok\n1$/', trim(str_replace("\r", '', $out))) === 1) {
                    $this->posixShell = $candidate;
                    break;
                }
            }
        }

        if ($this->posixShell === null) {
            $this->markTestSkipped('No POSIX sh with sed, awk and base64 available on this host.');
        }

        return $this->posixShell;
    }

    /**
     * The command reaches sh as a script file, byte for byte, the way SSH
     * delivers it. As an argv item, Windows would re-quote `"` and `\` first.
     *
     * @return array{0: int, 1: string}
     */
    protected function runInShell(string $command, ?string $sh = null): array
    {
        $sh ??= $this->requirePosixShell();
        $script = str_replace('\\', '/', tempnam(sys_get_temp_dir(), 'cie-sh-'));
        file_put_contents($script, $command . "\n");

        $process = @proc_open([$sh, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            @unlink($script);

            return [127, ''];
        }
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        @unlink($script);

        return [$code, $out];
    }

    protected function tempEnvFile(string $content): string
    {
        $file = str_replace('\\', '/', tempnam(sys_get_temp_dir(), 'cie-env-'));
        file_put_contents($file, $content);

        return $file;
    }
}
