<?php

namespace SaaSPlatform\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:text,textarea,email,number,date,radio,checkbox,select',
            'is_required' => 'nullable|boolean',
            'placeholder' => 'nullable|string|max:255',
            'help_text' => 'nullable|string|max:500',
            'options_raw' => 'nullable|string|max:8000',
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'عنوان فیلد الزامی است.',
            'label.max' => 'عنوان فیلد نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'name.required' => 'نام فیلد الزامی است.',
            'name.max' => 'نام فیلد نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'type.required' => 'نوع فیلد الزامی است.',
            'type.in' => 'نوع فیلد نامعتبر است.',
            'placeholder.max' => 'متن راهنما نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'help_text.max' => 'متن کمکی نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.',
            'options_raw.max' => 'گزینه‌ها نمی‌توانند بیشتر از ۸۰۰۰ کاراکتر باشند.',
        ];
    }
}
