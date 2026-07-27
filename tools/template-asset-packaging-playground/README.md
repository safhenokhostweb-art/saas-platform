# Template Asset Packaging Playground

A static, dependency-free prototype for the Template Builder ↔ Motion Graphics packaging contract. It selects fixture templates, resolves canonical assets and fallbacks, validates ownership/compatibility, applies an in-memory customer brand intent, displays lineage, and generates a downloadable **simulation manifest**.

It has no backend, persistence, upload, AI call, Laravel/SaaS connection, deployment, or runtime activation. It does not copy or modify the real Asset Library. Fixture IDs model future canonical references.

## Run

Serve the repository with any static web server and open `index.html`. Tests require Node.js 18+:

```text
npm test
```

The test suite covers template resolution, ownership validation, customer isolation, lineage, compatibility, package generation, invalid references/fallback, and verifies the existing Asset Library tree hash does not change.

## Prototype safety

The generated JSON is intentionally marked `simulation_only`. Its fingerprint is a deterministic demo identifier, not a cryptographic signature. A future platform adapter must rebuild every claim from authoritative records, use SHA-256/signatures, enforce authorization and licenses server-side, and stage before activation.

