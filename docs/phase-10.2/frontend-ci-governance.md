# Frontend CI Governance

**Phase:** 10.2  
**File:** `.github/workflows/frontend-governance.yml`

---

## Pipeline Overview

The frontend governance CI pipeline runs on every push/PR that touches Blade or CSS files. It has 5 jobs:

| Job | Trigger | Fails On |
|-----|---------|----------|
| `component-authority` | Any Blade change | Raw controls, inline styles, hardcoded colors |
| `responsive-governance` | Any Blade change | Missing touch targets, table wrappers, no prefers-reduced-motion |
| `enterprise-governance` | After component-authority passes | Score < 95/100 |
| `frontend-parity` | After enterprise-governance passes | Zero-drift not achieved |
| `static-blade-analysis` | Any Blade change | Fast static grep (no Laravel boot required) |

---

## Gates — What Blocks a Merge

### Immediate Blockers (no build required)
The `static-blade-analysis` job runs in ~30 seconds with no Laravel boot:

- Raw `<button>` in modified Blade files (except: core-ui component files, Alpine `x-for` templates)
- Raw `<input>` in modified Blade files (same exceptions)
- Raw `<select>` in modified Blade files
- Raw `<textarea>` in modified Blade files
- Hardcoded `#hex` color in `style=""` attribute

### Build-Required Blockers
After full Laravel bootstrap:

- `frontend:validate-component-authority --fail-on-drift` returns non-zero exit
- `frontend:validate-enterprise-governance --fail-below=95` returns non-zero exit
- `frontend:validate-parity --fail-on-drift` returns non-zero exit

---

## Adding New Frontend Rules

To add a new enforcement rule to the CI pipeline:

1. Add the detection logic to the appropriate PHP service
2. Update the command's exit code logic
3. Update the `static-blade-analysis` job's grep patterns if it can be detected statically

---

## Artifacts

Every CI run uploads reports as GitHub Actions artifacts:
- `component-authority-report/` — JSON with all violations
- `responsive-governance-report/` — JSON with responsive issues
- `enterprise-governance-report/` — JSON with 10-dimension scores
- `frontend-parity-report/` — JSON with zero-drift status

These artifacts are retained for 30 days (GitHub default).

---

## Local Pre-Check Before Push

Run this before pushing any Blade changes:

```bash
php artisan frontend:validate-component-authority --strict
php artisan frontend:validate-parity --fail-below=95
php artisan frontend:auto-heal --dry-run
```
