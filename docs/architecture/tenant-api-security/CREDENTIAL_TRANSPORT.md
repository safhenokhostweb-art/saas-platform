# Credential transport

Canonical tenant credential transport is `Authorization: Bearer <platform license key>`. The non-secret domain travels in `X-Tenant-Domain`. The platform validates both using existing license rules before setting `SiteContext`.

Customer callers must never generate a URL containing `license_key`.

API-1.1 applies this to update checks and site configuration as well as the
earlier marketplace/support flows. Those routes now use `tenant.api`.
