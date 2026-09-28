<?php

namespace SaaSPlatform\ThemeRuntime\Interaction;

final class InteractionDispatchResult
{
    private function __construct(
        public readonly string $status,
        public readonly mixed $result = null,
        public readonly string $reason = '',
    ) {
    }

    public static function handled(mixed $result): self { return new self('handled', $result); }
    public static function blocked(): self { return new self('blocked', null, 'interaction_target_not_authorized'); }
    public static function unhandled(): self { return new self('unhandled', null, 'interaction_handler_not_registered'); }
    public static function failed(): self { return new self('failed', null, 'interaction_handler_failed'); }
    /** Payload failed InteractionEvent validation — never dispatched. */
    public static function rejected(): self { return new self('rejected', null, 'interaction_payload_invalid'); }
}
