# Activation Checklist

This module is complete and tested as a standalone package but is not currently connected to the main SaaS platform or customer websites.

- [ ] Review finalized governance, tenancy, RBAC, licensing, and update architecture.
- [ ] Register autoload/provider once through the platform module mechanism.
- [ ] Bind and test every contract in FUTURE_INTEGRATION_CONTRACT.md.
- [ ] Prove Platform Admin-only authoring and tenant read-only consumption.
- [ ] Map external actor/site identifiers and TenantContext isolation.
- [ ] Validate migrations on disposable MySQL, staging, then production.
- [ ] Configure private source/preview and immutable published storage.
- [ ] Connect AI, audit, and deployment adapters.
- [ ] Add authenticated routes/menu only after authorization passes.
- [ ] Install customer runtime and Blade component separately.
- [ ] Verify runtime allowlist, integrity, caching, rollback, and regressions.

Keep enabled, routes_enabled, admin_ui_enabled, runtime_api_enabled, publishing_enabled, ai_enabled, customer_runtime_enabled, and real_storage_enabled false until each corresponding dependency and security gate passes.
