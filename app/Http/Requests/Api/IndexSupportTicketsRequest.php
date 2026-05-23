<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IndexSupportTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'license_key' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'status' => 'nullable|in:open,in_progress,closed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
