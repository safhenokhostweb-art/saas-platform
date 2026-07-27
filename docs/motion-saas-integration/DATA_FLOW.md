# Future Data Flow

```text
User creates Site
  -> selects authorized Template/version
  -> SaaS Deployment Engine freezes actor/site/license context
  -> Motion Graphics Adapter validates contract version
  -> Template Manifest resolves to exact published Asset versions/checksums
  -> candidate Site Asset Package is created
  -> approved Customer Brand Variant is applied
  -> Platform licensing is revalidated
  -> package security/quality/compatibility validation passes
  -> published-only Runtime Projection is generated
  -> Deployment Engine stages and activates Site
```

The adapter returns definitions and decisions; it does not deploy. Activation remains a Platform operation. Each step is idempotent, correlated, auditable, and bound to `site_id`. Failure preserves the prior active projection and returns a stable safe error.

AI output can enter only as a validated proposal through the existing approval workflow. It never enters runtime projection or activation directly.

