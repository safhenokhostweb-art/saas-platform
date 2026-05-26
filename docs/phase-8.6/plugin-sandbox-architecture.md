# Plugin Sandbox Architecture — Phase 8.6

## Overview

Phase 8.6 introduces a deny-by-default capability system with full sandbox isolation for third-party plugins and marketplace extensions. No plugin can execute without explicit capability grants, and all sandbox violations are observable and terminatable.

## Architecture Layers

```
┌─────────────────────────────────────────────────┐
│  MarketplacePolicy / PluginLifecycleManager      │  Install / Activate
├─────────────────────────────────────────────────┤
│  Trust Verification                              │  Signature + Publisher + Integrity
├─────────────────────────────────────────────────┤
│  Dependency Scanning                             │  UnsafeImportDetector + Classifier
├─────────────────────────────────────────────────┤
│  Capability Resolution Engine                    │  Deny-by-default, explicit grants
├─────────────────────────────────────────────────┤
│  Sandbox Runtime Boundary                        │  Context creation, tenant isolation
├─────────────────────────────────────────────────┤
│  Plugin Runtime Container                        │  Execution wrapper, telemetry
├─────────────────────────────────────────────────┤
│  Containment Manager                             │  Violation detection, quarantine
└─────────────────────────────────────────────────┘
```

## Execution Flow

```
1. Plugin requests execution
2. CapabilityResolutionEngine.resolve(moduleId, capability)
   → deny if no grant
3. RuntimeSandboxBoundary.createContext(moduleId, tenantId)
4. RuntimeSandboxBoundary.validateBoundary(context, capability)
5. PluginContainmentManager.guardExecution(context, callback)
   → quarantine check → run → timeout detection
6. All violations → SandboxViolationTelemetry.record()
7. 3 violations OR cross-tenant → UnsafeExecutionTerminator auto-quarantine
```

## Sandbox Invariants

- ✅ No module executes without capability grant
- ✅ No cross-tenant access (isCrossTenantAttempt blocks)
- ✅ All violations emit telemetry (SandboxViolationTelemetry)
- ✅ Auto-quarantine after 3 violations
- ✅ Immediate quarantine for cross-tenant, shell exec, deployment.execute attempts
- ✅ No App\ imports in any plugin code (UnsafeImportDetector: BLOCKED)
- ✅ Execution timeouts detected (30s default)
