# GitHub Branch Protection Guide — Frontend Governance Enforcement
## Phase 10.2.5 — Enterprise CI Lockdown

This guide provides exact GitHub repository settings to enforce mandatory frontend
governance on all pull requests before merging to protected branches.

---

## Required Status Checks

All checks in `.github/required-checks.json` must pass before any merge is allowed.

### saas-platform

| Check Name                                    | Job                  | Blocking |
|-----------------------------------------------|----------------------|----------|
| `Static Blade Analysis (fast)`                | static-blade-analysis | ✅ Yes  |
| `Component Authority`                         | component-authority   | ✅ Yes  |
| `Responsive Governance`                       | responsive-governance | ✅ Yes  |
| `Enterprise Frontend Governance (≥95)`        | enterprise-governance | ✅ Yes  |
| `Frontend Parity (Zero Drift)`                | frontend-parity       | ✅ Yes  |

### customer-site

| Check Name                                    | Job                  | Blocking |
|-----------------------------------------------|----------------------|----------|
| `Static Blade Analysis (fast)`                | static-blade-analysis | ✅ Yes  |
| `Component Authority`                         | component-authority   | ✅ Yes  |
| `Responsive Governance`                       | responsive-governance | ✅ Yes  |
| `Enterprise Frontend Governance (≥95)`        | enterprise-governance | ✅ Yes  |
| `Frontend Parity (Zero Drift)`                | frontend-parity       | ✅ Yes  |

---

## Step-by-Step GitHub Settings

### 1. Navigate to Branch Protection Rules

1. Open the repository on GitHub
2. Go to **Settings** → **Branches**
3. Under **Branch protection rules**, click **Add rule**

### 2. Branch Name Pattern

```
main
```

Also add a rule for:

```
develop
```

### 3. Required Settings (apply to both `main` and `develop`)

| Setting | Value |
|---------|-------|
| **Require a pull request before merging** | ✅ Enabled |
| **Require approvals** | 1 (minimum) |
| **Dismiss stale pull request approvals when new commits are pushed** | ✅ Enabled |
| **Require status checks to pass before merging** | ✅ Enabled |
| **Require branches to be up to date before merging** | ✅ Enabled |
| **Require conversation resolution before merging** | ✅ Enabled |
| **Do not allow bypassing the above settings** | ✅ Enabled for admins too |
| **Restrict who can push to matching branches** | Limit to CI service accounts only |
| **Allow force pushes** | ❌ Disabled |
| **Allow deletions** | ❌ Disabled |

### 4. Required Status Checks (exact names)

These are the **exact** GitHub Actions job names that must be added as required checks.
The names must match the `name:` field in the workflow YAML exactly.

#### saas-platform — add these checks:

```
Static Blade Analysis (fast)
Component Authority
Responsive Governance
Enterprise Frontend Governance (≥95)
Frontend Parity (Zero Drift)
```

#### customer-site — add these checks:

```
Static Blade Analysis (fast)
Component Authority
Responsive Governance
Enterprise Frontend Governance (≥95)
Frontend Parity (Zero Drift)
```

---

## Admin Bypass Policy

**Policy: Admins are NOT exempt from branch protection.**

The setting **"Do not allow bypassing the above settings"** must be enabled.
This ensures governance violations fail merges even for repository administrators.

**Rationale:** Frontend governance drift has historically been introduced by emergency
hotfixes bypassing CI. This policy eliminates that escape hatch entirely.

**Exception process:**
1. Create a GitHub environment named `governance-exception`
2. Require a named approver (lead architect) for exception environment
3. All governance exceptions are automatically logged to the audit trail

---

## PR-Only Enforcement

Direct pushes to `main` must be blocked. All code enters through pull requests.

```yaml
# Already enforced by workflow triggers:
on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]
```

The `push` trigger runs on direct pushes (emergency hotfixes).
The `pull_request` trigger runs on all PRs.
Both must pass for a change to land in `main`.

---

## Deployment Gating

Deployments to production are gated on governance passing:

```yaml
# In deployment workflow:
needs: [frontend-governance]  # references the governance workflow result
environment:
  name: production
  url: https://app.example.com
```

If any governance job fails, the deployment workflow will not run.

---

## Force-Push Restrictions

Force pushes to `main` and `develop` must be permanently disabled.
This prevents history rewriting that could bypass governance checks.

```
Allow force pushes → DISABLED
Allow deletions    → DISABLED
```

---

## Monitoring Governance Status

Use the cross-repository validator to check governance health at any time:

```bash
php artisan frontend:validate-cross-repository-governance --strict --json
```

Or generate a full lockdown report:

```bash
php artisan frontend:lockdown-report
```

---

## Enforcement Verification

After configuring branch protection, verify with:

1. Create a test branch with a raw `<button>` in a Blade file
2. Open a PR targeting `main`
3. Confirm CI fails on `Static Blade Analysis (fast)` and `Component Authority`
4. Confirm the merge button is blocked
5. Fix the violation, push — confirm CI passes and merge is allowed

---

## Related Files

- `.github/required-checks.json` — machine-readable list of required checks
- `.github/workflows/frontend-governance.yml` — governance CI pipeline
- `docs/phase-10.2.5/ci-enforcement-guide.md` — detailed CI guide
- `docs/phase-10.2.5/frontend-drift-prevention.md` — drift prevention policies
