# Smart Template Enhancement Architecture

## Purpose

The prototype converts a generic section outline into a business-specific visual recommendation plan. It is a preparation layer, not a renderer, builder, publisher, or tenant runtime.

```text
Business Profile + Template Sections
                |
          Input Validation
                |
         Section Analysis
                |
    Deterministic Asset Matching
                |
 Quality Score + Brand Theme Handoff
                |
      Platform Admin Review (future)
```

## Decisions

1. **Canonical references, no duplication.** Recommendations contain stable IDs and canonical paths from `library-index.json`.
2. **Rules before AI.** Deterministic rules establish an auditable baseline. A future AI provider may enrich search intent but cannot bypass eligibility filters.
3. **Industry first.** Industry relevance has the largest weight, then section usage, suitable asset type, style, audience, and quality.
4. **Safe eligibility.** Only `active`, `master`, `platform` assets scoring at least 70 are considered.
5. **Advisory score.** The enhancement score explains likely improvement; it is not a publication or visual-review decision.
6. **Immutable assets.** Recommendation and theme preview never mutate a Master Asset or create a Variant Asset.

## Components

- Business profile validator: checks supported industries, style, audience, required sections, and safe hex theme tokens.
- Section analyzer: maps section purpose and missing visual roles.
- Matcher: ranks canonical library assets using configuration in `rules/asset-matching.json`.
- Score calculator: compares generic and enhanced coverage across five metrics.
- Brand handoff: maps approved brand token names to asset token names without compiling a variant.
- AI placeholder: exposes a future contract while explicitly disabling provider calls and publishing.

## Non-goals

No HTML generation, template mutation, database persistence, customer ownership, licensing decision, network call, live AI, asset rendering, variant compilation, approval, or publishing is implemented.
