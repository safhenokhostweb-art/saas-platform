# Migration Notes

- Legacy `TenantContextContract` remains reseller-aware for backward compatibility.
- `SiteContextContract` is the canonical Site identity for newly migrated tenant-sensitive code.
- Forms Gate, Forms/Tickets/CRM site scopes, tenant API authentication, the Motion site resolver, and tenant restore execution now use the new contract.
- Request `tenant_site` consumers outside these paths remain compatibility debt and must migrate incrementally.
