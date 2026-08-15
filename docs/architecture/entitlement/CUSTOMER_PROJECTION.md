# Customer projection

The customer `licenses.modules` value is a cache of the platform validation response, not an independent grant. The platform derives it through `ModuleEntitlementResolverContract` under `SiteContext`.

Valid refreshes replace the cached module list. A definite invalid, expired, revoked, domain-mismatch, or inactive-site response sets `platform_valid=false` and clears the list. Customer runtime gates consume that projection and do not infer grants from local configuration.

