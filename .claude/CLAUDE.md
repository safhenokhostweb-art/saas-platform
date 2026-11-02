# Claude Code — Widget-Engine Notes

Root bootstrap remains `CLAUDE.md` → `AGENTS.md` → `PROJECT_CONSTITUTION.md`.
This file only adds pointers; it does not override the constitution or ADRs.

## Quality standard

The owner's Commercial Iranian UI/UX Standard is permanent and lives in
`PROJECT_CONSTITUTION.md` §16 (ADR-060). Apply its minimum requirements as
acceptance gates on every widget, header, footer, section, and builder change.

## Widget-engine map (verified 2026-10-25)

- Canonical registry/renderer: `packages/saas-platform/modules/theme-runtime/src/{BlockRegistry,BlockRenderer}.php`
- Repeater contract: `type: 'repeater'` + `fields` + `storage` (ADR-060). `item_schema` is not read by anything canonical.
- Schema contract + manifest for every definition: `theme-runtime/src/Widget/WidgetSchemaContract.php` (ADR-076; `ui: segmented`, `group`, `slots`). Social/messenger list: `Support/SocialNetworks.php`. Page-tab strip: `theme-runtime::canvas.tab-strip`.
- customer-site registry = canonical + `HOST_PATCHES` (ADR-062, no local copies): `customer-site/app/Services/ThemeCanvas/BlockRegistry.php`
- Platform editor: `resources/views/admin/theme-builder/canvas.blade.php`
- Block CSS: one shared sheet, `packages/.../theme-runtime/resources/css/theme-runtime-blocks.css` (ADR-063), included by both host partials (platform `admin/theme-builder/partials/canvas-css-contract.blade.php`, customer `partials/theme-canvas-style-contract.blade.php`), which keep only host chrome. The customer partial is mirrored into `tests/Support/FoodMart/FoodMartPhase2bStyleContract.php` (three markers).
- Audit, defect list, and phased roadmap: `docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md`

## Workspace runtime agents (added 2026-10-26)

Canonical spec, catalog, and finding codes: `../agents/README.md` (workspace
root, beside `deployment-system/`). Do not restate it here or elsewhere.

Invocation (from this repo; PHP path below):

- `php ../agents/bin/agent run deployment.readiness --strict` before P5 of
  `docs/quality/WORK_UNIT_DELIVERY_WORKFLOW.md`, and after any change under
  `packages/saas-platform/**` (customer-site mirrors those packages).
- `--payload='{"projects":["saas-platform"],"task_id":"<WU id>"}'` scopes
  release drafts only; dependency monitoring always covers both projects.
- `php ../agents/tests/run.php` after any change in `../agents/`.

Coordination rules:

- A `blocker` finding stops the release; record the artifact path and digest
  in the delivery report. `MIRROR_DRIFT` means customer-site test results are
  not evidence for the current package code until it is re-mirrored.
- `release-task.<project>.json` drafts are proposals. Narrow `owned_paths` to
  the work unit and install only in the isolated release worktree, never in
  this dev tree. `release-prepare check` remains the gate.
- Agents talk only through Task contracts and bus topics registered in
  `../agents/agents.json`, never by calling each other. New agents follow
  the README's "Adding an agent" section.
- Both repos have the `pre-push` readiness gate (`../agents/bin/install-hooks`).
  It refuses pushes on blockers. Never bypass it with `--no-verify` without
  owner approval; fix the finding instead.
- Agents are read-only toward the projects. Granting any agent the ability to
  write project files, commit, push, or deploy is an owner decision, never a
  registry edit.

## Local verification

- PHP: `C:/laragon/bin/php/php-8.4.16-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit …`
- Browser: `PLAYWRIGHT_CHROME_PATH=C:/Users/Farshad-IT/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe npx playwright test …` against the dev server on `127.0.0.1:8000`. Canvas specs must clean up the blocks they insert into the shared E2E fixture theme.
- JS: `node --test tests/js/*.mjs`
