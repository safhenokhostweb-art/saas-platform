<?php

namespace App\Http\Requests\Admin;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * ADR-090 — confirmation of an irreversible site purge.
 *
 * - super-admin only (and `sites.purge`, which super-admin implies);
 * - the operator types the site's domain exactly;
 * - re-authenticates with their current password in the same request (a
 *   per-action check — stricter than a session-wide password.confirm window);
 * - acknowledges irreversibility;
 * - submits the hash of the dry-run plan they were shown, which the
 *   controller compares with a freshly built plan.
 */
class SitePurgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return self::allowed($this->user());
    }

    public static function allowed($user): bool
    {
        return $user !== null
            && method_exists($user, 'isSuperAdmin')
            && $user->isSuperAdmin()
            && $user->hasPermission('sites.purge');
    }

    public function rules(): array
    {
        return [
            'confirm_domain'   => ['required', 'string', 'max:255'],
            'current_password' => ['required', 'string', 'current_password'],
            'acknowledge'      => ['accepted'],
            'delete_backups'   => ['nullable', 'boolean'],
            'delete_media'     => ['nullable', 'boolean'],
            'plan_hash'        => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Site $site */
            $site = $this->route('site');
            $typed = strtolower(trim((string) $this->input('confirm_domain')));

            if ($site && ($site->domain === null || $typed !== strtolower(trim($site->domain)))) {
                $validator->errors()->add('confirm_domain', 'دامنهٔ وارد شده با دامنهٔ سایت مطابقت ندارد.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'رمز عبور فعلی شما درست نیست.',
            'acknowledge.accepted'              => 'باید تأیید کنید که این عملیات غیرقابل بازگشت است.',
            'plan_hash.*'                       => 'پیش‌نمایش حذف معتبر نیست؛ صفحه را دوباره باز کنید.',
        ];
    }

    public function deleteBackups(): bool
    {
        return $this->boolean('delete_backups');
    }

    public function deleteMedia(): bool
    {
        return $this->boolean('delete_media');
    }
}
