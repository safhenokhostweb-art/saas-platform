<?php

namespace SaaSPlatform\Modules\MotionGraphics\Services;

use Illuminate\Validation\ValidationException;

final class EditableSchemaValidator
{
    public function validateComplete(array $current, array $proposed, array $schema): array
    {
        $this->validateObject($current, $proposed, $schema, '$');
        return $proposed;
    }

    private function validateObject(array $current, array $proposed, array $schema, string $path): void
    {
        $properties = $schema['properties'] ?? [];
        foreach ($schema['required'] ?? [] as $key) {
            if (! array_key_exists($key, $proposed)) {
                $this->fail($path.'.'.$key, 'Required field is missing.');
            }
        }
        foreach ($current as $key => $value) {
            if (! array_key_exists($key, $proposed)) {
                $this->fail($path.'.'.$key, 'Complete source definitions cannot remove fields.');
            }
            if (! array_key_exists($key, $properties) && $proposed[$key] !== $value) {
                $this->fail($path.'.'.$key, 'Field is not editable.');
            }
        }
        foreach ($proposed as $key => $value) {
            if (! array_key_exists($key, $current) && ! array_key_exists($key, $properties)) {
                $this->fail($path.'.'.$key, 'Undeclared fields are forbidden.');
            }
            if (! isset($properties[$key])) {
                continue;
            }
            $rule = $properties[$key];
            if (($rule['editable'] ?? true) === false && array_key_exists($key, $current) && $value !== $current[$key]) {
                $this->fail($path.'.'.$key, 'Field is read-only.');
            }
            $this->validateValue($value, $rule, $path.'.'.$key, $current[$key] ?? null);
        }
    }

    private function validateValue(mixed $value, array $rule, string $path, mixed $current): void
    {
        $validType = match ($rule['type'] ?? null) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ! array_is_list($value),
            null => true,
            default => false,
        };
        if (! $validType) {
            $this->fail($path, 'Value has an invalid type.');
        }
        if (isset($rule['enum']) && ! in_array($value, $rule['enum'], true)) {
            $this->fail($path, 'Value is outside the allowed enum.');
        }
        if (is_numeric($value) && isset($rule['minimum']) && $value < $rule['minimum']) {
            $this->fail($path, 'Value is below the minimum.');
        }
        if (is_numeric($value) && isset($rule['maximum']) && $value > $rule['maximum']) {
            $this->fail($path, 'Value exceeds the maximum.');
        }
        if (($rule['type'] ?? null) === 'object') {
            $this->validateObject(is_array($current) ? $current : [], $value, $rule, $path);
        }
        if (($rule['type'] ?? null) === 'array' && isset($rule['items'])) {
            foreach ($value as $index => $item) {
                $this->validateValue($item, $rule['items'], $path.'.'.$index, null);
            }
        }
    }

    private function fail(string $path, string $message): never
    {
        throw ValidationException::withMessages(['source_definition' => $path.': '.$message]);
    }
}
