# Migration notes

Migrated callers: tenant marketplace, support API client, PlatformApiService tenant GET/POST, and license activation. The platform marketplace route is now protected by `tenant.api`.

Update checks and site configuration are now tenant.api routes and resolve the
validated site through SiteContext. Remaining tenant_site readers outside these
paths are compatibility consumers and require endpoint-specific verification.
