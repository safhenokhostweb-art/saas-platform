# Leakage audit

Migrated customer tenant callers use Bearer and `X-Tenant-Domain`; credential values no longer enter generated query strings. Legacy-use logs record source, domain/IP, and user agent only. Authentication errors never return or log the credential.

The license validation endpoint supports Bearer with a legacy body bridge. Update checks and site configuration are protected by `tenant.api` and derive their tenant scope from canonical `SiteContext`.
