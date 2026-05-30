<?php
namespace App\UseCases\User;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * به‌روزرسانی کاربر با تمام روابط
     *
     * @param User $user
     * @param array $data
     * @return User
     * @throws ValidationException
     */
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $validated = $this->validate($data, $user->id);

            // به‌روزرسانی کاربر
            $user = $this->userRepository->update($user, $validated);

            // به‌روزرسانی پروفایل
            if (isset($validated['first_name']) || isset($validated['last_name']) || isset($validated['bio'])) {
                $profileData = array_filter([
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name'  => $validated['last_name'] ?? null,
                    'bio'        => $validated['bio'] ?? null,
                ], fn ($v) => $v !== null);

                if (!empty($profileData)) {
                    $user->profile()->updateOrCreate(
                        ['user_id' => $user->id],
                        $profileData
                    );
                }
            }

            // همگام‌سازی نقش‌ها
            if (isset($validated['roles'])) {
                $user->roles()->sync($validated['roles']);
            }

            // همگام‌سازی گروه‌ها
            if (isset($validated['groups'])) {
                $user->groups()->sync($validated['groups']);
            }

            // همگام‌سازی برچسب‌ها
            if (isset($validated['tags'])) {
                $user->tags()->sync($validated['tags']);
            }

            ActivityLogger::log('user.updated', "ویرایش کاربر #{$user->id}", $user);

            return $user->fresh(['profile', 'roles', 'groups', 'tags']);
        });
    }

    /**
     * اعتبارسنجی داده‌های به‌روزرسانی
     */
    private function validate(array $data, int $userId): array
    {
        $rules = [
            'mobile'     => ['sometimes', 'required', 'string', "unique:users,mobile,{$userId}", 'regex:/^09[0-9]{9}$/'],
            'password'   => 'sometimes|nullable|string|min:8',
            'first_name' => 'sometimes|nullable|string|max:255',
            'last_name'  => 'sometimes|nullable|string|max:255',
            'bio'        => 'sometimes|nullable|string|max:1000',
            'is_active'  => 'sometimes|boolean',
            'is_admin'   => 'sometimes|boolean',
            'roles'      => 'sometimes|nullable|array',
            'roles.*'    => 'exists:roles,id',
            'groups'     => 'sometimes|nullable|array',
            'groups.*'   => 'exists:user_groups,id',
            'tags'       => 'sometimes|nullable|array',
            'tags.*'     => 'exists:tags,id',
        ];

        $validator = Validator::make($data, $rules, [
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
            'mobile.regex'  => 'شماره موبایل معتبر نیست.',
            'password.min'  => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ]);

        if ($validator->fails()) {
            \Log::warning('User update validation failed', [
                'user_id' => $userId,
                'errors'  => $validator->errors()->toArray(),
            ]);
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}