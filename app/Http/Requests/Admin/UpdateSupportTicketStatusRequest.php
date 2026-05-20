<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupportTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:open,in_progress,closed',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'وضعیت الزامی است.',
            'status.in' => 'وضعیت باید یکی از مقادیر open, in_progress, closed باشد.',
        ];
    }
}
