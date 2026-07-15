# Asset Inheritance

Status: **Architecture only.**

## Inheritance rule

A customer variant is derived from one exact immutable version of a global master. It is not a copy that can replace or mutate the master.

```text
global asset / master version
  └── customer variant / version 1
        ├── customer variant / version 2
        └── restored variant / new version 3
```

Every variant lineage must retain:

| Field | Meaning |
|---|---|
| `source_asset_id` | Stable identity of the global master. |
| `source_asset_version` | Exact immutable master version used for generation. |
| `customer_id` | Authorized customer/site ownership boundary. |
| `variant_id` | Stable identity of the customer-specific variant. |
| `parent_variant_version` | Previous customer variant version, when present. |
| `generation_fingerprint` | Non-secret hash of normalized generation inputs and pipeline versions. |

The user-required `source_asset_id` and `customer_id` are mandatory. `source_asset_version` is additionally required to make derivation reproducible and prevent a moving-master ambiguity.

## Supported source classes

- icons;
- objects;
- illustrations; and
- motion graphics.

Only an approved global master version may be selected. Draft, deleted, quarantined, private-source, or customer-owned assets cannot become a global source without an explicit Platform Admin workflow.

## Structural preservation

Adaptation may change only properties allowed by the master editable schema. For SVG assets, the validator must preserve:

- root `viewBox`;
- semantic groups and stable editable identifiers;
- `title`, `desc`, ARIA relationships, and reading behavior;
- safe internal references;
- renderer compatibility;
- required motion timing/structure when the source is animated; and
- the absence of scripts, external URLs, unsafe event handlers, and unauthorized dependencies.

A failed structural comparison rejects the proposal. It never falls back to overwriting the master.

## Upstream master changes

Publishing a new master version never silently rewrites customer variants. Platform Admin may explicitly request a rebase:

1. choose the new master version;
2. copy the last approved customer theme intent;
3. generate a new customer draft;
4. compare structure and appearance;
5. approve as a new variant version.

The previous customer version remains published until the new draft is approved.

