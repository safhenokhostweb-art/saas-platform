# Future Integration Contract

This module is complete and tested as a standalone package but is not currently connected to the main SaaS platform or customer websites.

Future platform adapters must bind:

| Contract | Responsibility |
|---|---|
| PlatformAdminResolverContract | Resolve an external actor and prove Platform Admin authority. |
| MotionPermissionGatewayContract | Authorize each ability; tenants remain consumers. |
| SiteResolverContract | Resolve sites and enforce tenant isolation. |
| MotionAiProviderContract | Return normalized proposals; never approve or publish. |
| MotionAssetStorageContract | Store immutable versioned assets safely. |
| MotionCompilerContract / MotionOptimizerContract | Produce deterministic, safe optimized output. |
| MotionPreviewGeneratorContract | Generate private admin previews. |
| MotionAuditLoggerContract | Persist lifecycle/security events. |
| MotionDeploymentGatewayContract | Notify finalized deployment infrastructure. |
| ClockContract | Provide time. |

Actor and site identities are intentionally scalar external identifiers. Claude must map/validate them against finalized authorities and make any foreign-key conversion an explicit migration decision.

Runtime output is allowlisted. Sources, editable schemas, prompts, AI metadata, drafts, internal paths, and private metadata are forbidden.
