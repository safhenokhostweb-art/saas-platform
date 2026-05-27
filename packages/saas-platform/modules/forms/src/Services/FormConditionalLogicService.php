<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Models\FormField;
use Illuminate\Support\Collection;

class FormConditionalLogicService
{
    /**
     * Evaluate which fields should be visible given current form data.
     * Returns array of field IDs that are currently visible.
     */
    public function resolveVisibleFields(Collection $fields, array $formData): array
    {
        $visible = [];

        foreach ($fields as $field) {
            if ($this->shouldShow($field, $fields, $formData)) {
                $visible[] = $field->id;
            }
        }

        return $visible;
    }

    public function shouldShow(FormField $field, Collection $allFields, array $formData): bool
    {
        if (empty($field->conditions)) {
            return true;
        }

        $conditions = $field->conditions;
        $logicType  = $conditions['logic'] ?? 'AND'; // AND | OR
        $rules      = $conditions['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        $results = array_map(
            fn($rule) => $this->evaluateRule($rule, $allFields, $formData),
            $rules
        );

        return $logicType === 'OR'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    private function evaluateRule(array $rule, Collection $allFields, array $formData): bool
    {
        $fieldId  = $rule['field_id'] ?? null;
        $operator = $rule['operator'] ?? 'equals';
        $value    = $rule['value'] ?? '';

        if (! $fieldId) {
            return true;
        }

        $sourceField = $allFields->firstWhere('id', $fieldId);
        if (! $sourceField) {
            return true;
        }

        $actual = $formData["field_{$sourceField->id}"] ?? $formData[$sourceField->name] ?? null;

        return match ($operator) {
            'equals'          => (string) $actual === (string) $value,
            'not_equals'      => (string) $actual !== (string) $value,
            'contains'        => str_contains((string) $actual, (string) $value),
            'not_contains'    => ! str_contains((string) $actual, (string) $value),
            'starts_with'     => str_starts_with((string) $actual, (string) $value),
            'ends_with'       => str_ends_with((string) $actual, (string) $value),
            'is_empty'        => empty($actual),
            'is_not_empty'    => ! empty($actual),
            'greater_than'    => is_numeric($actual) && is_numeric($value) && $actual > $value,
            'less_than'       => is_numeric($actual) && is_numeric($value) && $actual < $value,
            default           => true,
        };
    }

    /**
     * Serialize conditions into JSON-safe structure for frontend.
     */
    public function serializeForBuilder(array $fields): array
    {
        return array_map(function ($field) {
            return [
                'id'         => $field['id'] ?? null,
                'label'      => $field['label'] ?? '',
                'type'       => $field['type'] ?? 'text',
                'conditions' => $field['conditions'] ?? [],
            ];
        }, $fields);
    }
}
