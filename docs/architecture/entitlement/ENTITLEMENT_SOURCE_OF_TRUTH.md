# Entitlement source of truth

Commercial truth is `platform_licenses` for the site-wide license and `module_licenses` for paid or license-only modules. A successful module product purchase is fulfilled into the latter; invoices and payments are commerce evidence, not runtime gates.

`ModuleEntitlementResolverContract` is the only runtime commercial decision service. Its site-context methods require `SiteContextContract`; callers must not derive entitlement authority from `tenant_site`.

The customer license endpoint projects only modules that are both commercially entitled and operationally active. This preserves the existing meaning of a customer runtime module list without making `site_modules` a commercial authority.

