<?php

/**
 * @file create-user-use-case.php
 * قانون ۱: معماری لایه‌ها - UseCase لایه مجزای منطق تجاری
 * قانون ۲: naming conventions - PascalCase برای کلاس، camelCase برای متدها
 */

namespace App\UseCases\User;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * ایجاد کاربر جدید با پروفایل، نقش‌ها، گروه‌ها و برچسب‌ها
     *
     * @param array $data
     * @return User
     * @throws ValidationException
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // رعایت قانون ۴: اعتبارسنجی ورودی قبل از پردازش
            $validated = $this->validate($data);

            // ایجاد کاربر - رعایت قانون ۳: soft delete را repository مدیریت می‌کند
            $user = $this->userRepository->create($validated);

            // ایجاد پروفایل
            if (!empty($validated['first_name']) || !empty($validated['last_name'])) {
                $user->profile()->create([
                    'first_name' => $validated['first_name'] ?? '',
                    'last_name'  => $validated['last_name'] ?? '',
                    'bio'        => $validated['bio'] ?? '',
                ]);
            }

            // تخصیص نقش‌ها (رعایت قانون ۴: sync از طریق رابطه Eloquent)
            if (!empty($validated['roles'])) {
                $user->roles()->sync($validated['roles']);
            }

            // تخصیص گروه‌ها
            if (!empty($validated['groups'])) {
                $user->groups()->sync($validated['groups']);
            }

            // تخصیص برچسب‌ها
            if (!empty($validated['tags'])) {
                $user->tags()->sync($validated['tags']);
            }

            // رعایت قانون ۵: لاگ سطح INFO
            ActivityLogger::log('user.created', "کاربر جدید: {$user->mobile}", $user);

            return $user->fresh(['profile', 'roles', 'groups', 'tags']);
        });
    }

    /**
     * اعتبارسنجی داده‌ها - رعایت قانون ۴: عدم اعتماد به ورودی کاربر
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    private function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'mobile'     => 'required|string|unique:users,mobile|regex:/^09[0-9]{9}$/',
            'email'      => 'nullable|email|unique:users,email',
            'password'   => 'nullable|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'bio'        => 'nullable|string|max:1000',
            'is_active'  => 'boolean',
            'is_admin'   => 'boolean',
            'roles'      => 'nullable|array',
            'roles.*'    => 'exists:roles,id',
            'groups'     => 'nullable|array',
            'groups.*'   => 'exists:user_groups,id',
            'tags'       => 'nullable|array',
            'tags.*'     => 'exists:tags,id',
        ], [
            'mobile.required'  => 'شماره موبایل الزامی است.',
            'mobile.unique'    => 'این شماره موبایل قبلاً ثبت شده است.',
            'mobile.regex'     => 'شماره موبایل معتبر نیست (باید با 09 شروع شود).',
            'email.email'      => 'ایمیل معتبر نیست.',
            'email.unique'     => 'این ایمیل قبلاً ثبت شده است.',
            'password.min'     => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ]);

        if ($validator->fails()) {
            // رعایت قانون ۵: لاگ WARN برای تلاش ناموفق
            \Log::warning('User creation validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}