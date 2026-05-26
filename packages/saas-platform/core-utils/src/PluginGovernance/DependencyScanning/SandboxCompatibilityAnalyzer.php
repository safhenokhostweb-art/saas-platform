<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning;

class SandboxCompatibilityAnalyzer
{
    private const INCOMPATIBLE_FUNCTIONS = [
        'set_time_limit', 'ini_set', 'error_reporting', 'set_error_handler',
        'register_shutdown_function', 'ob_start', 'header(', 'setcookie(',
    ];

    private const REQUIRES_CAPABILITY = [
        'Storage::put'     => 'storage.upload',
        'Storage::get'     => 'filesystem.read',
        'Queue::push'      => 'queue.dispatch',
        'Queue::later'     => 'queue.dispatch',
        'dispatch('        => 'queue.dispatch',
        'Http::post('      => 'api.external',
        'Http::get('       => 'api.external',
        'Mail::send'       => 'notification.send',
        'Notification::send' => 'notification.send',
        'Schedule::'       => 'runtime.schedule',
    ];

    public function analyze(string $sourceCode): array
    {
        return [
            'incompatible_functions' => $this->findIncompatibleFunctions($sourceCode),
            'capability_requirements'=> $this->findCapabilityRequirements($sourceCode),
            'compatible'             => $this->isCompatible($sourceCode),
        ];
    }

    public function isCompatible(string $sourceCode): bool
    {
        foreach (self::INCOMPATIBLE_FUNCTIONS as $fn) {
            if (str_contains($sourceCode, $fn)) {
                return false;
            }
        }
        return true;
    }

    public function findCapabilityRequirements(string $sourceCode): array
    {
        $required = [];

        foreach (self::REQUIRES_CAPABILITY as $pattern => $capability) {
            if (str_contains($sourceCode, $pattern)) {
                $required[$capability] = $pattern;
            }
        }

        return $required;
    }

    private function findIncompatibleFunctions(string $sourceCode): array
    {
        $found = [];

        foreach (self::INCOMPATIBLE_FUNCTIONS as $fn) {
            if (str_contains($sourceCode, $fn)) {
                $found[] = $fn;
            }
        }

        return $found;
    }
}
