<?php
namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    private const CACHE_TTL = 900;
    private const PER_PAGE = 50;

    private const RELATIONS_DEFAULT = ['profile', 'customer', 'roles', 'groups', 'tags'];

    public function search(array $filters, string $sortBy, string $sortDir, int $perPage = 25): LengthAwarePaginator
    {
        $cacheKey = 'users_search_' . md5(serialize($filters) . $sortBy . $sortDir . request('page', 1) . $perPage);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $sortBy, $sortDir, $perPage) {
            $trashed = $filters['trashed'] ?? '0';
            if ($trashed === '1') {
                $query = User::onlyTrashed()->with(self::RELATIONS_DEFAULT)->select('users.*')->orderBy($sortBy, $sortDir);
            } elseif ($trashed === 'all') {
                $query = User::withTrashed()->with(self::RELATIONS_DEFAULT)->select('users.*')->orderBy($sortBy, $sortDir);
            } else {
                $query = User::with(self::RELATIONS_DEFAULT)->select('users.*')->orderBy($sortBy, $sortDir);
            }

            // فقط کاربران پلتفرم نشان داده شود — کاربران نهایی سایت‌های مشتری مخفی باشند
            $query->where(fn($q) =>
                $q->whereNull('users.site_id')
                  ->orWhere('users.is_admin', true)
                  ->orWhere('users.is_site_admin', true)
            );

            // کاربرانی که customer هم هستند فقط در لیست مشتریان نمایش داده می‌شوند
            $customerUserIds = DB::table('customers')
                ->whereNotNull('user_id')
                ->whereNull('deleted_at')
                ->pluck('user_id')
                ->toArray();
            $customerMobiles = DB::table('customers')
                ->whereNull('user_id')
                ->whereNull('deleted_at')
                ->pluck('mobile')
                ->toArray();
            if (!empty($customerUserIds)) {
                $query->whereNotIn('users.id', $customerUserIds);
            }
            if (!empty($customerMobiles)) {
                $query->whereNotIn('users.mobile', $customerMobiles);
            }

            if (!empty($filters['mobile'])) {
                $query->where('mobile', 'like', '%' . $filters['mobile'] . '%');
            }

            if (!empty($filters['name'])) {
                $query->whereHas('profile', function ($q) use ($filters) {
                    $q->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $filters['name'] . '%');
                });
            }

            if (!empty($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $query->where('is_active', true);
                } elseif ($filters['status'] === 'inactive') {
                    $query->where('is_active', false);
                } elseif ($filters['status'] === 'locked') {
                    $query->whereNotNull('locked_until')->where('locked_until', '>', now());
                }
            }

            if (!empty($filters['role'])) {
                $query->whereHas('roles', fn ($q) => $q->where('slug', $filters['role']));
            }

            if (!empty($filters['group_id'])) {
                $query->whereHas('groups', fn ($q) => $q->where('user_groups.id', $filters['group_id']));
            }

            if (!empty($filters['tag_id'])) {
                $query->whereHas('tags', fn ($q) => $q->where('tags.id', $filters['tag_id']));
            }

            if (!empty($filters['two_factor'])) {
                $query->where('two_factor_enabled', $filters['two_factor'] === 'enabled');
            }

            if (!empty($filters['created_from'])) {
                $query->whereDate('created_at', '>=', $filters['created_from']);
            }
            if (!empty($filters['created_to'])) {
                $query->whereDate('created_at', '<=', $filters['created_to']);
            }

            return $query->paginate($perPage);
        });
    }

    public function findById(int $id, array $relations = []): ?User
    {
        $relations = $relations ?: self::RELATIONS_DEFAULT;
        return Cache::remember("user_{$id}_" . md5(implode(',', $relations)), self::CACHE_TTL, function () use ($id, $relations) {
            return User::with($relations)->find($id);
        });
    }

    public function findByMobile(string $mobile): ?User
    {
        return User::where('mobile', $mobile)->first();
    }

    public function create(array $data): User
    {
        $user = User::create([
            'mobile'    => $data['mobile'],
            'password'  => isset($data['password']) ? bcrypt($data['password']) : bcrypt(random_int(100000, 999999)),
            'is_active' => $data['is_active'] ?? true,
            'is_admin'  => $data['is_admin'] ?? false,
        ]);
        $this->clearCache();
        return $user;
    }

    public function update(User $user, array $data): User
    {
        $updatable = array_intersect_key($data, array_flip([
            'mobile', 'password', 'is_active', 'is_admin',
            'is_locked', 'failed_login_attempts', 'locked_until',
            'two_factor_enabled', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
            'last_login_at', 'last_login_ip',
        ]));

        if (!empty($updatable['password'])) {
            $updatable['password'] = bcrypt($updatable['password']);
        } else {
            unset($updatable['password']);
        }

        $user->update($updatable);
        $this->clearCache();

        return $user->fresh(self::RELATIONS_DEFAULT);
    }

    public function softDelete(User $user): bool
    {
        $result = $user->delete();
        $this->clearCache();
        return $result;
    }

    public function restore(int $id): ?User
    {
        $user = User::withTrashed()->find($id);
        if ($user) {
            $user->restore();
            $this->clearCache();
        }
        return $user;
    }

    public function bulkSoftDelete(array $ids): int
    {
        $count = 0;
        foreach (array_chunk($ids, 50) as $chunk) {
            $users = User::whereIn('id', $chunk)->get();
            foreach ($users as $user) {
                $user->delete();
                $count++;
            }
        }
        $this->clearCache();
        return $count;
    }

    public function bulkRestore(array $ids): int
    {
        $count = User::withTrashed()->whereIn('id', $ids)->restore();
        $this->clearCache();
        return $count;
    }

    public function bulkUpdate(array $ids, array $data): int
    {
        $count = User::whereIn('id', $ids)->update($data);
        $this->clearCache();
        return $count;
    }

    public function getAllForExport(array $filters): Collection
    {
        $query = User::with(self::RELATIONS_DEFAULT)->select('users.*')->orderBy('created_at', 'desc')
            ->where(fn($q) =>
                $q->whereNull('site_id')
                  ->orWhere('is_admin', true)
                  ->orWhere('is_site_admin', true)
            );

        if (!empty($filters['mobile'])) {
            $query->where('mobile', 'like', '%' . $filters['mobile'] . '%');
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return $query->get();
    }

    public function getStats(): array
    {
        return Cache::remember('user_stats', self::CACHE_TTL, function () {
            $base = User::where(fn($q) =>
                $q->whereNull('site_id')
                  ->orWhere('is_admin', true)
                  ->orWhere('is_site_admin', true)
            );
            return [
                'total'              => (clone $base)->count(),
                'active'             => (clone $base)->where('is_active', true)->count(),
                'inactive'           => (clone $base)->where('is_active', false)->count(),
                'locked'             => (clone $base)->whereNotNull('locked_until')->where('locked_until', '>', now())->count(),
                'admins'             => (clone $base)->where('is_admin', true)->count(),
                'two_factor_enabled' => (clone $base)->where('two_factor_enabled', true)->count(),
                'new_today'          => (clone $base)->whereDate('created_at', today())->count(),
                'new_this_month'     => (clone $base)->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->count(),
            ];
        });
    }

    public function getRegistrationTrend(int $days = 30): array
    {
        return Cache::remember("user_registration_trend_{$days}", self::CACHE_TTL, function () use ($days) {
            $results = User::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            $trend = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $trend[] = [
                    'date'  => $date,
                    'label' => now()->subDays($i)->format('m/d'),
                    'count' => (int) ($results->get($date)?->count ?? 0),
                ];
            }
            return $trend;
        });
    }

    public function clearCache(): void
    {
        Cache::flush();
    }
}