# Motion SaaS Integration Validator

A dependency-free, read-only validator for the proposed SaaS ↔ Motion adapter contract. It checks required interfaces/operations, the authorization matrix, contract references, ownership lineage, Runtime Projection allowlisting, logical persistence/deployment completeness, and non-negotiable integration assumptions.

It does not inspect or modify Laravel, bind interfaces, query a database, create endpoints, deploy packages, activate runtime, or authenticate real actors. A passing fixture means the design manifest is internally consistent—not that production integration is secure or complete.

## Run

```text
node validator --input fixtures/valid-integration-contract.json
npm test
```

The validator writes JSON and Markdown evidence under `reports/`. Use `--no-write` for a console-only check. Inputs are never modified.

Rules are deliberately independent of providers and Laravel types. A future implementation must produce these decisions from canonical platform authorities rather than trusting a client-supplied manifest.

