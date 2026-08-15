# Async Context Propagation

Tenant-specific asynchronous work must carry an explicit `site_id`. At execution it must use `SiteContextRunner::runForSiteId()` so the Site is resolved, context is set, work runs, and context is cleared in `finally`.

Platform-global work must use `runPlatform()` or explicitly clear context before execution. HTTP request state must never be used by jobs, commands, or schedulers.

`RestoreTenantDatabaseJob` is the initial migrated tenant job.
