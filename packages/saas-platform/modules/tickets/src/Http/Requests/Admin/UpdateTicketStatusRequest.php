<?php

namespace SaaSPlatform\Modules\Tickets\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:open,in_progress,closed'],
        ];
    }
}
