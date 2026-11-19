<?php

namespace App\Http\Requests\Admin;

use App\Services\Onboarding\OnboardingDomainPolicy;
use App\Services\Onboarding\SiteOnboardingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADR-088 — shape validation for the unified onboarding wizard. The
 * business rules (mobile ownership, domain availability, theme eligibility,
 * kit/vertical consistency, driver support) are enforced by
 * SiteOnboardingService::start(), which runs them again on the server
 * whatever the browser already checked.
 */
class StartOnboardingRequest extends FormRequest
{
    /** Characters that would break the quoted APP_NAME / ADMIN_NAME .env lines. */
    private const ENV_UNSAFE = '/["\\\\$`\r\n]/';

    public function authorize(): bool
    {
        return true; // admin route group middleware
    }

    public function rules(): array
    {
        return [
            'mobile'            => ['required', 'string', 'max:20'],
            'customer_name'     => ['nullable', 'string', 'max:255', 'not_regex:' . self::ENV_UNSAFE],
            'company_name'      => ['nullable', 'string', 'max:255'],
            'site_title'        => ['required', 'string', 'max:120', 'not_regex:' . self::ENV_UNSAFE],
            'domain_mode'       => ['required', Rule::in([OnboardingDomainPolicy::MODE_SUBDOMAIN, OnboardingDomainPolicy::MODE_CUSTOM])],
            'domain'            => ['required', 'string', 'max:253'],
            'license_type'      => ['required', Rule::in(array_keys(SiteOnboardingService::LICENSE_TYPES))],
            'business_vertical' => ['nullable', 'string', 'max:64'],
            'kit_module_ids'    => ['nullable', 'array'],
            'kit_module_ids.*'  => ['integer'],
            'active_module_ids' => ['nullable', 'array'],
            'active_module_ids.*' => ['integer'],
            'base_modules'      => ['nullable', 'array'],
            'base_modules.*'    => ['string', Rule::in(array_keys(SiteOnboardingService::BASE_MODULES))],
            'theme_id'          => ['nullable', 'integer'],
            'remote_server_id'  => ['required', 'integer', 'exists:customer_remote_servers,id'],
            'ssl_enabled'       => ['nullable', 'boolean'],
            'backup_policy'     => ['required', Rule::in(array_keys(SiteOnboardingService::BACKUP_POLICIES))],
            // Infrastructure & Database customization
            'web_root_path'     => ['nullable', 'string', 'max:255', 'not_regex:' . self::ENV_UNSAFE],
            'db_name'           => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'db_user'           => ['nullable', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/'],
            'db_host'           => ['nullable', 'string', 'max:255'],
            'db_password'       => ['nullable', 'string', 'max:128', 'not_regex:' . self::ENV_UNSAFE],
            // Hardware Resource Allocation
            'resource_plan'     => ['nullable', 'string', 'max:64'],
            'ram_mb'            => ['nullable', 'integer', 'min:256', 'max:131072'],
            'disk_gb'           => ['nullable', 'numeric', 'min:1', 'max:4096'],
            'soft_disk_gb'      => ['nullable', 'numeric', 'min:0.5', 'max:4096'],
            'cpu_cores'         => ['nullable', 'numeric', 'min:0.25', 'max:64'],
            'bandwidth_gb'      => ['nullable', 'numeric', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required'           => 'شماره موبایل مشتری الزامی است.',
            'site_title.required'       => 'نام سایت الزامی است.',
            'site_title.not_regex'      => 'نام سایت نباید شامل نویسه‌های " \\ $ ` یا شکست خط باشد.',
            'customer_name.not_regex'   => 'نام مشتری نباید شامل نویسه‌های " \\ $ ` یا شکست خط باشد.',
            'domain.required'           => 'دامنه یا زیردامنه الزامی است.',
            'domain_mode.in'            => 'نوع دامنه نامعتبر است.',
            'license_type.in'           => 'نوع لایسنس نامعتبر است.',
            'remote_server_id.required' => 'سرور مقصد را انتخاب کنید.',
            'remote_server_id.exists'   => 'سرور مقصد یافت نشد.',
            'backup_policy.in'          => 'سیاست پشتیبان‌گیری نامعتبر است.',
        ];
    }
}
