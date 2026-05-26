<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

/**
 * Contract for captcha verification services.
 * The host app binds its concrete CaptchaService to this interface.
 * Packages inject this interface and never import App\Services\CaptchaService.
 */
interface CaptchaServiceInterface
{
    public const TYPE_PERSIAN_NUMBER = 'persian_number';
    public const TYPE_NORMAL = 'normal';
    public const TYPE_HCAPTCHA = 'hcaptcha';

    public function isEnabled(): bool;

    /** @return array{enabled: bool, type: ?string, hcaptcha_site_key: string, hcaptcha_secret_key: string} */
    public function getConfigForSite(?int $siteId): array;

    public function generatePersianNumber(int $length): string;

    /** @return array{text: string, hash: string} */
    public function generateNormal(): array;

    public function validatePersianNumber(string $answer): bool;

    public function validateNormal(string $answer): bool;

    public function verifyHcaptcha(string $token, string $secretKey): bool;
}
