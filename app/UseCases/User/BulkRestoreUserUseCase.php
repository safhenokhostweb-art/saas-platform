<?php

namespace App\UseCases\User;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class BulkRestoreUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * بازیابی Bulk کاربران حذف‌شده
     */
    public function execute(array $userIds): int
    {
        $count = DB::transaction(function () use ($userIds) {
            $result = $this->userRepository->bulkRestore($userIds);

            foreach ($userIds as $id) {
                ActivityLogger::log('user.restored', "بازیابی کاربر #{$id}");
            }

            return $result;
        });

        return $count;
    }
}
