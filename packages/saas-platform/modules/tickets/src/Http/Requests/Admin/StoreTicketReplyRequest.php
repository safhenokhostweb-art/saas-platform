<?php

namespace SaaSPlatform\Modules\Tickets\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketReplyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'message'       => ['nullable', 'string'],  // فایل‌های بدون متن هم مجاز است
            'is_internal'   => ['nullable', 'boolean'],
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (empty($this->input('message')) && empty($this->file('attachments'))) {
                $v->errors()->add('message', 'پیام یا فایل پیوست الزامی است.');
            }
        });
    }
}
