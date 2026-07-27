# Asset Placement Schema

The schema describes placement capability without modifying Master Asset metadata. A future resolver composes canonical asset metadata with `asset-type-placement-defaults.json`, then may narrow rules for a specific asset version.

Required dimensions:

| Field | Meaning |
|---|---|
| `allowed_sections` | Hard placement allowlist. |
| `recommended_sections` | Preferred subset of allowed sections. |
| `placement_priority` | Relative 1–100 ranking, never an authorization decision. |
| `size_rules` | Preferred scale, safe bounds, aspect-ratio mode. |
| `responsive_rules` | Display, width, and alignment for desktop/tablet/mobile. |
| `animation_behavior` | Intensity, triggering, loop, and reduced-motion fallback. |
| `alignment_rules` | Logical RTL-aware alignments. |
| `spacing_rules` | Bounded logical block/inline spacing per breakpoint. |
| `theme_requirements` | Theme tokens, transparency, and contrast requirement. |
| `industry_usage` | Supported business contexts. |
| `audience_usage` | Supported audience contexts. |

`recommended_sections` must be a subset of `allowed_sections`; minimum width cannot exceed maximum width; motion requires an explicit reduced-motion policy; widths must remain between 10% and 100%; and theme-ready placement must use only canonical `--brand-*` tokens.

Defaults can be narrowed by an immutable asset-version contract. Broadening allowed sections or disabling accessibility/theme safeguards requires a new reviewed contract version.
