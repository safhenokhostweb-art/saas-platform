# Future Site Installation Flow

```text
Deployment Engine
  -> select authorized Template/version
  -> read and authenticate Template Manifest
  -> resolve exact Asset dependencies/fallbacks
  -> validate publication, ownership, license, compatibility and integrity
  -> compose approved Brand Adaptations/private assets
  -> create immutable candidate Site Asset Package
  -> run security, accessibility and performance checks
  -> Platform Admin approval
  -> stage and atomically activate Runtime pointer
  -> verify health and retain rollback package
```

The resolver must be deterministic for the same manifest, catalog snapshot, brand input, license context, and policy version. Every phase carries an idempotency key and audit correlation ID. Failure leaves the active package unchanged and preserves safe diagnostics.

Rollback resolves a prior verified package into a new candidate version, repeats authorization/validation, and advances the pointer only after approval. No code in this architecture performs deployment.

