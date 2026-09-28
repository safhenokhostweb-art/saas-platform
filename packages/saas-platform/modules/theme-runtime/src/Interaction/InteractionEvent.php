<?php

namespace SaaSPlatform\ThemeRuntime\Interaction;

use InvalidArgumentException;

/** Immutable transport object for a widget's user intention. */
final class InteractionEvent
{
    public const EVENT_TYPES = ['click', 'submit', 'change', 'hover'];
    public const ACTION_TRIGGERS = ['navigate', 'call_api', 'toggle_element', 'open_modal', 'scroll_to'];
    private const FORBIDDEN_KEYS = ['__proto__', 'prototype', 'constructor'];

    private function __construct(
        public readonly string $eventType,
        public readonly string $targetId,
        public readonly array $payload,
        public readonly string $actionTrigger,
    ) {
    }

    public static function fromArray(array $value): self
    {
        $eventType = $value['event_type'] ?? null;
        $targetId = $value['target_id'] ?? null;
        $actionTrigger = $value['action_trigger'] ?? null;
        $payload = $value['payload'] ?? null;

        if (!is_string($eventType) || !in_array($eventType, self::EVENT_TYPES, true)) {
            throw new InvalidArgumentException('Unknown interaction event type.');
        }
        if (!is_string($targetId) || preg_match('/^[A-Za-z][A-Za-z0-9_:\-]{0,127}$/', $targetId) !== 1) {
            throw new InvalidArgumentException('Interaction target ID is invalid.');
        }
        if (!is_string($actionTrigger) || !in_array($actionTrigger, self::ACTION_TRIGGERS, true)) {
            throw new InvalidArgumentException('Unknown interaction action trigger.');
        }
        if (!is_array($payload) || array_is_list($payload) || count($payload) > 32) {
            throw new InvalidArgumentException('Interaction payload must be a bounded object.');
        }

        return new self($eventType, $targetId, self::normalizeObject($payload, 0), $actionTrigger);
    }

    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType,
            'target_id' => $this->targetId,
            'payload' => $this->payload,
            'action_trigger' => $this->actionTrigger,
        ];
    }

    private static function normalizeObject(array $value, int $depth): array
    {
        if ($depth > 4) {
            throw new InvalidArgumentException('Interaction payload is too deeply nested.');
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || preg_match('/^[A-Za-z][A-Za-z0-9_\-]{0,63}$/', $key) !== 1
                || in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                throw new InvalidArgumentException('Interaction payload contains an unsafe key.');
            }
            if (is_array($item)) {
                if (count($item) > 64) {
                    throw new InvalidArgumentException('Interaction payload collection is too large.');
                }
                $result[$key] = array_is_list($item)
                    ? array_map(static fn (mixed $entry): string|int|float|bool|null => self::normalizeScalar($entry), $item)
                    : self::normalizeObject($item, $depth + 1);
            } else {
                $result[$key] = self::normalizeScalar($item);
            }
        }
        return $result;
    }

    private static function normalizeScalar(mixed $value): string|int|float|bool|null
    {
        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('Interaction payload contains an unsupported value.');
        }
        if (is_string($value) && (strlen($value) > 4096 || str_contains($value, "\0"))) {
            throw new InvalidArgumentException('Interaction payload text is invalid.');
        }
        return $value;
    }
}
