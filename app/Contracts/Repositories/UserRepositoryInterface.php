<?php
namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /**
     * جستجوی کاربران با فیلترهای پیشرفته
     * @param array $filters
     * @param string $sortBy
     * @param string $sortDir
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function search(array $filters, string $sortBy, string $sortDir, int $perPage = 50): LengthAwarePaginator;

    /**
     * یافتن کاربر با ID
     * @param int $id
     * @param array $relations
     * @return User|null
     */
    public function findById(int $id, array $relations = []): ?User;

    /**
     * یافتن کاربر با شماره موبایل
     * @param string $mobile
     * @return User|null
     */
    public function findByMobile(string $mobile): ?User;

    /**
     * ایجاد کاربر جدید
     * @param array $data
     * @return User
     */
    public function create(array $data): User;

    /**
     * به‌روزرسانی کاربر
     * @param User $user
     * @param array $data
     * @return User
     */
    public function update(User $user, array $data): User;

    /**
     * حذف نرم کاربر
     * @param User $user
     * @return bool
     */
    public function softDelete(User $user): bool;

    /**
     * بازیابی کاربر حذف شده
     * @param int $id
     * @return User|null
     */
    public function restore(int $id): ?User;

    /**
     * Bulk Soft Delete
     * @param array $ids
     * @return int
     */
    public function bulkSoftDelete(array $ids): int;

    /**
     * Bulk Restore
     * @param array $ids
     * @return int
     */
    public function bulkRestore(array $ids): int;

    /**
     * Bulk Update (فعال/غیرفعال)
     * @param array $ids
     * @param array $data
     * @return int
     */
    public function bulkUpdate(array $ids, array $data): int;

    /**
     * دریافت تمام کاربران (برای Export)
     * @param array $filters
     * @return Collection
     */
    public function getAllForExport(array $filters): Collection;

    /**
     * دریافت آمار
     * @return array
     */
    public function getStats(): array;
}
