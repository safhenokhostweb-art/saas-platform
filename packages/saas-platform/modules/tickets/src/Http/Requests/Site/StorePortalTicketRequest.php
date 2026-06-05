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
            // customer-site form uses 'message', canonical model uses 'description' — accept both
            'description'   => ['nullable', 'string'],
            'message'       => ['nullable', 'string'],
            'priority'      => ['nullable', 'string', 'in:low,normal,high'],
            'category'      => ['nullable', 'string', 'max:100'],
            'type'          => ['nullable', 'string', 'in:support,feature_request'],
            'for_user_id'   => ['nullable', 'integer'],
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    /**
     * Normalize 'message' → 'description' and validate that at least one body field is present.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            $desc = $this->input('description') ?? $this->input('message') ?? '';
            if (empty(trim($desc))) {
                $v->errors()->add('description', 'توضیحات الزامی است.');
            }
        });
    }

    protected function passedValidation(): void
    {
        if (empty($this->description) && !empty($this->message)) {
            $this->merge(['description' => $this->message]);
        }
    }
}
