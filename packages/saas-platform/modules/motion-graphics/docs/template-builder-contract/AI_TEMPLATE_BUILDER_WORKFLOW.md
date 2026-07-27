# AI Template Builder Workflow

AI integration is not implemented. This file defines a provider-neutral proposal exchange.

## Input

The provider receives only an allowlisted business profile and template structure:

- industry, audience, brand style, five colors, website goal;
- section name, available position, required visual type;
- eligible asset summaries supplied by the future platform adapter.

It must not receive private source definitions, editable schemas, AI metadata, tenant secrets, unpublished assets, or filesystem paths.

## Output

```json
{
  "contract_version": "1.0",
  "asset_id": "motion:medical:medical-professional-introduction:v2",
  "reason": ["medical industry", "hero storytelling", "theme ready"],
  "placement": { "section": "hero", "position": "trailing", "alignment": "inline-end", "priority": 92 },
  "size": "large",
  "theme_variant": "brand-proposal",
  "responsive_behavior": { "desktop": "show", "tablet": "show", "mobile": "static-preview", "reduced_motion": "static-preview" }
}
```

## Mandatory future pipeline

Validate input → resolve eligible published assets → call provider adapter → parse untrusted proposal → validate placement schema → enforce section/industry/theme rules → verify authorization and license → Platform Admin review → record approved template version.

AI cannot create ownership, approve, publish, broaden placement rules, or mutate Master Assets.
