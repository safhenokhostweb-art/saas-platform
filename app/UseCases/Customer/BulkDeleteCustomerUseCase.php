<?php

namespace App\UseCases\Customer;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class BulkDeleteCustomerUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {}

    /**
     * Bulk Soft Delete مشتریان
     * رعایت قانون ۳: Soft Delete اجباری
     */
    public function execute(array $customerIds): int
    {
        $count = DB::transaction(function () use ($customerIds) {
            $result = $this->customerRepository->bulkSoftDelete($customerIds);

            foreach ($customerIds as $id) {
                ActivityLogger::log('customer.deleted', "حذف نرم گروهی مشتری #{$id}");
            }

            return $result;
        });

        return $count;
    }
}