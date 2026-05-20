<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => 'required|exists:sites,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'customer_email' => 'required|email',
            'customer_name' => 'nullable|string|max:255',
            'priority' => 'nullable|in:low,normal,high',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string|max:500',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.required' => 'انتخاب سایت الزامی است.',
            'site_id.exists' => 'سایت انتخاب شده معتبر نیست.',
            'subject.required' => 'عنوان تیکت الزامی است.',
            'subject.max' => 'عنوان تیکت حداکثر ۲۵۵ کاراکتر می‌باشد.',
            'message.required' => 'متن تیکت الزامی است.',
            'message.max' => 'متن تیکت حداکثر ۵۰۰۰ کاراکتر می‌باشد.',
            'customer_email.required' => 'ایمیل مشتری الزامی است.',
            'customer_email.email' => 'ایمیل وارد شده معتبر نیست.',
            'priority.in' => 'اولویت باید یکی از مقادیر low, normal, high باشد.',
            'category.max' => 'دسته‌بندی حداکثر ۵۰ کاراکتر می‌باشد.',
            'tags.max' => 'برچسب‌ها حداکثر ۵۰۰ کاراکتر می‌باشند.',
            'attachments.*.mimes' => 'فرمت فایل باید jpg, jpeg, png, pdf, doc, docx, zip باشد.',
            'attachments.*.max' => 'حداکثر حجم فایل ۱۰ مگابایت می‌باشد.',
        ];
    }
}
