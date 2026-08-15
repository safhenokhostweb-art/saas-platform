# Legacy fallback policy

`MODULE_*_ENABLED` may be used only by an explicitly configured owner/local installation (`site.owner_site=true`) for bootstrap and local development. It is not production customer entitlement authority.

For every non-owner customer installation, missing, expired, or revoked cached projection fails closed. Thus local ENV cannot restore a paid module or defeat a known revocation.

