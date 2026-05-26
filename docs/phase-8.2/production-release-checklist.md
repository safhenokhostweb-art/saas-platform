# Production Release Checklist

**Phase 8.2 — Gate-by-Gate Release Verification**

---

## Gate 1 — Code Quality

- [ ] All feature tests pass
- [ ] No `App\` imports in packages: `php artisan package:validate-distribution`
- [ ] No security regressions from Phase 8.0 checks

## Gate 2 — Migration Safety

- [ ] Run: `php artisan migration:validate-safety`
- [ ] No CRITICAL/HIGH risk migrations (or `--force-production` approved by tech lead)
- [ ] Run: `php artisan migration:detect-drift`
- [ ] No orphaned migrations

## Gate 3 — Deployment Infrastructure

- [ ] No active deployment lock: `php artisan deployment:status`
- [ ] Previous release manifest exists: `deployment:status` shows a latest release
- [ ] Queue is healthy: `php artisan queue:validate-runtime`

## Gate 4 — Pre-Deployment Snapshot

- [ ] Database backup completed and verified
- [ ] Note current migration batch number
- [ ] Note current git commit hash

## Gate 5 — Tenant Impact Assessment

- [ ] Identify which tenants are affected by this release
- [ ] Notify affected tenants of maintenance window (if SLA requires)
- [ ] Confirm customer-facing test environment passes

## Gate 6 — Deployment Execution

- [ ] `php artisan queue:drain-safe --timeout=300 --stamp`
- [ ] `php artisan deployment:lock`
- [ ] `php artisan tenant:down {affected_ids}`
- [ ] `git pull && composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `npm run build && php artisan vendor:publish --force`
- [ ] `php artisan optimize`
- [ ] `php artisan queue:restart`

## Gate 7 — Post-Deployment Verification

- [ ] `php artisan platform:health --fail-on-warn`
- [ ] `curl https://domain.com/health/ready` → 200
- [ ] `curl https://domain.com/health/release` → 200
- [ ] Manual smoke test of affected modules
- [ ] Check logs: `tail -100 storage/logs/laravel.log`

## Gate 8 — Restore

- [ ] `php artisan tenant:up {affected_ids}`
- [ ] `php artisan deployment:unlock`
- [ ] Verify affected tenants are accessible

## Gate 9 — Post-Release Monitoring (first 30 minutes)

- [ ] Error rate unchanged
- [ ] No `deployment.*.failed` log entries
- [ ] No `module_license.check_error` entries
- [ ] Queue processing normally
- [ ] Response times normal

## Gate 10 — Release Record

- [ ] `php artisan deployment:status` confirms new manifest
- [ ] `php artisan deployment:validate-governance` score ≥ 80
- [ ] Update internal release notes
