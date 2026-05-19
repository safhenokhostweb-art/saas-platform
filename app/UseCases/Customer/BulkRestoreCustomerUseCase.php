<?php

namespace App\UseCases\Customer;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class BulkRestoreCustomerUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {}

    public function execute(array $customerIds): int
    {
        $count = DB::transaction(function () use ($customerIds) {
            $result = $this->customerRepository->bulkRestore($customerIds);
            foreach ($customerIds as $id) {
                ActivityLogger::log('customer.restored', "بازیابی مشتری #{$id}");
            }
            return $result;
        });
        return $count;
    }
}