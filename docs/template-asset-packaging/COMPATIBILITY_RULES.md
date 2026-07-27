# Compatibility Rules

Resolution intersects, never unions, the constraints of Master metadata, template reference, site profile, license, and runtime capabilities.

Mandatory dimensions:

- industry: exact/declared general compatibility; medical-only is rejected for construction;
- section: requested placement must be in the asset's allowed sections;
- style: at least one compatible style or an explicitly neutral asset;
- theme: required tokens and rendering mode must be supported;
- performance: asset and aggregate package must fit the selected budget;
- engine/runtime contract, direction/locale, accessibility/reduced motion, lifecycle/publication, and license.

Required incompatibility fails the package. Optional incompatibility omits the asset or tries its declared fallback. Fallbacks pass the same checks. Compatibility overrides cannot broaden a Master contract.

The playground implements deterministic checks for ownership, lifecycle, industry, section, style, theme tokens, and performance. Real rendering/browser measurements remain a future integration gate.

