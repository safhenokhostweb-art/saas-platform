<?php

namespace App\UseCases\Customer;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateCustomerUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {}

    /**
     * ایجاد مشتری جدید
     *
     * @param array $data
     * @return Customer
     * @throws ValidationException
     */
    public function execute(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $validated = $this->validate($data);

            $customer = $this->customerRepository->create($validated);

            ActivityLogger::log('customer.created', "مشتری جدید: {$customer->name}", $customer);

            return $customer->fresh(['user.profile']);
        });
    }

    private function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'mobile'       => 'required|string|unique:customers,mobile|regex:/^09[0-9]{9}$/',
            'company_name' => 'nullable|string|max:255',
            'notes'        => 'nullable|string|max:5000',
            'user_id'      => 'nullable|exists:users,id',
            'reseller_id'  => 'nullable|exists:resellers,id',
        ], [
            'name.required'    => 'نام مشتری الزامی است.',
            'mobile.required'  => 'شماره موبایل الزامی است.',
            'mobile.unique'    => 'این شماره موبایل قبلاً ثبت شده است.',
            'mobile.regex'     => 'شماره موبایل معتبر نیست.',
            'email.email'      => 'ایمیل معتبر نیست.',
        ]);

        if ($validator->fails()) {
            \Log::warning('Customer creation validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}