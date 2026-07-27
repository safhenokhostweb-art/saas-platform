# Security Threat Model

## Trust boundaries

Untrusted inputs include uploaded SVG/font/audio, AI prompts and responses, tenant identifiers, theme values, template placement data, manifests, and asset references. Master storage, approval records, signing keys, provider credentials, and private metadata remain behind the future platform adapter.

## Threats and required controls

| Surface | Threats | Required controls |
|---|---|---|
| SVG | scripts, event handlers, `foreignObject`, external URLs, `javascript:`, unsafe CSS, embedded images/entities | strict XML parser; allowlist elements/attributes/protocols; reject active content; disable DTD/entities/network; sanitize then revalidate |
| AI | prompt leakage/injection, malicious output, metadata exposure, ownership/license changes | minimize provider payload; opaque IDs; schema and diff validation; treat output as hostile; no approval/publish capability; protected logs |
| Fonts | parser exploits, tracking/licensing risk, oversized payload | allowlisted formats/signatures; size/glyph limits; malware scan; licensing record; isolated conversion; no remote fonts |
| Audio | malformed codecs, metadata leakage, bombs, autoplay | allowlisted formats/signatures/duration/size; metadata stripping; isolated transcoding; no autoplay; asset-specific license |
| Upload | MIME spoofing, path traversal, archive bombs, overwrite | signature verification; random private names; canonical path enforcement; quotas; quarantine; immutable storage |
| Runtime | IDOR, tenant crossover, draft/source exposure, cache poisoning | tenant/site authorization; allowlisted published projection; scoped caches/URLs; integrity checks; CSP; fail closed |

## Data classification

Public runtime data is limited to published optimized artifacts and safe configuration. Master sources, `source_definition_json`, `editable_schema_json`, `ai_metadata_json`, prompts, logos, provider payloads, approval evidence, private previews, and security reports are confidential authoring data.

## Operational requirements before integration

The future implementation needs pinned sanitizers/parsers, dependency scanning, secrets management, rate limits, quotas, audit retention, incident response, backup/restore tests, CSP, security headers, and penetration testing. The standalone checker is defense-in-depth evidence, not a runtime security boundary.

