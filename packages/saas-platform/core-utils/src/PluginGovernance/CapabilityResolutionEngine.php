<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance;

class CapabilityResolutionEngine
{
    public function __construct(
        private ModuleCapabilityRegistry $registry,
        private CapabilityGrantManager   $grantManager
    ) {}

    public function resolve(string $moduleId, string $capability): bool
    {
        $definition = $this->registry->get($capability);

        if ($definition === null) {
            return false;
        }

        if (!$definition->requiresExplicitGrant()) {
            return true;
        }

        return $this->grantManager->hasGrant($moduleId, $capability);
    }

    public function resolveAll(string $moduleId): array
    {
        $result = [];

        foreach ($this->registry->all() as $name => $definition) {
            $result[$name] = $this->resolve($moduleId, $name);
        }

        return $result;
    }

    public function effectiveCapabilities(string $moduleId): array
    {
        return array_keys(array_filter($this->resolveAll($moduleId)));
    }

    public function deniedCapabilities(string $moduleId): array
    {
        return array_keys(array_filter($this->resolveAll($moduleId), fn(bool $v) => !$v));
    }

    public function resolutionProfile(string $moduleId): ModulePermissionProfile
    {
        $effective = $this->effectiveCapabilities($moduleId);
        $denied    = $this->deniedCapabilities($moduleId);

        return new ModulePermissionProfile($moduleId, $effective, $denied);
    }

    public function canExecuteInContext(string $moduleId, string $capability, string $context): bool
    {
        if (!$this->resolve($moduleId, $capability)) {
            return false;
        }

        $definition = $this->registry->get($capability);

        if ($definition === null) {
            return false;
        }

        $allowed = $definition->allowedContexts();

        return empty($allowed) || in_array($context, $allowed, true);
    }
}
