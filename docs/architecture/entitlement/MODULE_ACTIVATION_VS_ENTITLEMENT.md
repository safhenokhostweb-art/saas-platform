# Activation versus entitlement

`ModuleEntitlementResolverContract` answers commercial right. `site_modules.is_active` is an operational activation/runtime-distribution state. Deployment and installation state remain separate.

A deployment failure must not revoke a paid `module_licenses` record. Conversely, a valid license alone does not put a module into the customer runtime projection until the site assignment is active.

