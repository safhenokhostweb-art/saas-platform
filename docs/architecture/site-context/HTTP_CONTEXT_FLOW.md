# HTTP Context Flow

1. `AuthenticateTenantApi` clears any stale site context.
2. It validates license and domain, then resolves the authoritative platform `Site`.
3. It sets `SiteContextContract` once from that Site.
4. `tenant_site` remains a backward-compatible request-attribute mirror only.
5. The middleware clears site context in `finally`; invalid credentials leave no context.

Tenant scopes in Forms, Tickets, and CRM resolve `SiteContextContract`, not request attributes.
