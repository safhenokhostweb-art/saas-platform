# Brand Variant Versioning

Status: **Architecture only.**

## Immutability

Master versions, variant versions, AI proposals, approved manifests, and published packages are immutable. Never edit an existing version in place.

Each generated version must contain at least:

```json
{
  "source_asset_id": "global-asset-id",
  "source_asset_version": "master-version",
  "customer_id": "customer-or-site-id",
  "theme_palette": {},
  "AI_prompt": "private prompt reference or protected content",
  "AI_provider": "normalized provider identifier",
  "generated_date": "RFC-3339 timestamp",
  "version": 1
}
```

Storage may use canonical snake_case names, but exported private authoring manifests must preserve the required meaning. Prompts are private authoring data and must not appear in runtime packages.

## Version sequence

1. Platform Admin creates an adaptation request.
2. The system freezes input identities and checksums.
3. AI returns a proposal.
4. Validation creates an immutable draft version.
5. Platform Admin compares and approves the draft.
6. Compilation creates immutable optimized artifacts.
7. Publication advances the published pointer.

Working, approved, and published pointers remain separate. A new draft does not change customer runtime behavior.

## Rollback and restore

Rollback never moves history backward and never overwrites a package. It creates a new draft whose content is copied from a selected historical version, records that version as the restore source, and proceeds through validation and approval.

## Version comparison

Comparison must cover:

- source master identity and version;
- normalized theme tokens;
- SVG structure and accessibility;
- changed attributes, styles, timing, and motion behavior;
- file size and dependency delta;
- visual preview delta;
- security scan result; and
- provider, prompt revision, model/pipeline revision, and validation profile.

## Publication identity

A published package path must be immutable and versioned, for example:

```text
customer-assets/{customer-id}/{variant-slug}/v3/
```

Mutable aliases may point to a published version, but cached assets must resolve to content-addressed or versioned files.

