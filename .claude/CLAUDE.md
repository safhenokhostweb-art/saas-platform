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
- customer-site overlay registry (host wins, drifted): `customer-site/app/Services/ThemeCanvas/BlockRegistry.php`
- Platform editor: `resources/views/admin/theme-builder/canvas.blade.php`
- Duplicated block CSS (drifted): platform `admin/theme-builder/partials/canvas-css-contract.blade.php` and customer `partials/theme-canvas-style-contract.blade.php`. The latter is mirrored byte-exact into `tests/Support/FoodMart/FoodMartPhase2bStyleContract.php`.
- Audit, defect list, and phased roadmap: `docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md`

## Local verification

- PHP: `C:/laragon/bin/php/php-8.4.16-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit …`
- Browser: `PLAYWRIGHT_CHROME_PATH=C:/Users/Farshad-IT/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe npx playwright test …` against the dev server on `127.0.0.1:8000`. Canvas specs must clean up the blocks they insert into the shared E2E fixture theme.
- JS: `node --test tests/js/*.mjs`
