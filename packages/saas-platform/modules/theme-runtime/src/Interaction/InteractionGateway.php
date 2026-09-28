<?php

namespace SaaSPlatform\ThemeRuntime\Interaction;

use InvalidArgumentException;

/**
 * Phase 3C/M3 closing gap — the ONE server-side entry point for interaction
 * events. Receives a raw (browser-submitted) interaction payload — the same
 * shape the client runtime emits on 'theme:interaction' — normalizes and
 * validates it through InteractionEvent, then dispatches it through
 * InteractionDispatcher, whose InteractionPolicy check always runs BEFORE
 * any handler.
 *
 * Division of responsibility per action trigger:
 *   - toggle_element / open_modal are pure CLIENT UI actions: the server's
 *     entire role is authorization. If (and only if) the policy allows the
 *     target, the built-in handler answers may_execute=true; a violating
 *     target never reaches the handler and comes back "blocked".
 *   - navigate / call_api carry real side effects: they require a
 *     host-registered handler (withHandler()/constructor); without one they
 *     come back "unhandled" — the gateway never invents a side effect.
 *
 * Malformed payloads never throw: they are rejected with a stable status so
 * a future HTTP surface can map this method's result onto a response.
 */
final class InteractionGateway
{
    private InteractionPolicy $policy;

    /** @var array<string,callable> host + default handlers, keyed by action trigger */
    private array $trackedHandlers = [];

    /** @var list<string> actions the server authorizes without a host handler */
    private const UI_ONLY_ACTIONS = ['toggle_element', 'open_modal'];

    public function __construct(InteractionPolicy $policy, array $handlers = [])
    {
        $this->policy = $policy;

        foreach (self::UI_ONLY_ACTIONS as $action) {
            $this->trackedHandlers[$action] = static fn (InteractionEvent $event): array => [
                'may_execute' => true,
                'target_id' => $event->targetId,
            ];
        }

        foreach ($handlers as $action => $handler) {
            if (is_string($action) && in_array($action, InteractionEvent::ACTION_TRIGGERS, true) && is_callable($handler)) {
                $this->trackedHandlers[$action] = $handler;
            }
        }
    }

    /** Registers a host side-effect handler for one action trigger. */
    public function withHandler(string $actionTrigger, callable $handler): self
    {
        if (!in_array($actionTrigger, InteractionEvent::ACTION_TRIGGERS, true)) {
            throw new InvalidArgumentException("Unknown interaction action trigger '{$actionTrigger}'.");
        }
        $this->trackedHandlers[$actionTrigger] = $handler;

        return $this;
    }

    /**
     * Accepts the payload shape InteractionEvent::fromArray() accepts
     * (identical to the client's theme:interaction detail) and returns the
     * authorization/dispatch decision. Never throws for bad input.
     */
    public function handle(array $payload): InteractionDispatchResult
    {
        try {
            $event = InteractionEvent::fromArray($payload);
        } catch (InvalidArgumentException) {
            return InteractionDispatchResult::rejected();
        }

        // Rebuild the dispatcher per dispatch: InteractionDispatcher is
        // immutable by design, and withHandler() may have changed the
        // handler set since construction. Dispatch cost is one small
        // array copy per event.
        $dispatcher = new InteractionDispatcher($this->policy, $this->trackedHandlers);

        return $dispatcher->dispatch($event);
    }

    /** The policy this gateway enforces (exposed for diagnostics/testing). */
    public function policy(): InteractionPolicy
    {
        return $this->policy;
    }
}
