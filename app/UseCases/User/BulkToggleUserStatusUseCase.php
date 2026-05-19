<?php

namespace App\UseCases\User;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class BulkToggleUserStatusUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * تغییر وضعیت Bulk (فعال/غیرفعال)
     * رعایت قانون ۱: Single Responsibility - فقط تغییر وضعیت
     * رعایت قانون ۳: Soft Delete رعایت نمی‌شود چون تغییر وضعیت حذف نیست
     *
     * @param array $userIds
     * @param bool $active
     * @return int تعداد کاربران تغییر وضعیت داده شده
     */
    public function execute(array $userIds, bool $active): int
    {
        $filteredIds = array_values(
            array_filter($userIds, fn ($id) => (int) $id !== (int) auth()->id())
        );

        if (empty($filteredIds)) {
            return 0;
        }

        $affected = DB::transaction(function () use ($filteredIds, $active) {
            $count = $this->userRepository->bulkUpdate($filteredIds, ['is_active' => $active]);

            foreach ($filteredIds as $userId) {
                ActivityLogger::log(
                    $active ? 'user.bulk_activated' : 'user.bulk_deactivated',
                    ($active ? 'فعال‌سازی' : 'غیرفعال‌سازی') . " گروهی کاربر #{$userId}",
                    'App\Models\User',
                    $userId
                );
            }

            return $count;
        });

        return $affected;
    }
}