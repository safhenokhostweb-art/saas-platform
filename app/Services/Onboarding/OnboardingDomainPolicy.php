<?php

namespace App\Services\Onboarding;

use App\Models\CustomerInstallation;
use App\Models\Site;
use App\Services\CustomerInstallation\InstallationTargetGuard;

/**
 * ADR-088 — the onboarding wizard's domain rules: normalisation, the
 * platform-subdomain vs custom-domain split, reserved labels, and
 * availability across existing sites and installations still in flight.
 * Used by the live check endpoint and again, authoritatively, on submit.
 */
class OnboardingDomainPolicy
{
    public const MODE_SUBDOMAIN = 'subdomain';

    public const MODE_CUSTOM = 'custom';

    private const IN_FLIGHT = ['pending', 'running', 'rolling_back'];

    public function __construct(private readonly InstallationTargetGuard $guard) {}

    public function platformRootDomain(): string
    {
        return $this->guard->platformRootDomain();
    }

    /** Lowercase host without scheme, path, port, trailing dot or a leading "www.". */
    public static function normalizeHost(string $input): string
    {
        $host = strtolower(trim($input));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host) ?? '';
        $host = preg_replace('#[/?\#].*$#', '', $host) ?? '';
        $host = preg_replace('#:\d+$#', '', $host) ?? '';
        $host = rtrim($host, '.');

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Resolve the wizard's domain input to a full host and the first rule it
     * breaks. Availability is not checked here (see check()).
     *
     * @return array{domain: ?string, error: ?string}
     */
    public function resolve(string $mode, string $input): array
    {
        $value = self::normalizeHost($input);

        if ($mode === self::MODE_SUBDOMAIN) {
            $root = $this->platformRootDomain();
            if ($root === '') {
                return ['domain' => null, 'error' => 'دامنهٔ اصلی پلتفرم در تنظیمات DNS Provider ثبت نشده است؛ از دامنهٔ اختصاصی استفاده کنید.'];
            }
            if (str_ends_with($value, '.' . $root)) {
                $value = substr($value, 0, -strlen('.' . $root));
            }
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/', $value) !== 1 || strlen($value) < 3) {
                return ['domain' => null, 'error' => 'زیردامنه باید ۳ تا ۶۳ نویسهٔ لاتین کوچک، رقم یا خط تیره باشد و با خط تیره شروع یا تمام نشود.'];
            }
            if (in_array($value, (array) config('onboarding.reserved_subdomains', []), true)) {
                return ['domain' => null, 'error' => "زیردامنهٔ «{$value}» برای پلتفرم رزرو شده است."];
            }

            return ['domain' => $value . '.' . $root, 'error' => null];
        }

        if ($value === '' || strlen($value) > 253
            || preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $value) !== 1) {
            return ['domain' => null, 'error' => 'دامنه معتبر نیست. نمونهٔ درست: example.ir'];
        }

        return ['domain' => $value, 'error' => null];
    }

    /** Persian error when the host already belongs to a site or an in-flight installation. */
    public function availabilityError(string $domain): ?string
    {
        $site = Site::query()->with('customer')
            ->whereIn('domain', [$domain, 'www.' . $domain, 'https://' . $domain, 'http://' . $domain])
            ->first();
        if ($site !== null) {
            $owner = $site->customer?->name ? " (مشتری: {$site->customer->name})" : '';

            return "دامنهٔ «{$domain}» قبلاً به سایت «{$site->title}»{$owner} اختصاص یافته است.";
        }

        $installing = CustomerInstallation::query()
            ->whereIn('status', self::IN_FLIGHT)
            ->whereIn('customer_domain', [$domain, 'www.' . $domain])
            ->exists();
        if ($installing) {
            return "برای دامنهٔ «{$domain}» یک نصب در حال اجراست.";
        }

        return null;
    }

    /**
     * Full check used by the live endpoint and on submit.
     *
     * @return array{ok: bool, domain: ?string, error: ?string, platform_subdomain: bool}
     */
    public function check(string $mode, string $input, bool $withDns = true): array
    {
        $resolved = $this->resolve($mode, $input);
        $domain = $resolved['domain'];
        $error = $resolved['error'] ?? ($domain !== null ? $this->availabilityError($domain) : null);

        if ($error === null && $domain !== null && $withDns && $mode === self::MODE_CUSTOM) {
            $error = $this->guard->customDomainDnsError($domain);
        }

        return [
            'ok'                 => $error === null && $domain !== null,
            'domain'             => $domain,
            'error'              => $error,
            'platform_subdomain' => $domain !== null && $this->guard->isPlatformSubdomain($domain),
        ];
    }
}
