<?php

namespace SaaSPlatform\Modules\Tickets\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class StorePortalReplyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'message'       => ['required', 'string'],
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
