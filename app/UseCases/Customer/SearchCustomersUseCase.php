<?php
namespace App\UseCases\Customer;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchCustomersUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {}

    /**
     * جستجوی مشتریان با فیلترهای پیشرفته
     */
    public function execute(array $filters, string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 50): LengthAwarePaginator
    {
        return $this->customerRepository->search($filters, $sortBy, $sortDir, $perPage);
    }
}