import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { runAudit } from '../scanner';

const here = path.dirname(fileURLToPath(import.meta.url));
const casesRoot = path.resolve(here, '../examples/test-cases');

const cases = [
  {
    name: 'valid asset',
    directory: 'valid-asset',
    requiredIndexes: [],
    assert(report) {
      return report.total_assets === 1 && report.errors === 0 && report.valid_assets === 1;
    },
  },
  {
    name: 'missing metadata',
    directory: 'missing-metadata',
    requiredIndexes: [],
    expectedCode: 'METADATA_MISSING',
  },
  {
    name: 'invalid SVG',
    directory: 'invalid-svg',
    requiredIndexes: [],
    expectedCode: 'SVG_INVALID_XML',
  },
  {
    name: 'hardcoded color',
    directory: 'hardcoded-color',
    requiredIndexes: [],
    expectedCode: 'THEME_HARDCODED_COLOR',
  },
  {
    name: 'broken index',
    directory: 'broken-index',
    requiredIndexes: [
      {
        name: 'fixture-index',
        path: 'library-index.json',
        base: '.',
        entries_property: 'assets',
        count_property: 'total_assets',
        required: true,
        canonical: true
      }
    ],
    expectedCode: 'INDEX_BROKEN_PATH',
  },
];

let failures = 0;
for (const testCase of cases) {
  const report = runAudit({
    libraryRoot: path.join(casesRoot, testCase.directory, 'library'),
    requiredIndexes: testCase.requiredIndexes,
    writeReports: false,
  });
  const passed = testCase.assert
    ? testCase.assert(report)
    : report.issue_list.some((issue) => issue.code === testCase.expectedCode);
  process.stdout.write(`${passed ? 'PASS' : 'FAIL'}  ${testCase.name}\n`);
  if (!passed) {
    failures += 1;
    process.stdout.write(`      issues: ${report.issue_list.map((issue) => issue.code).join(', ')}\n`);
  }
}

process.stdout.write(`\n${cases.length - failures}/${cases.length} test cases passed.\n`);
process.exitCode = failures === 0 ? 0 : 1;
