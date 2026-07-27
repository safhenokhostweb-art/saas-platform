# Template Asset Packaging & Site Ownership

Status: **standalone architecture and browser prototype**. This bridge prepares the independent Motion Graphics ecosystem for a future Template Builder integration without connecting to Laravel, databases, deployment, customer runtime, or production providers.

The Asset Library remains the source of truth. Templates store canonical, versioned references and constraints—not physical Master files. A future authorized deployment adapter resolves those references into one immutable, site-scoped package.

```text
Template Builder -> Template Asset Manifest -> Resolver -> Site Asset Package
                                             |              |
                                      Master Library   Customer variants
```

The companion static playground is at `tools/template-asset-packaging-playground/`. Its catalog and customers are fixtures; package generation happens only in browser memory or tests.

## Documents

- `TEMPLATE_ASSET_ARCHITECTURE.md`
- `ASSET_OWNERSHIP_MODEL.md`
- `TEMPLATE_MANIFEST_CONTRACT.md`
- `SITE_ASSET_PACKAGE.md`
- `INHERITANCE_RULES.md`
- `REUSE_POLICY.md`
- `DEPLOYMENT_FLOW.md`
- `COMPATIBILITY_RULES.md`
- `INTEGRATION_HANDOFF.md`

