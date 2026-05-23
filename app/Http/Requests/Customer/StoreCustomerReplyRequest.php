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
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'متن پاسخ الزامی است.',
            'message.max' => 'متن پاسخ حداکثر ۵۰۰۰ کاراکتر می‌باشد.',
            'attachments.max' => 'حداکثر ۵ فایل قابل ارسال است.',
            'attachments.*.mimes' => 'فرمت فایل باید jpg, jpeg, png, pdf, doc, docx, zip باشد.',
            'attachments.*.max' => 'حداکثر حجم هر فایل ۱۰ مگابایت است.',
        ];
    }
}
