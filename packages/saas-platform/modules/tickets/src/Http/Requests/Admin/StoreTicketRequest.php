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
            'customer_mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/'],
            'customer_name'   => ['nullable', 'string', 'max:255'],
            'category'       => ['nullable', 'string', 'max:100'],
            'for_user_id'     => ['nullable', 'integer'],
            'for_customer_id' => ['nullable', 'integer'],
            'initial_note'   => ['nullable', 'string', 'max:5000'],
            'attachments'    => ['nullable', 'array'],
            'attachments.*'  => ['file', 'max:10240'],
        ];
    }
}
