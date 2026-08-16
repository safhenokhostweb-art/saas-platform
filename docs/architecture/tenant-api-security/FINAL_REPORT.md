# API-1 report

Bearer is canonical, legacy query/body use is temporary and observable without credential logging, and conflicting credentials fail closed. Marketplace, Finance Ledger, Motion runtime, update checks, and site configuration now use authenticated `SiteContext` rather than `tenant_site` as authority. Marketplace regression execution is blocked by the unavailable MySQL test service; see `REGRESSION_CLOSURE.md`.
