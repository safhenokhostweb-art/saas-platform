# Sandbox Validation Guide — Phase 8.6

## Commands

```bash
php artisan plugins:validate-sandbox               # Overall sandbox health check
php artisan plugins:scan-dependencies {path}       # Scan module for unsafe imports
php artisan plugins:test-isolation                 # Run isolation boundary tests
php artisan plugins:verify-signatures {path}       # Verify publisher trust + hash
php artisan plugins:runtime-status                 # Show platform plugin status
```

## Dependency Scan Risk Levels

| Classification | Meaning | Execution |
|---|---|---|
| SAFE | No unsafe patterns | Allowed |
| WARNING | Potentially unsafe patterns | Allowed (sandboxed) |
| UNSAFE | Dangerous patterns present | Blocked |
| BLOCKED | App\\ imports or shell exec | Blocked |

## Isolation Tests (plugins:test-isolation)

1. `deny_by_default` — module with no grants receives only free capabilities
2. `grant_and_allow` — explicit grant enables capability
3. `cross_tenant_detection` — cross-tenant attempt detected correctly
4. `capability_boundary` — deployment.execute denied for ungranted module
5. `module_isolation` — module A grants don't leak to module B

## Validation Checks (plugins:validate-sandbox)

1. `capability_registry` — 11 capabilities registered
2. `deny_by_default` — unknown modules get minimal capabilities
3. `sandbox_runtime` — runtime manager active
4. `containment_available` — containment manager resolves

## Unsafe Patterns Detected

**BLOCKED**: `App\\`, `exec(`, `shell_exec(`, `system(`, `passthru(`, `proc_open(`, `popen(`, `eval(`

**UNSAFE**: `DB::statement`, `DB::unprepared`, `Schema::`, `Artisan::call`

**WARNING**: `file_get_contents`, `file_put_contents`, `fopen(`, `curl_init(`, `Http::withoutVerifying`, `withoutTenantIsolation`
