<?php

namespace SaaSPlatform\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sites' => 'nullable|array',
            'sites.*' => 'exists:sites,id',
        ];
    }

    public function messages(): array
    {
        return [
            'sites.*.exists' => 'سایت انتخاب شده معتبر نیست.',
        ];
    }
}
