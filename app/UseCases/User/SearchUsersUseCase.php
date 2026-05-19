<?php

namespace App\UseCases\User;

use App\Contracts\Repositories\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchUsersUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * جستجوی کاربران با فیلترهای پیشرفته و کش
     * رعایت قانون ۱: فقط جستجو - منطق تجاری خالص
     *
     * @param array $filters
     * @param string $sortBy
     * @param string $sortDir
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function execute(array $filters, string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 50): LengthAwarePaginator
    {
        return $this->userRepository->search($filters, $sortBy, $sortDir, $perPage);
    }
}