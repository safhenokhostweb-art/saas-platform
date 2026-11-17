<?php

namespace Tests\Unit\Services\CustomerInstallation\Env;

use App\Services\CustomerInstallation\Env\DotenvValue;
use PHPUnit\Framework\TestCase;

/**
 * Installation 51 (2026-11-17): `DB_PASSWORD=<pw>` was written unquoted, the
 * password contained `#`, and phpdotenv (what Laravel reads) cut the value
 * there. MySQL answered 1045 "Access denied ... (using password: YES)".
 */
class DotenvValueTest extends TestCase
{
    public function test_phpdotenv_truncates_an_unquoted_value_at_any_hash(): void
    {
        // The exact failure mode, pinned against the library itself.
        $this->assertSame('aB3xY9', DotenvValue::parse('DB_PASSWORD=aB3xY9#kLm2!')['DB_PASSWORD']);
        $this->assertSame('', DotenvValue::parse('DB_PASSWORD=#aB3xY9kLm2')['DB_PASSWORD']);
    }

    /** @return iterable<string, array{string}> */
    public static function hostileValues(): iterable
    {
        yield 'hash inside' => ['aB3xY9#kLm2!'];
        yield 'leading hash' => ['#leading'];
        yield 'dollar interpolation' => ['pa$HOME$x'];
        yield 'double quotes' => ['say "hi"'];
        yield 'single quote' => ["it's"];
        yield 'backslashes' => ['C:\\path\\n\\t'];
        yield 'spaces' => ['two words  and # more'];
        yield 'old charset specials' => ['!@#%^*_+=:,.?~'];
        yield 'persian company' => ['شرکت "خورشید" #۱ & پارس'];
        yield 'new charset' => ['Ab9-x_y.Z'];
        yield 'empty' => [''];
    }

    /** @dataProvider hostileValues */
    public function test_encoded_values_read_back_exactly(string $value): void
    {
        $line = DotenvValue::line('VALUE', $value);

        $this->assertSame($value, DotenvValue::parse($line)['VALUE'] ?? '', "line: {$line}");
    }

    public function test_safe_values_stay_unquoted(): void
    {
        $this->assertSame('Ab9-x_y.Z', DotenvValue::encode('Ab9-x_y.Z'));
        $this->assertSame('https://panel.example.ir', DotenvValue::encode('https://panel.example.ir'));
        $this->assertSame('"a#b"', DotenvValue::encode('a#b'));
    }
}
