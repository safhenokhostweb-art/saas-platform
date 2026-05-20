<?php
namespace App\UseCases\Customer;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateCustomerUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {}

    /**
     * به‌روزرسانی مشتری
     */
    public function execute(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $validated = $this->validate($data, $customer->id);

            $customer = $this->customerRepository->update($customer, $validated);

            ActivityLogger::log('customer.updated', "ویرایش مشتری #{$customer->id}", $customer);

            return $customer;
        });
    }

    private function validate(array $data, int $customerId): array
    {
        $validator = Validator::make($data, [
            'name'         => 'sometimes|required|string|max:255',
            'email'        => 'sometimes|nullable|email|max:255',
            'mobile'       => ['sometimes', 'required', 'string', "unique:customers,mobile,{$customerId}", 'regex:/^09[0-9]{9}$/'],
            'company_name' => 'sometimes|nullable|string|max:255',
            'notes'        => 'sometimes|nullable|string|max:5000',
            'user_id'      => 'sometimes|nullable|exists:users,id',
            'reseller_id'  => 'sometimes|nullable|exists:resellers,id',
        ], [
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
            'mobile.regex'  => 'شماره موبایل معتبر نیست.',
        ]);

        if ($validator->fails()) {
            \Log::warning('Customer update validation failed', [
                'customer_id' => $customerId,
                'errors'      => $validator->errors()->toArray(),
            ]);
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}