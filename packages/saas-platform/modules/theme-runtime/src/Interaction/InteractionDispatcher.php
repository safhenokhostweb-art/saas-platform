<?php

namespace SaaSPlatform\ThemeRuntime\Interaction;

use Closure;
use Throwable;

/** Routes authorized intentions to replaceable host handlers. */
final class InteractionDispatcher
{
    /** @var array<string,Closure> */
    private array $handlers = [];

    public function __construct(private readonly InteractionPolicy $policy, array $handlers = [])
    {
        foreach ($handlers as $action => $handler) {
            if (is_string($action) && in_array($action, InteractionEvent::ACTION_TRIGGERS, true) && is_callable($handler)) {
                $this->handlers[$action] = Closure::fromCallable($handler);
            }
        }
    }

    public function dispatch(InteractionEvent $event): InteractionDispatchResult
    {
        if (!$this->policy->allows($event)) {
            return InteractionDispatchResult::blocked();
        }
        $handler = $this->handlers[$event->actionTrigger] ?? null;
        if ($handler === null) {
            return InteractionDispatchResult::unhandled();
        }
        try {
            return InteractionDispatchResult::handled($handler($event));
        } catch (Throwable) {
            return InteractionDispatchResult::failed();
        }
    }
}
