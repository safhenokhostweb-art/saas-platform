# ENT-1 implementation report

Flow audited: module catalog -> product -> invoice/payment -> module purchase -> module license -> operational `site_modules` -> append-only entitlement ledger -> license API -> customer cached modules -> runtime gate.

Commercial licenses are canonical; `site_modules` is activation; `module_entitlements` is historical audit evidence. The validation API now gets customer modules from the canonical resolver under `SiteContext`. The customer site treats the cached list as a projection and limits configuration fallback to owner/local mode.

Known compatibility boundary: older marketplace presentation code still reports historical purchase ownership separately from runtime entitlement. It is not the customer runtime authority and should be migrated in the next marketplace/API phase.
