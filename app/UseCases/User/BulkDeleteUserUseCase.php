<?php

namespace App\UseCases\User;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class BulkDeleteUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Bulk Soft Delete کاربران
     * رعایت قانون ۳: Soft Delete اجباری
     *
     * @param array $userIds
     * @return int
     */
    public function execute(array $userIds): int
    {
        $filteredIds = array_values(
            array_filter($userIds, fn ($id) => (int) $id !== (int) auth()->id())
        );

        if (empty($filteredIds)) {
            return 0;
        }

        $count = DB::transaction(function () use ($filteredIds) {
            $result = $this->userRepository->bulkSoftDelete($filteredIds);

            foreach ($filteredIds as $userId) {
                ActivityLogger::log('user.deleted', "حذف نرم گروهی کاربر #{$userId}");
            }

            return $result;
        });

        return $count;
    }
}