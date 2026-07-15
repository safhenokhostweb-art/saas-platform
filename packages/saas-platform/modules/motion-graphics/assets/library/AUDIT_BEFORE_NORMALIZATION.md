# Motion Graphics Asset Library — Pre-Normalization Audit

Audit status: **Completed before asset modification**

Date: 2026-07-14  
Scope: `packages/saas-platform/modules/motion-graphics/assets/library`

## Executive summary

A read-only inspection reviewed every discovered asset package across motion graphics, icons, visual assets, and the object library.

| Family | Packages/versions reviewed | Metadata incomplete | Missing semantic theme API | Missing AI instructions | Missing accessibility |
|---|---:|---:|---:|---:|---:|
| Motion Graphics | 22 | 22 | 22 | 0 | 22 |
| Icons | 80 | 80 | 80 | 0 | 0 |
| Visual Assets | 49 | 49 | 49 | 0 | 0 |
| Object Library | 450 | 450 | 0 | 0 | 0 |
| **Total** | **601** | **601** | **151** | **0** | **22** |

All 601 packages contain their expected primary SVG, preview, README, metadata, and AI editing instruction file. No missing primary SVG was found.

## Audit checks

The audit checked:

1. family and category placement;
2. slug and naming consistency;
3. required metadata fields and required usage/editable values;
4. semantic theme token support;
5. AI editing instruction presence;
6. expected package files;
7. SVG accessibility markers;
8. SVG XML and reuse readiness; and
9. normalized structural similarity.

## Metadata findings

Every existing `metadata.json` is missing at least one part of the requested normalized contract. Common gaps are:

- `type` on motion assets;
- Boolean `theme_support`;
- required usage values `hero`, `section`, `card`, and `background`;
- required editable values `color`, `theme`, `size`, and `style`;
- `asset_role: master`;
- `variant_supported: true`; and
- `source_asset: null`.

Useful existing metadata must be preserved. Detailed theme capability currently stored as an object will be retained under `theme_tokens` when `theme_support` becomes the required Boolean.

## Theme findings

The required semantic API is:

- `--asset-primary`;
- `--asset-secondary`;
- `--asset-accent`;
- `--asset-background`;
- `--asset-border`; and
- `currentColor`.

The complete API is already present in 450 object assets. It is absent from 22 motion versions, 80 icons, and 49 visual assets.

Direct shape-level palette values were found in one motion asset and all 49 legacy visual assets. Icons and objects keep their literals in fallback variable declarations rather than shape attributes. Normalization must remove direct shape colors and map legacy token names to the semantic API.

## Accessibility findings

All icons, visual assets, and objects contain `title`, `desc`, `aria-label`, and `role="img"`.

All 22 motion versions fail at least one required accessibility marker. Their SVG structure must be upgraded without changing timing, version directories, or animation behavior.

## Category findings

- Icon and object category placement is contextually valid. Repeated concepts such as ambulance, tractor, calculator, chair, and bed are legitimate category-specific variants and should not be moved solely because their slug appears elsewhere.
- Legacy visual categories are generally usage-oriented and valid. `desk-objects` is a category anomaly under `iranian-objects` and is a candidate for relocation to `business-objects`, with index paths updated and slug preserved.
- Motion category naming is inconsistent: Title Case, spaces, and format labels are mixed. `Hero Animation` describes placement rather than domain. Motion metadata categories should be normalized to stable lowercase slugs while version directories and asset slugs remain unchanged.
- No customer variant exists in scope; every current package is a master candidate.

## Similarity and quality findings

Structural normalization found **30 similarity clusters containing 437 object packages**. The largest clusters contain 47, 40, 36, 32, and 30 assets.

High-risk examples:

- one generic tool silhouette is reused across unrelated medical, office, kitchen, construction, restaurant, and transportation concepts;
- all 30 clothing objects share one body silhouette;
- all 30 Iranian Traditional objects share one vessel-like silhouette;
- large groups of food, transportation, education, furniture, beauty, and agricultural assets share category archetypes rather than object-specific forms.

These packages pass structural validation but do not meet marketplace-quality semantic identity. Normalization must add object-specific visual identity and must not rely on random circles, repeated SaaS nodes, or decorative differences alone.

## Planned normalization

1. Preserve all master slugs and version directories.
2. Normalize metadata without removing useful existing fields.
3. Add the semantic Theme API to every SVG and remove direct shape palette values.
4. Preserve or add accessibility attributes.
5. Standardize AI editing instructions.
6. Add master/variant preparation metadata.
7. Correct the confirmed `desk-objects` category anomaly and all affected indexes.
8. Normalize motion category values in metadata only.
9. improve structurally repeated object assets with deterministic object-specific visual markers and category semantics.
10. Rebuild and validate all three central indexes.

## Baseline counts for final comparison

- Assets/versions reviewed: 601
- Metadata packages requiring normalization: 601
- Packages requiring Theme API upgrade: 151
- Packages missing AI instruction files: 0
- Motion versions requiring accessibility upgrade: 22
- Confirmed category moves planned: 1
- Similarity groups requiring quality treatment: 30
- Similar packages in those groups: 437

