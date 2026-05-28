# CI Enforcement Guide
## Phase 10.2.5

---

## Pipeline Architecture

Both `saas-platform` and `customer-site` run identical governance CI pipelines on every
push and pull request to `main`/`develop`.

```
Push / PR
    │
    ▼
┌─────────────────────────┐
│ Job 1: static-blade-    │ ← Fast gate (no Laravel boot). Fails immediately
│        analysis         │   on raw controls or hardcoded hex.
└──────────┬──────────────┘
           │ ┌─────────────────────────┐
           ├─▶ Job 2: component-       │ ← Full artisan scan for all
           │  │        authority       │   unauthorized HTML elements.
           │  └──────────┬────────────┘
           │             │
           │  ┌──────────▼────────────┐
           │  │ Job 4: enterprise-    │ ← Requires score ≥ 95 across
           │  │        governance     │   17 weighted dimensions.
           │  └──────────┬────────────┘
           │             │
           │  ┌──────────▼────────────┐
           │  │ Job 5: frontend-      │ ← Zero-drift parity check.
           │  │        parity         │   Auto-heal dry-run.
           │  └───────────────────────┘
           │
           │  ┌─────────────────────────┐
           └─▶ Job 3: responsive-       │ ← Touch targets, RTL, reduced-motion.
              │        governance       │   Runs in parallel with component-authority.
              └─────────────────────────┘
```

---

## Failure Modes

Every job must return a non-zero exit code on violation. No `continue-on-error` is
permitted on governance steps.

| Job | Exit Code 0 | Exit Code 1 |
|-----|-------------|-------------|
| static-blade-analysis | No violations in changed files | Raw control or hardcoded color found |
| component-authority | All components canonical | Unauthorized HTML element found |
| responsive-governance | Touch targets ≥44px, RTL OK | Touch target or RTL violation |
| enterprise-governance | Score ≥95 | Score < 95 |
| frontend-parity | Zero drift, no lockdown issues | Drift detected |

---

## Exit Code Propagation

All artisan governance commands must propagate their exit code to the CI step:

```yaml
- name: Validate Component Authority
  run: |
    php artisan frontend:validate-components \
      --strict \
      --json > /tmp/report.json 2>&1
    EXITCODE=$?
    cat /tmp/report.json
    exit $EXITCODE
```

The `exit $EXITCODE` line is mandatory. Without it, the step always reports success
even when the artisan command fails.

---

## Artifact Upload

Every job uploads its JSON report as an artifact, regardless of pass/fail:

```yaml
- name: Upload report
  if: always()
  uses: actions/upload-artifact@v3
  with:
    name: governance-report
    path: /tmp/report.json
```

`if: always()` ensures the report is available even when the job fails.

---

## fetch-depth: 0 Requirement

All checkout steps must use `fetch-depth: 0`:

```yaml
- uses: actions/checkout@v4
  with:
    fetch-depth: 0
```

Without this, GitHub Actions performs a shallow clone. The `static-blade-analysis` job
uses `git diff origin/main...HEAD` to identify changed files. A shallow clone causes
this command to fail because `origin/main` is not in the local history.

---

## Environment Requirements

Governance artisan commands require a minimal Laravel environment:

```yaml
env:
  APP_ENV: testing
  DB_CONNECTION: sqlite
  DB_DATABASE: ':memory:'
```

All governance services use file system scanning only. No database queries are executed.

---

## Secrets and Credentials

Governance CI pipelines do not require any secrets or API keys. All validation is
performed against local files only.

---

## Caching

Composer packages are cached to reduce CI run time:

```yaml
- uses: actions/cache@v3
  with:
    path: vendor
    key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
    restore-keys: ${{ runner.os }}-php-
```

---

## When CI Is Bypassed (Emergency Policy)

If a governance check must be bypassed for an emergency:

1. Open an issue labeled `governance-exception`
2. Get approval from the lead architect
3. The bypass MUST be reverted within 24 hours
4. A follow-up PR must fix the underlying violation
5. Emergency bypasses are tracked in the `governance-exception` GitHub environment
