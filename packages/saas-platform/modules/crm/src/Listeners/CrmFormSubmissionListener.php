<?php

namespace SaaSPlatform\Modules\Crm\Listeners;

use SaaSPlatform\Modules\Crm\Models\CrmLead;

/**
 * Phase 20 — Part 7: reacts to Forms' FormSubmissionCreated event
 * (registered by CrmServiceProvider::boot(), guarded by class_exists —
 * see that method's docblock for why this listener deliberately never
 * imports SaaSPlatform\Modules\Forms\* classes, only duck-types $event).
 *
 * MVP scope, documented in the Phase 20 final report: any site-scoped
 * form submission containing an email or phone becomes a Lead. There is
 * no per-form "is this a contact form" configuration yet — a genuine
 * field-mapping UI (matching a specific form to specific CRM fields) is
 * flagged as a real next-step, not built here.
 */
class CrmFormSubmissionListener
{
    public function handle(object $event): void
    {
        $submission = $event->submission ?? null;
        if (!$submission || empty($submission->site_id)) {
            return;
        }

        $form = $submission->form()->with('fields')->first();
        if (!$form) {
            return;
        }

        [$name, $email, $phone] = $this->extractContactFields($form->fields, $submission->data ?? []);

        if (empty($email) && empty($phone)) {
            return;
        }

        CrmLead::create([
            'site_id' => $submission->site_id,
            'first_name' => $name ?: 'سرنخ فرم',
            'email' => $email,
            'phone' => $phone,
            'source' => 'form:' . ($form->slug ?? $form->id),
        ]);
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [name, email, phone] */
    private function extractContactFields(iterable $fields, array $data): array
    {
        $name = null;
        $email = null;
        $phone = null;

        foreach ($fields as $field) {
            $value = $data['field_' . $field->id] ?? null;
            if (empty($value) || !is_string($value)) {
                continue;
            }

            if ($field->type === 'email' && $email === null) {
                $email = $value;
            } elseif ($field->type === 'tel' && $phone === null) {
                $phone = $value;
            } elseif ($field->type === 'text' && $name === null) {
                $name = $value;
            }
        }

        return [$name, $email, $phone];
    }
}
