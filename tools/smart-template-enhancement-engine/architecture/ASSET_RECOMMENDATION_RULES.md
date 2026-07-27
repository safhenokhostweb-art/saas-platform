# Asset Recommendation Rules

## Eligibility gate

An asset is excluded unless all conditions hold:

- status is `active`;
- role is `master`;
- ownership type is `platform`;
- average technical, visual, reusability, and AI-readiness score is at least 70.

Visibility is not a license grant. A future integration must separately check licensing and published-version access.

## Matching order

1. Match industry and configured aliases.
2. Match the section's intended usage.
3. Prefer the section's asset-type order.
4. Apply industry/section preference terms.
5. Match style and audience.
6. Add normalized quality evidence.
7. Add a small boost for membership in a canonical relation bundle.

The exact weights and terms live in `rules/asset-matching.json`. The engine requires either a direct industry match or membership in a matching canonical relation bundle, plus a valid section asset type. This prevents visually attractive but irrelevant results while honoring curated bundles.

## Section roles

| Section | Primary asset order | Intent |
|---|---|---|
| Hero | motion, visual asset, object | Story, identity, attention |
| Features | icon, object | Scannability and capability |
| Services | icon, object, visual asset | Service recognition |
| About | visual asset, motion, object | Identity and credibility |
| Products | object, visual asset, icon | Product/category focus |
| Testimonials | icon, visual asset | Trust and customer proof |
| CTA | motion, icon, visual asset | Action and trust |

## Output guarantees

Each recommendation includes canonical ID, immutable version, path, match score, quality score, reasons, theme readiness, and ownership. It never includes SVG source content or editing metadata.
