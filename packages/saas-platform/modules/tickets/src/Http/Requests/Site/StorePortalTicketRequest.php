<?php

namespace SaaSPlatform\Modules\Tickets\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class StorePortalTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'subject'       => ['required', 'string', 'max:255'],
            'description'   => ['required', 'string'],
            'priority'      => ['nullable', 'string', 'in:low,normal,high'],
            'category'      => ['nullable', 'string', 'max:100'],
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
