# Deployment Gateway Integration — Design Only

**Binding instruction for this whole document, received mid-Phase-8 from the user:** "Do NOT connect to any server. Do NOT execute any deployment. Do NOT use SSH, cPanel, VPS access, customer server access, or any external environment. The SaaS deployment mechanism is handled by my independent Deployment System that I designed separately... If any deployment step is required: STOP and only provide the design recommendation." Nothing in this document was executed. `MotionDeploymentGatewayContract` still binds `FakeMotionDeploymentGateway` (Phase 5, unchanged) — no code in this phase touches that binding.

## What Phase 6's audit already established (re-confirmed, not re-derived)

`CustomerInstallationOrchestrator` / driver stack (`CpanelDeploymentDriver`, `SshRemoteDriver`, `SftpDeploymentDriver`, `DockerDeploymentDriver`, `DirectAdminDeploymentDriver`) requires an already-provisioned site with a `remote_server_id` and exists to ship *files* to a *remote server*. Phase 8's actual need — deliver a small, versioned JSON manifest that already-deployed customer-site code fetches over HTTP — is not that problem. Forcing a connection here would misuse the pipeline, not reuse it (Phase 6's own conclusion, unchanged by anything found this phase).

## Recommended adapter shape, if/when a real connection is ever authorized

Should a future phase need actual file-level deployment of Motion runtime assets (rather than fetched-manifest delivery, which this phase implements instead), the recommended integration boundary — **not built, not executed, design only** — would be:

```php
interface MotionDeploymentGatewayContract // unchanged, Phase 5
{
    public function prepareDeployment(DeploymentManifest $manifest): DeploymentGatewayResult;
    public function validateTarget(string $siteReference, MotionAdapterContext $context): DeploymentGatewayResult;
}
```

A real implementation would wrap the user's own independent Deployment System (not `CustomerInstallationOrchestrator`) behind this same contract — the contract's shape (prepare, validate target) already matches a generic deployment gateway's natural interface, so no contract change would be needed, only a new implementation class bound in `MotionGraphicsAdapterServiceProvider` in place of `FakeMotionDeploymentGateway`. That implementation is explicitly out of scope for this session: it would require credentials/connectivity to the user's Deployment System, which this phase was instructed not to use.

## Why the manifest-fetch approach (what was actually built) sidesteps this entirely

Because `MotionRuntimeManifestReader` (customer-site) *pulls* the manifest over the already-existing `tenant.api` HTTP channel rather than having anything *pushed* to it, there is no file-transfer step for a deployment gateway to perform in the first place for this phase's scope. The Deployment Gateway question only becomes load-bearing again if a future phase needs to ship new *code* (not data) to customer-site outside of whatever already delivers customer-site's codebase today — a materially different, larger problem than Phase 8 solves.
