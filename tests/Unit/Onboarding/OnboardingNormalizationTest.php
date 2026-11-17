<?php

namespace Tests\Unit\Onboarding;

use App\Services\Onboarding\OnboardingDomainPolicy;
use App\Support\IranianMobileNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** ADR-088 — the identity and host normalisation the wizard relies on. */
class OnboardingNormalizationTest extends TestCase
{
    public static function mobiles(): array
    {
        return [
            'canonical'              => ['09123456789', '09123456789'],
            'persian digits'         => ['۰۹۱۲۳۴۵۶۷۸۹', '09123456789'],
            'arabic-indic digits'    => ['٠٩١٢٣٤٥٦٧٨٩', '09123456789'],
            'spaced and dashed'      => ['0912-345 6789', '09123456789'],
            'plus country code'      => ['+98 912 345 6789', '09123456789'],
            'double-zero country'    => ['00989123456789', '09123456789'],
            'bare ten digits'        => ['9123456789', '09123456789'],
            'landline'               => ['02112345678', null],
            'too short'              => ['0912345', null],
            'letters'                => ['not-a-number', null],
            'empty'                  => ['', null],
        ];
    }

    #[DataProvider('mobiles')]
    public function test_mobile_numbers_normalise_to_one_canonical_identity(string $input, ?string $expected): void
    {
        $this->assertSame($expected, IranianMobileNumber::normalize($input));
        $this->assertSame($expected !== null, IranianMobileNumber::isValid($input));
    }

    public function test_non_scalar_mobile_is_rejected(): void
    {
        $this->assertNull(IranianMobileNumber::normalize(['09123456789']));
    }

    public static function hosts(): array
    {
        return [
            'plain'           => ['example.ir', 'example.ir'],
            'uppercase'       => ['Example.IR', 'example.ir'],
            'scheme and path' => ['https://shop.example.ir/fa/home?x=1', 'shop.example.ir'],
            'leading www'     => ['www.example.ir', 'example.ir'],
            'port'            => ['example.ir:8443', 'example.ir'],
            'trailing dot'    => ['example.ir.', 'example.ir'],
            'whitespace'      => ['  example.ir  ', 'example.ir'],
        ];
    }

    #[DataProvider('hosts')]
    public function test_hosts_normalise_without_scheme_path_port_or_www(string $input, string $expected): void
    {
        $this->assertSame($expected, OnboardingDomainPolicy::normalizeHost($input));
    }
}
