# Variant Generation Rules

Status: **Architecture only.**

## Definition

A Variant is a customer/site-owned, immutable derivative of one exact platform Master Asset version. It retains master lineage but never replaces, patches, or becomes a child directory inside the Master Asset package.

## Path model

Conceptual example:

```text
Master:
assets/library/icons/business/company/

Generated private variant:
customer-assets/{site-id}/icons/company/v2/
```

The path is illustrative. A future storage adapter determines physical storage, but it must use normalized immutable versioned paths and must reject traversal. Customer variants must not be written under `assets/library/`.

## Required private metadata

```json
{
  "variant_id": "variant_opaque_id",
  "source_asset": "icon:business:company:v1",
  "source_version": "v1",
  "customer_id": "customer-opaque-id",
  "theme": {
    "primary": "#0F766E",
    "secondary": "#134E4A",
    "accent": "#F59E0B",
    "background": "#FFFFFF",
    "border": "#CBD5E1"
  },
  "ai_provider": "fake",
  "prompt_history": "history_opaque_id",
  "created_at": "2026-07-14T12:00:00+03:30",
  "version": 2
}
```

The complete contract is `schemas/ASSET_VARIANT.schema.json`.

## Creation prerequisites

- source resolves to an approved platform Master Asset and exact immutable version;
- source checksum still matches the frozen session;
- customer/site and Platform Admin authority are valid;
- proposal passed every required validator;
- exact proposal checksum was explicitly approved;
- prompt/history evidence is ready for append-only persistence;
- generated artifacts are sanitized, deterministic where practical, and checksum-addressed; and
- version number is allocated without overwriting an existing version.

## Version and lineage

- First variant version starts a new customer/site lineage from the exact master version.
- A later edit creates the next variant version and records `parent_variant_version`.
- Rebase to a newer master is explicit and creates another variant version.
- Rollback/restore copies an old approved variant into a new version; pointers never move backward by rewriting history.
- A rejected proposal consumes no variant version but retains its proposal/history identity.
- Publication pointer and latest approved version are independent.

## Structural preservation

For SVG, preserve the root `viewBox`, editable element identifiers, accessibility semantics, internal references, declared renderer behavior, reduced-motion behavior, and safe dependency-free structure. For other engines, apply the engine-specific equivalent. An unsupported or unvalidated structural change rejects the proposal.

## Ownership and reuse

The variant is owned by the designated customer/site but remains authorable only by Platform Admin. Similar palette or style does not permit cross-customer access. Reuse follows `../brand-adaptation/CUSTOMER_OWNERSHIP_RULES.md`: deduplicated bytes need separate ownership manifests; a reusable generic result must first become an approved platform template; an authorized clone creates a new lineage.

## Runtime projection

Future runtime packages may receive only approved compiled assets, safe display identity, version, integrity, theme values allowed by deployment policy, and publication time. They never receive the source working snapshot, editable schema, prompt/history, provider payload, logo analysis, AI metadata, proposal, or private preview.
