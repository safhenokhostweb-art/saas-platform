# SC-1 Site Context Unification

Status: implemented for tenant API establishment, high-risk shared model scopes, Forms access, Motion current-site resolution, and tenant database restore execution.

The context is site-only, explicit, serializable, and cleared after authenticated requests or `SiteContextRunner` work. Reseller context remains separate. ENT-1 may proceed only after remaining request-attribute consumers are migrated in the relevant entitlement paths.
