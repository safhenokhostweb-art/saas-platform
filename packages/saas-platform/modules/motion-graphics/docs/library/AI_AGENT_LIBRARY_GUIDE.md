# AI Agent Library Guide

## Mandatory context

Before changing this library, read repository governance, module AI governance, Brand Adaptation documents, this folder, and the current library indexes. Repository evidence overrides conversational assumptions.

## Structure

```text
assets/library/
├── <motion-slug>/<version>/
├── icons/<category>/<slug>/
├── visual-assets/<category>/<slug>/
├── visual-assets/object-library/<category>/<slug>/
├── library-index.json
├── tags.json
└── asset-relations.json
```

Each package contains a primary SVG, preview, metadata, AI instructions, and README. Motion uses `animation.svg`; icons use `icon.svg`; visual/object packages use `asset.svg`.

## Creating an asset

1. Choose the correct family and canonical lowercase kebab-case category.
2. Choose a stable lowercase kebab-case slug.
3. For motion, create a new immutable version directory.
4. Include all five package files.
5. Use semantic theme variables and no direct shape colors.
6. Add accessibility and reduced-motion behavior where applicable.
7. Complete ownership, search, quality, AI, master/variant, and usage metadata.
8. Use standardized tags plus specific custom tags.
9. Validate before adding the asset to indexes and relationships.
10. Rebuild indexes from metadata; never hand-edit only one side of a reference.

## Stable IDs

```text
<type>:<category>:<slug>:<version>
```

Stable IDs do not change when display names or tags change. Category moves require an explicit migration/redirect decision because category is part of the identifier.

## Metadata updates

Preserve useful existing fields. Search fields include Persian name, English name, keywords, synonyms, category, industry, usage, and tags. Ownership and quality changes require evidence and audit history in a future implementation.

## Variants

Never modify a master to create customer branding. A variant records source master ID/version, customer/site ownership, palette, provider/prompt provenance, generation date, and its own version. The AI stops at a validated draft until Platform Admin approval.

## Version rules

Published and historical files are immutable. Rollback creates a new version copied from history. Never rename or collapse motion version directories.

## Prohibited actions

- tenant authoring;
- direct publication by AI;
- source/private metadata exposure;
- cross-customer reuse without a new ownership manifest;
- unsafe SVG content or external dependencies;
- broken index paths;
- unreviewed category moves;
- deleting duplicates without consolidation evidence; and
- Laravel, database, runtime, or integration changes during an asset-governance task.

