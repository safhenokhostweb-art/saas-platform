# Extension Lifecycle Governance — Phase 8.6

## States

```
unregistered → inactive → active → suspended / blocked
                              ↑
                         (re-activate)
```

## Registration Flow

```
PluginLifecycleManager.register(moduleId, version, modulePath)
  1. PluginDependencyScanner.scan() → BLOCKED → state=blocked
  2. PluginActivationCoordinator.activate() → state=active
  3. PluginRollbackCoordinator.saveSnapshot(version)
  4. PluginHealthMonitor.recordSuccess()
```

## Health Monitoring

- Success recorded after each successful execution
- Failure recorded after each exception
- 5 consecutive failures → module auto-deactivated
- Health states: healthy, degraded, critical, unknown

## Upgrade Governance

- Major version jumps (>1) require manual approval
- New version is dependency-scanned before activation
- Snapshot saved before upgrade for rollback capability

## Rollback

```
PluginRollbackCoordinator.rollback(moduleId, reason)
  → reads snapshot → deactivates current version → logs rollback
  → Operator must re-register with restored path
```
