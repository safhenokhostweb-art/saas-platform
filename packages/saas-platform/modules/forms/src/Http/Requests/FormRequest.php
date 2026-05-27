<?php

namespace SaaSPlatform\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest as BaseFormRequest;

class FormRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|string|max:500',
            'meta_robots' => 'nullable|string|max:80',
            'intro_text' => 'nullable|string|max:65000',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان فرم الزامی است.',
            'title.max' => 'عنوان فرم نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'slug.max' => 'نامک نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'meta_title.max' => 'عنوان متا نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'meta_description.max' => 'توضیحات متا نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.',
            'canonical_url.max' => 'آدرس کنونیکال نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.',
            'meta_robots.max' => 'ربات متا نمی‌تواند بیشتر از ۸۰ کاراکتر باشد.',
            'intro_text.max' => 'متن مقدمه نمی‌تواند بیشتر از ۶۵۰۰۰ کاراکتر باشد.',
        ];
    }
}
