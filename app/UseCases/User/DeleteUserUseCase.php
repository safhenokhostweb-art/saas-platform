<?php
namespace App\UseCases\User;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class DeleteUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * حذف نرم یک کاربر
     * رعایت قانون ۳: Soft Delete اجباری
     *
     * @param User $user
     * @return void
     */
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $userData = ['id' => $user->id, 'mobile' => $user->mobile];
            $this->userRepository->softDelete($user);
            ActivityLogger::log('user.deleted', "حذف نرم کاربر #{$userData['id']} ({$userData['mobile']})", $user);
        });
    }
}