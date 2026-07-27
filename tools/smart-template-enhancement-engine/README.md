# Smart Template Enhancement Engine

An independent, deterministic prototype that turns a generic template outline and a business profile into ranked recommendations from the existing Motion Graphics Asset Library.

## Safety boundary

- Reads the canonical library index, tag registry, and relation registry.
- Never edits, copies, publishes, or creates variants of Master Assets.
- Has no Laravel, database, tenant, network, or AI-provider integration.
- Recommendations are advisory and require a future Platform Admin approval workflow.

## Quick start

```bash
npm test --prefix tools/smart-template-enhancement-engine
npm run test:placement --prefix tools/smart-template-enhancement-engine
npm run examples --prefix tools/smart-template-enhancement-engine
npm run analyze --prefix tools/smart-template-enhancement-engine
```

Run one scenario:

```bash
node tools/smart-template-enhancement-engine/scripts/cli.js tools/smart-template-enhancement-engine/examples/medical-clinic.json
```

The CLI writes JSON to standard output only. It does not write to the asset library.

## Inputs and outputs

Input combines `business_profile` and `template_sections`. The engine validates the profile, analyzes missing visual roles, ranks compatible canonical assets, calculates before/after scores, and emits a brand-adaptation handoff using the five approved theme tokens.

See `architecture/` for contracts and `recommendations/` for generated example outputs.

The Template Builder placement layer is documented at `packages/saas-platform/modules/motion-graphics/docs/template-builder-contract/`. Its validator and fixtures remain dependency-free and standalone in this tool.
