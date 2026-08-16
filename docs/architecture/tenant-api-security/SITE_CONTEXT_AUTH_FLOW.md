# Authentication context flow

The middleware clears `SiteContext`, extracts and validates the credential/domain, resolves the validated `Site`, sets the context once, and finally clears it. `tenant_site` remains only as a compatibility mirror. Migrated Finance and Motion APIs resolve identity from `SiteContextContract`.

