<?php
namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->input('user_id');

        return [
            'mobile'     => ['sometimes', 'required', 'string', Rule::unique('users', 'mobile')->ignore($userId), 'regex:/^09[0-9]{9}$/'],
            'email'      => ['sometimes', 'nullable', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password'   => ['sometimes', 'nullable', 'string', 'min:8'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio'        => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active'  => ['sometimes', 'boolean'],
            'is_admin'   => ['sometimes', 'boolean'],
            'roles'      => ['sometimes', 'nullable', 'array'],
            'roles.*'    => ['exists:roles,id'],
            'groups'     => ['sometimes', 'nullable', 'array'],
            'groups.*'   => ['exists:user_groups,id'],
            'tags'       => ['sometimes', 'nullable', 'array'],
            'tags.*'     => ['exists:tags,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
            'mobile.regex'  => 'شماره موبایل معتبر نیست.',
            'email.email'   => 'ایمیل معتبر نیست.',
            'password.min'  => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
        if ($this->has('is_admin')) {
            $this->merge(['is_admin' => $this->boolean('is_admin')]);
        }
    }
}