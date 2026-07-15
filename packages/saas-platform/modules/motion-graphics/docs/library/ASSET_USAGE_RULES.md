# Asset Usage Rules

## Purpose

Usage metadata drives discovery and recommendations. It does not grant publishing or tenant-authoring permission.

| Asset type | Primary usage | Secondary usage | Avoid |
|---|---|---|---|
| Motion | Hero, landing page, storytelling section | About, service narrative, CTA | Dense menus, small cards, blocking loaders |
| Icon | Feature cards, menus, service lists | Dashboard indicators, compact CTA | Full-page illustration without composition |
| Object | Product sections, ecommerce, decoration | Hero support, category cards | Background tiling unless explicitly designed |
| Visual Asset | Background, branding section, editorial composition | Hero, divider, product presentation | Functional controls without accessible labels |

## Selection rules

1. Match industry before style.
2. Match usage before visual preference.
3. Prefer `status=active` and a quality score appropriate to the surface.
4. Use the published immutable version; never resolve an authoring draft.
5. Check contrast after applying theme tokens.
6. Preserve `title`, `desc`, ARIA, viewBox, and reduced-motion behavior.
7. Do not infer a customer license from search visibility.

## Theme rendering

Live host CSS variables do not cross a plain `<img>` SVG document boundary. Use a sanitized inline/component renderer for live theme inheritance, or use compiled customer variants with fixed approved token fallbacks.

## Responsive usage

Preserve the SVG viewBox and aspect ratio. Crop only through an explicit composition wrapper. Motion should honor `prefers-reduced-motion`.

