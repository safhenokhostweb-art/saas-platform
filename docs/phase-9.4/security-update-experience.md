# Security Update Experience — Phase 9.4

## SecurityAdvisoryService

**Namespace:** `SaaSPlatform\CoreUtils\Security\SecurityAdvisoryService`

Provides a global security advisory center with per-module vulnerability visibility, forced patch notices, risk dashboards, and maintenance scheduling.

## Core Capabilities

### Advisory Management

```php
$service->publishAdvisory([
    'severity'      => 'critical',   // critical|high|medium|low|info
    'title'         => 'SQL Injection in FormController',
    'description'   => '...',
    'affected'      => ['forms'],    // module names
    'cve'           => 'CVE-2026-XXXX',
    'patch_version' => '2.1.4',
    'requires_license' => false,     // visible even without license
]);
```

### Patch Status

```php
$status = $service->getPatchStatus('forms', '2.1.3');
// Returns: ['patch_available'=>true,'critical'=>true,'confidence'=>87,'advisories'=>[...]]
```

### Tenant Risk Dashboard

```php
$dashboard = $service->getTenantRiskDashboard($tenantId);
// Returns: ['risk_score'=>30,'risk_level'=>'medium','advisories'=>['critical'=>0,'high'=>1,'total'=>3]]
```

### Patch Confidence Indicator

An integer 0–100 expressing confidence in applying the patch (based on test coverage and rollout history). Displayed in the security posture card.

### Maintenance Scheduling

```php
$id = $service->scheduleMaintenance($tenantId, '2026-06-01T02:00:00Z', 'Security patch 2.1.4');
```

### Staged Rollout

```php
$rollout = $service->getRolloutStatus('forms');
// Returns: ['stage'=>'canary','percentage'=>10,'notes'=>'Deploy to 10% of tenants first']
```

## Critical Rule

> Security visibility (`getAdvisories()`, `getTenantRiskDashboard()`) must exist even without a license.

The `requires_license` flag on advisories controls visibility gating. When `false`, the advisory is visible to all tenants regardless of their subscription level.

## Advisory Storage

Advisories are stored in Laravel cache with a 90-day TTL under:
```
security:advisories:global   — global advisories
security:advisories:{tenantId} — tenant-specific advisories
```

In production, replace the cache-backed store with a database table for persistence across restarts.
