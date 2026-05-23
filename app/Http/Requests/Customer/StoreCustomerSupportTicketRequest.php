<?php
namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $customer = $user?->customer;
        $siteIds = $customer ? $customer->sites()->pluck('id')->implode(',') : '0';

        return [
            'site_id' => 'required|in:' . $siteIds,
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'priority' => 'nullable|in:low,normal,high',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string|max:500',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.required' => 'انتخاب سایت الزامی است.',
            'site_id.in' => 'سایت انتخاب شده معتبر نیست.',
            'subject.required' => 'عنوان تیکت الزامی است.',
            'subject.max' => 'عنوان تیکت حداکثر ۲۵۵ کاراکتر می‌باشد.',
            'message.required' => 'متن تیکت الزامی است.',
            'message.max' => 'متن تیکت حداکثر ۵۰۰۰ کاراکتر می‌باشد.',
            'priority.in' => 'اولویت باید یکی از مقادیر low, normal, high باشد.',
            'category.max' => 'دسته‌بندی حداکثر ۵۰ کاراکتر می‌باشد.',
            'tags.max' => 'برچسب‌ها حداکثر ۵۰۰ کاراکتر می‌باشند.',
            'attachments.max' => 'حداکثر ۵ فایل قابل ارسال است.',
            'attachments.*.mimes' => 'فرمت فایل باید jpg, jpeg, png, pdf, doc, docx, zip باشد.',
            'attachments.*.max' => 'حداکثر حجم هر فایل ۱۰ مگابایت است.',
        ];
    }
}
