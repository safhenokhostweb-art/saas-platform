# Module Update Safety Rules

**PHASE 6.98 — TASK 9**
**Authority:** These rules are MANDATORY for all module releases and updates.
Violations block the release pipeline.

---

## Core Guarantees

Every module update MUST satisfy all of the following guarantees.
No exceptions. No workarounds.

| # | Guarantee | Enforced By |
|---|-----------|-------------|
| 1 | Tenant overrides survive updates | `ModuleUpdateOrchestrator::validatePostUpdate()` |
| 2 | Published files are never silently overwritten | Snapshot + drift detection |
| 3 | Migrations are reversible | `PlatformCompatibilityService::isRollbackSafe()` |
| 4 | Failed updates roll back safely | `ModuleUpdateOrchestrator::rollback()` |
| 5 | Schema version is tracked per release | `ReleaseManifest::generate()` |
| 6 | Package authority remains canonical | `php artisan platform:validate-authority` |
| 7 | Customer runtime customizations survive updates | Override preservation check |

---

## Rule 1 — Tenant Override Preservation

### Rule
A module update MUST NOT delete or overwrite files that a customer site
has published (config files, view overrides).

### Why
Customer sites customise module behaviour by publishing config/views.
Losing those customisations after an update breaks customer deployments
silently and is very hard to recover from.

### How to apply
- `composer update` only replaces `vendor/` — published files are safe
- **Never** run `php artisan vendor:publish --force` automatically in update scripts
- `ModuleUpdateOrchestrator::prepareUpdate()` snapshots published file checksums before update
- `ModuleUpdateOrchestrator::validatePostUpdate()` compares checksums after update
- Any unexpected change is reported as a warning; deletion is reported as an error

---

## Rule 2 — Published File Integrity

### Rule
Published config and view files MUST NOT be modified by the update process
without explicit customer action.

### Why
Published files belong to the customer site, not the module package.
Silently overwriting them violates the customer's right to customise.

### How to apply
- Module updates deliver new versions to `vendor/` only
- If a new release changes default config values, document them in the changelog
- Use `php artisan vendor:publish --tag={tag} --force` only when the customer
  explicitly requests it (e.g. after reading migration notes)
- The release manifest's `publish_tags` field lists which tags changed — communicate this

---

## Rule 3 — Reversible Migrations

### Rule
Every database migration added by a module MUST have a functioning `down()` method.

### Why
If an update fails mid-way or causes a production issue, the database must
be rollable back to the exact pre-update state. A missing `down()` makes
rollback impossible and locks the database in a broken intermediate state.

### How to apply
- `PlatformCompatibilityService::isRollbackSafe()` checks all migration files
- `module:build` warns when a migration lacks `down()`
- **Never** use `Schema::drop()` inside `up()` without a matching `Schema::create()` in `down()`
- Destructive schema changes (column removal, type changes) must be split across
  two releases to allow safe rollback

---

## Rule 4 — Automatic Rollback on Failure

### Rule
If any step of an update fails (composer, migrate, cache clear), the update
orchestrator MUST trigger a rollback before returning control to the operator.

### Why
A partially-applied update leaves the system in an inconsistent state —
new code may reference database columns that don't exist yet, or vice versa.
Automatic rollback eliminates this window of inconsistency.

### How to apply
- `ModuleUpdateOrchestrator` implements `rollback()` with full state from `prepareUpdate()`
- The `module_installations` table records `status = failed` before rollback begins
- After rollback succeeds, status is updated to `rolled_back`
- Rollback steps: restore vendor state → `migrate:rollback` → `cache:clear` → validate

---

## Rule 5 — Schema Version Tracking

### Rule
Every module release that includes database migrations MUST increment
the `schema_version` in `module.json`.

### Why
The schema version allows the update system to detect if a customer is
running a schema that is out of sync with the installed code version.
Without this, silent data corruption is possible after a failed partial update.

### How to apply
- `module.json` has a `schema_version` field (integer as string, e.g. `"3"`)
- Increment `schema_version` whenever `database/migrations/` changes
- `ReleaseManifest::generate()` copies `schema_version` into the release manifest
- `ModuleUpdateOrchestrator::validateUpdate()` checks schema version jump is safe
- Customers running schema v1 updating to schema v3 must apply migrations sequentially

---

## Rule 6 — Package Authority Remains Canonical

### Rule
Module views, config, and logic MUST always originate from the canonical
package (`vendor/saas-platform/modules-{slug}` or path symlink).
Duplicated module files in `app/`, `resources/`, or `routes/` are forbidden.

### Why
Duplicate files cause drift: the canonical package is updated but the
duplicate copy stays stale. This is the root cause of most silent regressions
in distributed module architectures.

### How to apply
- `php artisan drift:authority` detects duplicate views/controllers
- `php artisan platform:validate-authority` confirms no runtime JSON dependencies
- `module:build` checks for hardcoded layouts in module views
- Customer customisation happens through:
  - `php artisan vendor:publish` (explicit, tracked)
  - Service binding overrides (extend in customer `AppServiceProvider`)
  - Config overrides (`config/forms.php` publish tag)

---

## Rule 7 — Customer Runtime Customisations Survive

### Rule
Customer-side customisations — service bindings, config overrides, published views,
route additions — MUST survive a module update unchanged.

### Why
Customers build on top of the platform. An update that silently resets their
customisations creates emergency support tickets and destroys trust.

### How to apply
- Module `ServiceProvider::boot()` uses `mergeConfigFrom()` not `loadConfigFrom()`,
  so customer published configs always win
- Module views are loaded with `$this->loadViewsFrom()` with fallback to package views —
  customer published views in `resources/views/vendor/{module}/` take priority
- New service bindings added by the module must not conflict with existing container bindings
- `ModuleUpdateOrchestrator::checkTenantOverrides()` verifies override directories post-update

---

## Drift Prevention

### What is drift?
Drift occurs when the state of a deployed module diverges from the canonical
package without being tracked. Common causes:
- Editing files inside `vendor/` directly (lost on next `composer install`)
- Copying module files into the application and editing them
- Running `vendor:publish --force` without documenting the change

### Prevention rules
1. **Never edit `vendor/` directly** — changes are lost on next composer install
2. **Only customise through published paths** — config, views, and bindings
3. **Track all published customisations** in source control
4. Run `php artisan drift:authority` in CI to catch drift automatically
5. Run `php artisan drift:blades` to detect duplicate view files

---

## Distributed Hosting Considerations

When modules are distributed to customer servers via Satis:

- Each customer server runs its own `composer.json` — versions may diverge
- The `module_installations` table provides the audit trail per server
- The `environment` column differentiates local / staging / production installs
- The `source_type` column (`symlink` vs `satis`) distinguishes dev from production
- License validation (future phase) gates Satis access per customer token
- Never push a release to Satis without first running `module:build` and `module:release`

---

## Rollback Guarantees

| Scenario | Rollback Method | Recovery Time |
|----------|----------------|---------------|
| composer install fails | Re-run previous `composer.json` | < 5 min |
| Migration fails mid-run | `migrate:rollback --step=N` | < 10 min |
| Runtime error after update | Composer downgrade + rollback | < 15 min |
| Published file corrupted | Restore from snapshot (git) | < 5 min |
| Module conflict detected | Remove conflicting module | Manual review |

### Rollback is always possible when:
- ✅ All migrations have `down()` methods
- ✅ Previous version is available in Satis (or git tag exists)
- ✅ `module_installations` record exists with `previous_version`
- ✅ Published file checksums were snapshotted before update

### Rollback may fail when:
- ❌ Migrations lack `down()` (caught by `module:build`)
- ❌ Previous version removed from Satis (retention policy required)
- ❌ Data was destructively migrated without a restoration path
