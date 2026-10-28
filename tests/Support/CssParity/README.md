# CSS parity harness

A visual-regression guard for the shared block stylesheet (ADR-063). It
renders a markup corpus through a host's real block renderer, applies two
stylesheets to it in real Chromium, and diffs the **full computed style** of
every element and pseudo-element at 1366, 768 and 390 px. It is a manual
tool: a full run takes about 20 minutes per host.

Run it before and after any change to:

- `theme-runtime-blocks.css` or `theme-runtime-contract.css`;
- a host style partial;
- a renderer's markup or inline styles;
- the theme tokens.

## Workflow

All commands run from the saas-platform root. PHP is
`C:/laragon/bin/php/php-8.4.16-nts-Win32-vs17-x64/php.exe`.

1. **Capture the baseline before changing anything.** Customer-site must be
   re-mirrored first if the package changed:

   ```bash
   php tests/Support/CssParity/corpus.php ../customer-site customer before-corpus.json
   php tests/Support/CssParity/css.php    ../customer-site customer before.css
   ```

2. **Make the change.** Then re-mirror customer-site
   (`composer reinstall saas-platform/modules-theme-runtime`) and run
   `php artisan view:clear`.

3. **Render the after state the same way.** Render the corpus again too if
   renderer code changed.

4. **Compare and summarise:**

   ```bash
   PLAYWRIGHT_CHROME_PATH=… node tests/Support/CssParity/compare.cjs \
     --corpus before-corpus.json [--corpus-after after-corpus.json] \
     --before before.css --after after.css --report report.json
   php tests/Support/CssParity/summarize.php report.json
   ```

Every diff in the summary must be explained by the change. Comparing a
stylesheet with itself reports zero diffs; run that once as a self-test after
editing the harness.

## Token modes (`css.php --tokens=`)

| Mode | Meaning |
|---|---|
| `default` | The token block for empty `global_styles`: a theme with no palette. |
| `none` | No token block at all. Every `var()` fallback must reproduce the old literal. |
| `<file.json>` | A `global_styles` object, e.g. `{"primary_color":"#e11d48"}`. It shows that the brand colour actually reaches each block. |

## Comparisons that prove something

- **Live unchanged:** customer corpus; before CSS vs after CSS.
- **Preview equals live:** platform corpus; customer CSS vs platform CSS. The
  only expected difference is the host-only skill-bar rule and the
  `--platform-primary` variable.

## Known traps

- One browser context per page. Many pages in one context stall
  `setContent`.
- Another session running PHPUnit on the same database corrupts that run, not
  this harness. Still, run the harness on a quiet machine: under load the dev
  server and Chromium both slow down.
- **Preview vs live is slow** when the platform CSS lacks the customer
  partial's `:root { --platform-primary: var(--tb-primary); }`. That one
  variable then differs on every element, so every document gets a full
  detail pass (hours). Append that rule to the platform CSS file before
  comparing; the remaining diffs are the real ones.
- A run whose log stops advancing has stalled: stop the node process tree
  and rerun.
- Colours are normalised (`rgb()`, `color(srgb …)` and `color-mix()` results
  compare as rgba). Sizes are not, so a font or digit change shows up as
  derived width and height changes. `summarize.php` folds those.
