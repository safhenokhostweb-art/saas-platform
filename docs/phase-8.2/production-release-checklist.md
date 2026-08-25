# Production Release Checklist

**Phase 8.2 — Gate-by-Gate Release Verification**

**Alignment note (current):** Production releases go through the Artifact
deployment model, not direct server access. See `deployment-runbook.md` for
the authoritative procedure. This checklist's gates are reframed below to
match that model — the old git/composer/npm-on-production steps are kept
further down, clearly marked **Deprecated**, for historical reference only.

```text
Developer commit
        │
        ▼
Artifact build            (Local Agent, off the committed GitHub SHA)
        │
        ▼
Artifact validation       (checksum, completeness gate — Gate 1–2 below)
        │
        ▼
Manual approval           (tech lead / release owner)
        │
        ▼
Deployment System release (Gate 6 below — production never runs Git/Composer/npm)
        │
        ▼
Health verification       (Gate 7 below)
        │
        ├── PASS ─▶ Gate 8–10 (Restore, Monitor, Record)
        └── FAIL ─▶ rollback-runbook.md
```

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

Runs as a pre-release gate against the Local Agent build, before the artifact
is sealed — not on the production host.

## Gate 3 — Deployment Infrastructure

- [ ] No active deployment lock: `php artisan deployment:status`
- [ ] Previous release manifest exists: `deployment:status` shows a latest release
- [ ] Queue is healthy: `php artisan queue:validate-runtime`

## Gate 4 — Pre-Deployment Snapshot

- [ ] Database backup completed and verified
- [ ] Note current migration batch number
- [ ] Note current git commit hash (the SHA the artifact was built from)
- [ ] Note the artifact ID and its SHA-256 checksum (from Deployment System)

## Gate 5 — Tenant Impact Assessment

- [ ] Identify which tenants are affected by this release
- [ ] Notify affected tenants of maintenance window (if SLA requires)
- [ ] Confirm customer-facing test environment passes

## Gate 6 — Deployment Execution (current: Artifact model)

Production is a runtime target only. All of the following are performed by
**Deployment System**, never by an operator running commands directly against
the production host:

- [ ] `php artisan queue:drain-safe --timeout=300 --stamp` (drain, pre-swap)
- [ ] `php artisan deployment:lock`
- [ ] `php artisan tenant:down {affected_ids}` (or global `php artisan down` — see `zero-downtime-deployment-flow.md`)
- [ ] Deployment System verifies the artifact checksum and completeness gate
- [ ] Deployment System atomically swaps the staged release, retaining the prior release as the rollback target
- [ ] `php artisan migrate --force` (run by Deployment System against the swapped release)
- [ ] `php artisan optimize`
- [ ] `php artisan queue:restart` and verify all managed worker groups return

See `deployment-runbook.md` for the full authoritative sequence, including the
pre-release gates and persistent-state handling (`.env`, `storage`,
`public/uploads`, `public/storage`).

### Deprecated — do not use on production

The following describes the **pre-Artifact model** and is kept only for
historical reference. It must not be run against production:

```bash
# DEPRECATED — production must not run Git, Composer, or npm.
git pull
composer install --no-dev --optimize-autoloader
npm run build && php artisan vendor:publish --force
```

## Gate 7 — Post-Deployment Verification

- [ ] `php artisan platform:health --fail-on-warn`
- [ ] `curl https://domain.com/health/ready` → 200
- [ ] `curl https://domain.com/health/release` → 200 (see `deployment-runbook.md`'s note on when this gate applies)
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
- [ ] Record the artifact ID, commit SHA, and checksum in the Deployment System audit trail
- [ ] Update internal release notes

## If any gate fails

Follow `rollback-runbook.md` — activate the last known-good artifact through
Deployment System. Do not attempt an ad hoc Git-based recovery on production.
