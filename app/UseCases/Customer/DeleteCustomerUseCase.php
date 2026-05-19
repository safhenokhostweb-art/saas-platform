<?php

namespace App\UseCases\Customer;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class DeleteCustomerUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {}

    /**
     * حذف نرم یک مشتری
     * رعایت قانون ۳: Soft Delete اجباری
     */
    public function execute(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $name = $customer->company_name ?: $customer->name;
            $this->customerRepository->softDelete($customer);
            ActivityLogger::log('customer.deleted', "حذف نرم مشتری: {$name}", $customer);
        });
    }
}