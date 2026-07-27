# Template Enhancement Simulation Lab

A standalone Persian RTL Before/After environment that demonstrates how a generic template can be specialized using the existing Motion Graphics Asset Library, Smart Template Enhancement Engine, Placement Contract, Brand Theme contract, and scoring rules.

## Safety boundary

- Reads canonical contracts and assets only.
- Does not modify or copy Master Assets.
- Does not call AI providers or APIs.
- Does not contain Laravel, database, tenant, authentication, publishing, or runtime integration.
- Browser theme rendering is an in-memory token simulation, not a generated Variant Asset.

## Run

```bash
npm run build --prefix tools/template-enhancement-simulation-lab
npm test --prefix tools/template-enhancement-simulation-lab
npm run report --prefix tools/template-enhancement-simulation-lab
npm run serve --prefix tools/template-enhancement-simulation-lab
```

Open `http://127.0.0.1:4174/tools/template-enhancement-simulation-lab/`.

## Data flow

Scenario JSON → existing `enhance()` → eligibility filter → existing Placement Validator → Before/After simulation JSON → read-only browser visualization.

Generated files under `data/`, `examples/`, and `reports/` contain asset references only. SVG source is fetched read-only for preview and sanitized before inline display.
