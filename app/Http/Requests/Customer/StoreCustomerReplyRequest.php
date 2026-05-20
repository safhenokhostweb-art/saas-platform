<?php
namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'متن پاسخ الزامی است.',
            'message.max' => 'متن پاسخ حداکثر ۵۰۰۰ کاراکتر می‌باشد.',
        ];
    }
}
