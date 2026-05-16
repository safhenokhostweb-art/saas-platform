<?php

namespace App\Services;

use App\Models\User;
use App\Models\Profile;
use App\Services\ActivityLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserService
{
    private const CACHE_TTL = 900; // 15 دقیقه
    private const PER_PAGE = 50;

    /**
     * جستجوی کاربران با فیلترهای پیشرفته و کش هوشمند
     */
    public function search(array $filters = [], string $sortBy = 'created_at', string $sortDir = 'desc'): LengthAwarePaginator
    {
        $cacheKey = 'users_search_' . md5(serialize($filters) . $sortBy . $sortDir . request('page', 1));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $sortBy, $sortDir) {
            $query = User::with(['profile', 'customer', 'roles'])
                ->select('users.*')
                ->orderBy($sortBy, $sortDir);

            // فیلتر موبایل
            if (!empty($filters['mobile'])) {
                $query->where('mobile', 'like', '%' . $filters['mobile'] . '%');
            }

            // فیلتر ایمیل
            if (!empty($filters['email'])) {
                $query->where('email', 'like', '%' . $filters['email'] . '%');
            }

            // فیلتر نام (از پروفایل)
            if (!empty($filters['name'])) {
                $query->whereHas('profile', function ($q) use ($filters) {
                    $q->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $filters['name'] . '%');
                });
            }

            // فیلتر وضعیت
            if (!empty($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $query->where('is_active', true);
                } elseif ($filters['status'] === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            // فیلتر نقش
            if (!empty($filters['role'])) {
                $query->whereHas('roles', fn ($q) => $q->where('slug', $filters['role']));
            }

            // فیلتر تاریخ ثبت‌نام
            if (!empty($filters['created_from'])) {
                $query->whereDate('created_at', '>=', $filters['created_from']);
            }
            if (!empty($filters['created_to'])) {
                $query->whereDate('created_at', '<=', $filters['created_to']);
            }

            // Eager loading برای جلوگیری از N+1
            return $query->paginate(self::PER_PAGE);
        });
    }

    /**
     * تغییر وضعیت Bulk (فعال/غیرفعال)
     */
    public function bulkToggleActive(array $userIds, bool $active): int
    {
        // جلوگیری از غیرفعال کردن خودکار خود
        $filteredIds = array_filter($userIds, fn ($id) => $id !== auth()->id());

        if (empty($filteredIds)) {
            return 0;
        }

        $count = 0;
        foreach (array_chunk($filteredIds, 50) as $chunk) {
            $affected = User::whereIn('id', $chunk)->update(['is_active' => $active]);
            $count += $affected;

            // ثبت لاگ برای هر کاربر
            foreach ($chunk as $userId) {
                ActivityLogger::log(
                    $active ? 'user.bulk_activated' : 'user.bulk_deactivated',
                    ($active ? 'فعال‌سازی' : 'غیرفعال‌سازی') . " گروهی کاربر #{$userId}",
                    User::find($userId)
                );
            }

            // پاک کردن کش
            $this->clearCache();
        }

        return $count;
    }

    /**
     * Bulk Soft Delete
     */
    public function bulkSoftDelete(array $userIds): int
    {
        $filteredIds = array_filter($userIds, fn ($id) => $id !== auth()->id());

        if (empty($filteredIds)) {
            return 0;
        }

        $count = 0;
        foreach (array_chunk($filteredIds, 50) as $chunk) {
            $users = User::whereIn('id', $chunk)->get();
            foreach ($users as $user) {
                $user->delete(); // Soft delete
                ActivityLogger::log('user.deleted', "حذف نرم کاربر #{$user->id}", $user);
            }
            $count += count($chunk);
            $this->clearCache();
        }

        return $count;
    }

    /**
     * بازیابی کاربران حذف‌شده
     */
    public function bulkRestore(array $userIds): int
    {
        $count = User::withTrashed()->whereIn('id', $userIds)->restore();

        foreach ($userIds as $id) {
            ActivityLogger::log('user.restored', "بازیابی کاربر #{$id}");
        }

        $this->clearCache();
        return $count;
    }

    /**
     * ایجاد کاربر جدید با پروفایل
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'mobile'    => $data['mobile'],
                'email'     => $data['email'] ?? null,
                'password'  => Hash::make($data['password'] ?? random_int(100000, 999999)),
                'is_active' => $data['is_active'] ?? true,
                'is_admin'  => $data['is_admin'] ?? false,
            ]);

            if (!empty($data['first_name']) || !empty($data['last_name'])) {
                $user->profile()->create([
                    'first_name' => $data['first_name'] ?? '',
                    'last_name'  => $data['last_name'] ?? '',
                    'bio'        => $data['bio'] ?? '',
                ]);
            }

            // تخصیص نقش‌ها
            if (!empty($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            ActivityLogger::log('user.created', "کاربر جدید: {$user->mobile}", $user);
            $this->clearCache();

            return $user;
        });
    }

    /**
     * به‌روزرسانی کاربر
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $updatable = array_intersect_key($data, array_flip(['mobile', 'email', 'is_active', 'is_admin']));

            if (!empty($data['password'])) {
                $updatable['password'] = Hash::make($data['password']);
            }

            $user->update($updatable);

            // به‌روزرسانی پروفایل
            if (!empty($data['first_name']) || !empty($data['last_name']) || !empty($data['bio'])) {
                $profileData = array_intersect_key($data, array_flip(['first_name', 'last_name', 'bio']));
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }

            // همگام‌سازی نقش‌ها
            if (isset($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            ActivityLogger::log('user.updated', "ویرایش کاربر #{$user->id}", $user);
            $this->clearCache();

            return $user->fresh(['profile', 'roles']);
        });
    }

    /**
     * Export کاربران به CSV (از طریق Queue)
     */
    public function exportCsv(array $filters, string $sortBy, string $sortDir): string
    {
        $users = $this->searchQuery($filters, $sortBy, $sortDir)->get();

        $filename = 'users_export_' . now()->format('Ymd_His') . '.csv';
        $path = storage_path("app/exports/{$filename}");
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($path, 'w');
        // BOM برای UTF-8
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($handle, ['#', 'موبایل', 'ایمیل', 'نام', 'وضعیت', 'ادمین', 'تاریخ ثبت‌نام']);

        foreach ($users as $user) {
            fputcsv($handle, [
                $user->id,
                $user->mobile,
                $user->email ?? '',
                $user->profile?->full_name ?? '',
                $user->is_active ? 'فعال' : 'غیرفعال',
                $user->is_admin ? 'بله' : 'خیر',
                $user->created_at->format('Y-m-d H:i'),
            ]);
        }

        fclose($handle);
        return $filename;
    }

    /**
     * Import کاربران از CSV (از طریق Queue)
     */
    public function importCsv(string $filepath): array
    {
        $result = ['imported' => 0, 'failed' => []];

        if (!file_exists($filepath)) {
            throw new \RuntimeException('فایل یافت نشد.');
        }

        $handle = fopen($filepath, 'r');
        $header = fgetcsv($handle);

        DB::transaction(function () use ($handle, &$result) {
            while (($row = fgetcsv($handle)) !== false) {
                try {
                    $data = [
                        'mobile'    => $row[1] ?? '',
                        'email'     => $row[2] ?? '',
                        'first_name'=> $row[3] ?? '',
                        'is_active' => ($row[4] ?? 'فعال') === 'فعال',
                    ];

                    if (empty($data['mobile'])) {
                        continue;
                    }

                    $this->create($data);
                    $result['imported']++;

                } catch (\Exception $e) {
                    $result['failed'][] = $row[1] ?? 'unknown';
                    Log::warning("Import user failed: {$e->getMessage()}", ['row' => $row]);
                }
            }
        });

        fclose($handle);
        return $result;
    }

    /**
     * پاک کردن کش پس از تغییرات
     */
    public function clearCache(): void
    {
        Cache::flush(); // عملی: فقط کش‌های مرتبط با کاربران را بشویید
    }

    /**
     * کوئری پایه جستجو (برای Export)
     */
    private function searchQuery(array $filters, string $sortBy, string $sortDir)
    {
        $query = User::with(['profile', 'customer', 'roles'])
            ->select('users.*')->orderBy($sortBy, $sortDir);

        if (!empty($filters['mobile'])) {
            $query->where('mobile', 'like', '%' . $filters['mobile'] . '%');
        }
        if (!empty($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return $query;
    }
}