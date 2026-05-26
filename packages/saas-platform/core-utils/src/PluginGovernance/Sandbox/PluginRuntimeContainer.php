<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Sandbox;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginRuntimeContainer
{
    private const STATE_IDLE       = 'idle';
    private const STATE_RUNNING    = 'running';
    private const STATE_SUSPENDED  = 'suspended';
    private const STATE_TERMINATED = 'terminated';

    private string $state = self::STATE_IDLE;

    public function __construct(
        private TelemetryManager $telemetry
    ) {}

    public function run(PluginExecutionContext $context, callable $callback): mixed
    {
        if ($this->state === self::STATE_TERMINATED) {
            throw new \RuntimeException("Container for [{$context->moduleId()}] is terminated.");
        }

        $this->state = self::STATE_RUNNING;

        try {
            $result = $callback($context);

            $this->telemetry->info('plugin_governance', 'plugin.container.execution_complete', [
                'module_id'    => $context->moduleId(),
                'execution_id' => $context->executionId(),
                'elapsed_ms'   => $context->elapsedMs(),
            ]);

            $this->state = self::STATE_IDLE;
            return $result;
        } catch (\Throwable $e) {
            $this->telemetry->error('plugin_governance', 'plugin.container.execution_failed', [
                'module_id'    => $context->moduleId(),
                'execution_id' => $context->executionId(),
                'error'        => $e->getMessage(),
                'elapsed_ms'   => $context->elapsedMs(),
            ]);

            $this->state = self::STATE_IDLE;
            throw $e;
        }
    }

    public function suspend(string $moduleId): void
    {
        $this->state = self::STATE_SUSPENDED;
        $this->telemetry->warning('plugin_governance', 'plugin.container.suspended', ['module_id' => $moduleId]);
    }

    public function terminate(string $moduleId): void
    {
        $this->state = self::STATE_TERMINATED;
        $this->telemetry->critical('plugin_governance', 'plugin.container.terminated', ['module_id' => $moduleId]);
    }

    public function state(): string { return $this->state; }

    public function isRunning(): bool    { return $this->state === self::STATE_RUNNING; }
    public function isSuspended(): bool  { return $this->state === self::STATE_SUSPENDED; }
    public function isTerminated(): bool { return $this->state === self::STATE_TERMINATED; }
}
