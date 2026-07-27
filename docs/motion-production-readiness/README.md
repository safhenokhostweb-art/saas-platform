# Motion Production Readiness

Status: **standalone governance and validation contract**. This layer prepares the Motion Graphics ecosystem for a future, separately approved SaaS integration. It does not implement Laravel, persistence, deployment, authentication, billing, runtime delivery, or an AI provider.

## Scope

This package defines the final pre-integration controls for the existing asset library, governance, AI editing, placement, simulation, creation, and rendering contracts:

- immutable lifecycle and lineage;
- human approval and separation of duties;
- tenant-safe consumption boundaries;
- license policy vocabulary;
- SVG, AI, upload, and runtime threat controls;
- production validation and performance budgets;
- browser QA scenarios; and
- an adapter boundary for future integration.

The companion read-only checker is at `tools/motion-production-readiness-checker/`.

## Authority summary

| Action | Creator | Reviewer | Platform Admin | Tenant | AI provider |
|---|---:|---:|---:|---:|---:|
| Create draft/proposal | yes | no | yes | no | proposal only |
| Review | no | yes | yes | no | no |
| Approve | no | no | yes | no | no |
| Publish/rollback/change ownership | no | no | yes | no | no |
| Consume published projection | no | no | yes | yes | no |
| Create allowed customer variant | request only | no | authorizes | policy-bound | proposal only |

All authority must be enforced server-side by the future platform adapter. Documentation or UI visibility is never authorization.

## Documents

- `LIFECYCLE_MANAGEMENT.md`
- `APPROVAL_WORKFLOW.md`
- `TENANT_AUTHORIZATION.md`
- `LICENSE_MODEL.md`
- `SECURITY_THREAT_MODEL.md`
- `BROWSER_QA_PLAN.md`
- `PERFORMANCE_BUDGET.md`
- `INTEGRATION_ADR.md`
- `FINAL_READINESS_CHECKLIST.md`

