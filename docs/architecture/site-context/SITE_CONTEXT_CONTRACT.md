# Site Context Contract

`SiteContextContract` is the only canonical platform-side identity for an authenticated customer installation.

- It stores only a positive `site_id`.
- `hasSite()` distinguishes tenant work from explicit platform work.
- `requireSiteId()` fails closed when tenant identity is absent.
- `snapshot()` yields `['site_id' => int]`, the only permitted async payload.
- `clear()` is mandatory at the start and end of request/job lifecycles.

It does not represent a reseller or platform administrator. Those identities remain separate concerns.
