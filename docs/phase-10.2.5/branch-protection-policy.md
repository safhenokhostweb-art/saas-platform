# Branch Protection Policy
## Phase 10.2.5

---

## Policy Statement

All merges to `main` and `develop` in both `saas-platform` and `customer-site`
**MUST** pass the complete frontend governance CI pipeline. No exceptions for
administrators.

---

## Protected Branches

| Repository | Branch | Protection Level |
|-----------|--------|-----------------|
| saas-platform | `main` | Full governance enforcement |
| saas-platform | `develop` | Full governance enforcement |
| customer-site | `main` | Full governance enforcement |
| customer-site | `master` | Full governance enforcement |
| customer-site | `develop` | Full governance enforcement |

---

## Required Checks (Both Repositories)

All 5 checks must pass:

1. `Static Blade Analysis (fast)`
2. `Component Authority`
3. `Responsive Governance`
4. `Enterprise Frontend Governance (≥95)`
5. `Frontend Parity (Zero Drift)`

---

## Merge Requirements

- Pull Request required (no direct pushes to protected branches)
- At minimum 1 reviewer approval
- All conversations resolved
- Branch must be up to date with target
- All required status checks passing
- Stale reviews dismissed on new commits

---

## Force Push and Deletion

Force pushes to `main` and `develop` are **permanently disabled**.
Branch deletions for `main` and `develop` are **permanently disabled**.

---

## Admin Bypass Policy

Repository administrators are **NOT exempt** from branch protection rules.
The "Do not allow bypassing the above settings" option must be enabled.

This prevents:
- Emergency hotfixes bypassing governance
- Administrators merging broken frontend code under time pressure
- History rewriting that could remove governance evidence

---

## Exception Process

If a governance check must be bypassed for a production emergency:

1. The PR author creates an issue labeled `governance-exception`
2. The issue is reviewed and approved by the lead architect
3. The exception environment (`governance-exception`) is activated for that specific PR
4. The bypass is logged in the GitHub audit trail
5. A follow-up PR fixing the underlying violation must be created within 24 hours
6. The exception issue is closed after the fix lands

There is no silent bypass path. Every exception is tracked.

---

## Enforcement Verification

To verify branch protection is correctly configured:

1. Create branch: `git checkout -b test/raw-control-violation`
2. Add a raw `<button>` to any non-core-ui Blade file
3. Push branch and open a PR targeting `main`
4. Verify:
   - `Static Blade Analysis (fast)` fails
   - `Component Authority` fails
   - The merge button is blocked ("Some checks were not successful")
5. Remove the violation, push, verify CI passes and merge is available

---

## Audit Trail

GitHub maintains an immutable audit trail of:
- All branch protection rule changes
- All protected branch bypasses
- All PR merges and rejections
- All status check results

Access: Settings → Audit Log → Filter by "protected_branch"
