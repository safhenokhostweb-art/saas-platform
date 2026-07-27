# Template Manifest Contract

`template-assets.json` is a provider-neutral, versioned reference contract:

```json
{
  "contract_version": "1.0.0",
  "template_id": "construction-pro",
  "template_version": 2,
  "industry": ["construction"],
  "style": ["corporate", "premium"],
  "theme": {"required_tokens": ["--brand-primary", "--brand-accent"]},
  "assets": [
    {
      "type": "motion",
      "asset_id": "motion.company-introduction",
      "version": 2,
      "required": true,
      "sections": ["hero"],
      "fallback_asset_id": "motion.company-introduction-static",
      "compatibility": {"industries": ["construction", "company"]}
    }
  ]
}
```

Required assets fail resolution when unavailable or incompatible. Optional assets may be omitted. A fallback is used only when explicitly declared, published, license-compatible, section-compatible, no more resource-intensive than the original limit, and valid for the same site.

Every reference pins type, canonical ID, exact version, required flag, allowed sections, and optional fallback. The manifest also declares supported module/runtime contract versions, theme tokens, locale/direction, performance profile, and a checksum over canonicalized content.

Templates may narrow Master compatibility but cannot widen Master sections, industries, editable properties, licenses, or security capabilities.

