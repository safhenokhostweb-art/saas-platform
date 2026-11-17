<?php

namespace Tests\Unit\Services\CustomerInstallation\Env;

use App\Services\CustomerInstallation\Env\DotenvValue;
use App\Services\CustomerInstallation\Env\RemoteEnvFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\RunsPosixShell;

/**
 * Installation 52 (2026-11-17) failed PROVISION_LICENSE on a wrong check
 * of the LICENSE_KEY line. RemoteEnvFile::set() is the shared upsert for
 * .env keys written after GENERATE_ENVIRONMENT. These cases run the real
 * command in sh and decode the file with phpdotenv, as Laravel will.
 */
class RemoteEnvFileTest extends TestCase
{
    use RunsPosixShell;

    private const KEY = 'ABCD-EF12-GH34-IJ56';

    /** @return array{0: string, 1: string} [file content after, stdout] */
    private function set(string $content, string $key, string $value): array
    {
        $file = $this->tempEnvFile($content);
        try {
            [$code, $out] = $this->runInShell(RemoteEnvFile::set($file, $key, $value));
            $this->assertSame(0, $code, $out);
            $this->assertStringContainsString(RemoteEnvFile::SET_OK, $out);

            return [file_get_contents($file), $out];
        } finally {
            @unlink($file);
        }
    }

    public function test_the_generate_environment_placeholder_is_replaced_and_nothing_else_changes(): void
    {
        $before = "APP_NAME=Solar\nLICENSE_KEY=__LICENSE_KEY__\nLICENSE_VERIFY_SSL=false\n";

        [$after] = $this->set($before, 'LICENSE_KEY', self::KEY);

        $this->assertSame("APP_NAME=Solar\nLICENSE_KEY=" . self::KEY . "\nLICENSE_VERIFY_SSL=false\n", $after);
    }

    public function test_a_missing_key_is_appended(): void
    {
        [$after] = $this->set("APP_NAME=Solar\n", 'LICENSE_KEY', self::KEY);

        $this->assertSame("APP_NAME=Solar\nLICENSE_KEY=" . self::KEY . "\n", $after);
    }

    public function test_a_missing_final_newline_does_not_glue_the_key_to_the_last_line(): void
    {
        [$after] = $this->set('APP_NAME=Solar', 'LICENSE_KEY', self::KEY);

        $this->assertSame("APP_NAME=Solar\nLICENSE_KEY=" . self::KEY . "\n", $after);
    }

    public function test_an_empty_file_gets_the_key(): void
    {
        [$after] = $this->set('', 'LICENSE_KEY', self::KEY);

        $this->assertSame('LICENSE_KEY=' . self::KEY . "\n", $after);
    }

    /** @return iterable<string, array{string}> */
    public static function existingLines(): iterable
    {
        yield 'empty value' => ['LICENSE_KEY='];
        yield 'double-quoted' => ['LICENSE_KEY="OLD KEY #1"'];
        yield 'single-quoted' => ["LICENSE_KEY='old'"];
        yield 'old CIE format' => ['LICENSE_KEY=CIE-OLD-KEY'];
    }

    /** @dataProvider existingLines */
    public function test_any_existing_value_is_replaced(string $existing): void
    {
        [$after] = $this->set("A=1\n{$existing}\nB=2\n", 'LICENSE_KEY', self::KEY);

        $this->assertSame("A=1\nLICENSE_KEY=" . self::KEY . "\nB=2\n", $after);
    }

    public function test_duplicates_collapse_to_one_line(): void
    {
        [$after] = $this->set("LICENSE_KEY=a\nX=1\nLICENSE_KEY=b\n", 'LICENSE_KEY', self::KEY);

        $this->assertSame("LICENSE_KEY=" . self::KEY . "\nX=1\n", $after);
    }

    public function test_similar_keys_and_comments_are_left_alone(): void
    {
        $before = "# LICENSE_KEY=commented\nLICENSE_KEY_OLD=x\nMY_LICENSE_KEY=y\n";

        [$after] = $this->set($before, 'LICENSE_KEY', self::KEY);

        $this->assertSame($before . 'LICENSE_KEY=' . self::KEY . "\n", $after);
    }

    public function test_setting_twice_is_idempotent(): void
    {
        [$once] = $this->set("LICENSE_KEY=__LICENSE_KEY__\n", 'LICENSE_KEY', self::KEY);
        [$twice] = $this->set($once, 'LICENSE_KEY', self::KEY);

        $this->assertSame($once, $twice);
    }

    /** @return iterable<string, array{string}> */
    public static function hostileValues(): iterable
    {
        yield 'hash' => ['ab#cd'];
        yield 'dollar and quotes' => ["\$HOME \"q\" 'x'"];
        yield 'backslashes, ampersand, pipe' => ['a\\b&c|d'];
        yield 'awk escapes' => ['\\n\\t%s'];
        yield 'persian' => ['کلید «ویژه» #۱'];
    }

    /** @dataProvider hostileValues */
    public function test_hostile_values_decode_exactly(string $value): void
    {
        [$after] = $this->set("X=1\nSOME_KEY=old\n", 'SOME_KEY', $value);

        $this->assertSame($value, DotenvValue::parse($after)['SOME_KEY']);
        $this->assertSame('1', DotenvValue::parse($after)['X']);
    }

    public function test_read_and_decode_return_the_values_laravel_sees(): void
    {
        $file = $this->tempEnvFile("DB_DATABASE=cust_x_1\nDB_USERNAME=u_x\nDB_PASSWORD=\"p=a#ss\"\nOTHER=1\n");
        try {
            [$code, $out] = $this->runInShell(RemoteEnvFile::read($file, ['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']));
        } finally {
            @unlink($file);
        }

        $this->assertSame(0, $code);
        $this->assertSame(
            ['DB_DATABASE' => 'cust_x_1', 'DB_USERNAME' => 'u_x', 'DB_PASSWORD' => 'p=a#ss'],
            RemoteEnvFile::decode($out),
        );
        $this->assertSame([], RemoteEnvFile::decode("  \n"));
    }

    public function test_a_missing_file_reports_failure(): void
    {
        $dir = str_replace('\\', '/', sys_get_temp_dir()) . '/cie-missing-' . bin2hex(random_bytes(4));

        [$code, $out] = $this->runInShell(RemoteEnvFile::set($dir . '/.env', 'LICENSE_KEY', self::KEY));

        $this->assertNotSame(0, $code);
        $this->assertStringContainsString(RemoteEnvFile::SET_FAILED, $out);
    }

    public function test_unsafe_keys_and_multiline_values_are_refused(): void
    {
        foreach (['license_key', 'KEY;rm', 'A-B', ''] as $key) {
            try {
                RemoteEnvFile::set('/x/.env', $key, 'v');
                $this->fail("key accepted: {$key}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        RemoteEnvFile::set('/x/.env', 'LICENSE_KEY', "a\nb");
    }
}
