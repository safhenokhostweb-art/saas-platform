<?php

namespace SaaSPlatform\ExtensionSdk;

/**
 * Phase 3G-B-2 — the shared, pure-declarative form of one control within an
 * ExtensionElementDefinition's schema. Mirrors the V1 control vocabulary
 * already proven by customer-site's App\Builder\Definition\
 * ThemeBuilderControlDefinition byte-for-byte (same 10 control-type
 * constants, same closed-set enforcement, same SELECT/options rule) but
 * deliberately omits that class's `validationType` field: that field exists
 * solely to reconstruct customer-site's legacy ElementValidator schema array
 * (toLegacySchema()) and is a runtime/adapter concern, never part of the
 * portable declarative contract a commercial manifest carries.
 *
 * Never carries rendering logic, a raw-HTML-trust flag, or a callback.
 */
final class ExtensionControlDefinition
{
    public const TEXT = 'text';
    public const TEXTAREA = 'textarea';
    public const NUMBER = 'number';
    public const TOGGLE = 'toggle';
    public const SELECT = 'select';
    public const COLOR = 'color';
    public const MEDIA = 'media';
    public const URL = 'url';
    public const SPACING = 'spacing';
    public const RESPONSIVE = 'responsive';

    /** V1 vocabulary only — preserved exactly, never broadened in this phase. */
    public const V1_CONTROL_TYPES = [
        self::TEXT, self::TEXTAREA, self::NUMBER, self::TOGGLE, self::SELECT,
        self::COLOR, self::MEDIA, self::URL, self::SPACING, self::RESPONSIVE,
    ];

    private function __construct(
        public readonly string $key,
        public readonly string $controlType,
        public readonly ?string $label,
        public readonly mixed $default,
        public readonly bool $required,
        public readonly ?int $max,
        public readonly ?array $options,
    ) {
    }

    public static function make(
        string $key,
        string $controlType,
        ?string $label = null,
        mixed $default = null,
        bool $required = false,
        ?int $max = null,
        ?array $options = null,
    ): self {
        if (!self::isKnownControlType($controlType)) {
            throw ExtensionSdkException::unknownControlType($key, $controlType);
        }

        if ($controlType === self::SELECT && (!is_array($options) || $options === [])) {
            throw ExtensionSdkException::invalidSelectOptions($key);
        }

        return new self($key, $controlType, $label, $default, $required, $max, $options);
    }

    public static function isKnownControlType(string $controlType): bool
    {
        return in_array($controlType, self::V1_CONTROL_TYPES, true);
    }

    public static function fromArray(string $key, array $data): self
    {
        return self::make(
            key: $key,
            controlType: (string) ($data['control_type'] ?? ''),
            label: isset($data['label']) ? (string) $data['label'] : null,
            default: $data['default'] ?? null,
            required: (bool) ($data['required'] ?? false),
            max: isset($data['max']) ? (int) $data['max'] : null,
            options: $data['options'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'control_type' => $this->controlType,
            'label' => $this->label,
            'default' => $this->default,
            'required' => $this->required,
            'max' => $this->max,
            'options' => $this->options,
        ];
    }
}
