# Motion Production Readiness Checker

A dependency-free, read-only Node.js checker for production candidate manifests. It validates lifecycle, authorization, ownership, licenses, SVG safety/accessibility, motion references, reduced-motion declarations, approvals, and performance budgets.

It does not sanitize, compile, fix, publish, deploy, mutate assets, call AI, or connect to Laravel/database/runtime systems. A passing report is governance evidence, not a replacement for server-side authorization, a production-grade parser/sanitizer, visual QA, or browser profiling.

## Requirements and use

Node.js 18 or newer:

```text
node checker --input tests/fixtures/valid-production-package.json
npm test
```

Options: `--output <directory>`, `--strict` (warnings fail), and `--no-write`. By default the checker writes `reports/latest-report.json` and `reports/latest-report.md`; it never writes to the input or asset library.

## Manifest boundary

A production candidate declares:

- `contract_version`;
- asset identity, SHA-256 checksum, role, ownership, metadata and theme readiness;
- version transition, append-only/rollback intent, and approval;
- actor/action authorization context;
- immutable license snapshot;
- timeline, references, accessibility, and optional local SVG path;
- safe runtime projection; and
- measured or preflight performance metrics.

The checker uses provider-neutral rules in `rules/`. The future adapter must produce the manifest from authoritative records; tenant-supplied claims are never trusted.

## Exit status

- `0`: no errors (warnings allowed unless `--strict`)
- `1`: one or more errors, or warnings in strict mode

## Safety

SVG paths are confined to the manifest directory. External references and active SVG content are rejected by pattern checks. These checks intentionally fail closed, but production integration must use a hardened XML/SVG parser with DTD/network disabled and an allowlist sanitizer.

