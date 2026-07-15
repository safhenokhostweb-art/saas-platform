# Duplicate and Similarity Analysis

Date: 2026-07-14  
Scope: all 601 governed asset packages.

## Method

Primary SVG files were normalized by removing titles, descriptions, accessibility identifiers, metadata identifiers, and style declarations. SHA-256 signatures were then compared inside each asset family. This detects exact structural duplication while preserving geometry differences.

Visual similarity was reviewed by design archetype, category, slug reuse, and version lineage.

## Exact duplicates

**No exact normalized structural duplicates were found.**

No asset should be deleted based on the current evidence.

## Intentional relationships that are not duplicates

- Motion `v1` and `v2` packages are immutable version lineage.
- Repeated slugs such as `ambulance`, `tractor`, `calculator`, `chair`, `bed`, and `teapot` occur in different contextual categories.
- Icon, object, visual, and motion representations of one concept serve different rendering roles.

## Visually similar groups

| Group | Scope | Finding | Recommendation |
|---|---|---|---|
| Tool archetypes | Construction, office, kitchen, medical | Shared premium proportions with different semantic marks | Retain packages; consider dedicated silhouettes for highest-traffic assets |
| Clothing | 30 clothing objects | Consistent garment frame and identity weave | Retain; commission product-specific tailoring details in a future art phase |
| Iranian traditional | 30 objects | Shared ornamental language | Retain; prioritize samovar, carpet, lantern, window, and copperware for bespoke redraw |
| Containers and vessels | Kitchen, food, restaurant, beauty | Similar base vessel geometry | Keep contextual ownership and tags; avoid cross-category deletion |
| Transport | Transportation, tourism, medical, agriculture | Shared vehicle perspective | Keep; specialize wheels, cabins, and body shapes when marketplace analytics justify it |
| Motion versions | 10 versioned motion families | Related scenes by design | Treat as lineage, never consolidate by deletion |

## Consolidation policy

- Do not merge customer-facing identities merely because geometry is related.
- Shared primitives may be consolidated only in a future authoring/build system; published packages remain immutable.
- Consolidation must preserve stable IDs, slugs, ownership, version history, accessibility, and index redirects.
- A candidate requires visual review, usage analytics, and Platform Admin approval.

