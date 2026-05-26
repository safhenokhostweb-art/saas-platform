# Tenant Maintenance Governance

**Phase 8.2 — Per-Tenant Isolation**

---

## Table: `tenant_maintenance`

| Column | Type | Description |
|---|---|---|
| `site_id` | bigint | The tenant site ID |
| `scope` | varchar(30) | `full`, `module`, `partial` |
| `module` | varchar(100) | Affected module (when `scope=module`) |
| `message` | varchar(500) | User-facing message (Persian) |
| `allow_admin_bypass` | boolean | Platform admins can bypass |
| `allow_health_bypass` | boolean | Health probes bypass |
| `allow_deployment_bypass` | boolean | Deployment calls bypass |
| `initiated_by` | varchar(255) | Username/process that started maintenance |
| `deployment_uuid` | varchar(36) | Associated deployment (nullable) |
| `started_at` | timestamp | When maintenance began |
| `ended_at` | timestamp | When maintenance ended (null = active) |

---

## Bypass Rules

The middleware `CheckTenantMaintenance` always allows through:

1. **Health endpoints** — any path starting with `health/`
2. **Admin users** — `auth()->user()->is_admin === true`
3. **Non-tenant contexts** — requests not associated with a specific site

---

## Middleware Registration

Add to `app/Http/Kernel.php` web middleware group:

```php
// In $middlewareGroups['web']:
\App\Http\Middleware\CheckTenantMaintenance::class,
```

Position it after `SetTenantContext` so the tenant is resolved before the maintenance check runs.

---

## Scopes

| Scope | Behavior |
|---|---|
| `full` | All requests for this site → 503 |
| `module` | Only requests for the specific module → 503 |
| `partial` | Custom logic (extend `CheckTenantMaintenance`) |

---

## Deployment-Linked Maintenance

When using the deployment orchestrator, maintenance windows are automatically linked to the deployment UUID:

```bash
php artisan tenant:down {id} --deployment={uuid}
# After deployment finishes:
php artisan deployment:rollback ...  # auto-lifts if deployment_uuid matches
```

Or manually lift all maintenance associated with a deployment:

```php
$manager->disableForDeployment($deploymentUuid);
```

---

## Monitoring

```bash
php artisan tenant:maintenance-status
php artisan tenant:maintenance-status --json
```
