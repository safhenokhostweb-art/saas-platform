<?php
namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @file store-user-request.php
 * قانون ۴: اعتبارسنجی ورودی - عدم اعتماد به ورودی کاربر
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile'     => ['required', 'string', 'unique:users,mobile', 'regex:/^09[0-9]{9}$/'],
            'password'   => ['nullable', 'string', 'min:8'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
            'bio'        => ['nullable', 'string', 'max:1000'],
            'is_active'  => ['boolean'],
            'is_admin'   => ['boolean'],
            'roles'      => ['nullable', 'array'],
            'roles.*'    => ['exists:roles,id'],
            'groups'     => ['nullable', 'array'],
            'groups.*'   => ['exists:user_groups,id'],
            'tags'       => ['nullable', 'array'],
            'tags.*'     => ['exists:tags,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.unique'   => 'این شماره موبایل قبلاً ثبت شده است.',
            'mobile.regex'    => 'شماره موبایل باید با 09 شروع شود و 11 رقم باشد.',
            'password.min'    => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'is_admin'  => $this->boolean('is_admin', false),
        ]);
    }
}