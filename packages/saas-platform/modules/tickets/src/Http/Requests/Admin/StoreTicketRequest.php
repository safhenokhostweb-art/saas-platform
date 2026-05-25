<?php

namespace SaaSPlatform\Modules\Tickets\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'subject'        => ['required', 'string', 'max:255'],
            'description'    => ['required', 'string'],
            'priority'       => ['nullable', 'string', 'in:low,normal,high'],
            'site_id'        => ['nullable', 'integer', 'exists:sites,id'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_name'  => ['nullable', 'string', 'max:255'],
            'category'       => ['nullable', 'string', 'max:100'],
            'attachments'    => ['nullable', 'array'],
            'attachments.*'  => ['file', 'max:10240'],
        ];
    }
}
