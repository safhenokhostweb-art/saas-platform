# Scanner Examples

`test-cases/` contains five isolated miniature asset libraries used by `../tests/run-tests.js`.

Each intentionally failing case demonstrates one required detection without modifying the production library:

- `valid-asset`: complete themeable and accessible icon package;
- `missing-metadata`: package without `metadata.json`;
- `invalid-svg`: mismatched XML tags;
- `hardcoded-color`: fixed `fill="#ff0000"` outside theme tokens; and
- `broken-index`: index path referencing a missing file.

The real generated sample report is available in `../reports/latest-report.json` and `../reports/latest-report.md`.
