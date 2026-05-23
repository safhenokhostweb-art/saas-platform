<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketReplyApiRequest extends FormRequest
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
            'message' => 'required|string|max:5000',
            'customer_name' => 'nullable|string|max:255',
            'attachments' => 'nullable|array|max:5',
            'attachments.*.name' => 'required_with:attachments|string|max:255',
            'attachments.*.path' => 'required_with:attachments|string|max:500',
            'attachments.*.url' => 'nullable|url|max:1000',
            'attachments.*.size' => 'nullable|integer|min:0|max:10485760',
            'attachments.*.mime' => 'nullable|string|max:120',
        ];
    }
}
