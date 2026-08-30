'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {
  classify,
  selectArtifactsForDeletion,
  selectPrCachesForDeletion,
  runArtifactCleanup,
  runPrCachePrune,
} = require('./storage-maintenance.js');

function artifact(id, name, createdAt, expired = false) {
  return { id, name, created_at: createdAt, expired };
}

test('newest artifacts in a release stream are protected', () => {
  const artifacts = [
    artifact(1, 'android-apks', '2026-01-01T00:00:00Z'),
    artifact(2, 'android-apks', '2026-01-05T00:00:00Z'),
    artifact(3, 'android-apks', '2026-01-10T00:00:00Z'),
  ];
  const { toKeep, toDelete } = selectArtifactsForDeletion({ artifacts });
  assert.deepEqual(toKeep.map((a) => a.id).sort(), [2, 3]);
  assert.deepEqual(toDelete.map((a) => a.id), [1]);
});

test('older artifacts beyond keep-N are selected for deletion even if unexpired', () => {
  const artifacts = Array.from({ length: 5 }, (_, i) =>
    artifact(i + 1, 'component-authority-report', `2026-01-0${i + 1}T00:00:00Z`)
  );
  const { toDelete } = selectArtifactsForDeletion({ artifacts });
  // keep newest 2 (ids 4, 5), delete the other 3
  assert.deepEqual(toDelete.map((a) => a.id).sort(), [1, 2, 3]);
});

test('unknown artifact names are always protected, never selected for deletion', () => {
  const artifacts = [
    artifact(1, 'totally-unrecognized-name', '2020-01-01T00:00:00Z'),
    artifact(2, 'totally-unrecognized-name', '2020-01-02T00:00:00Z'),
    artifact(3, 'totally-unrecognized-name', '2020-01-03T00:00:00Z'),
  ];
  const { toDelete, skippedUnknown } = selectArtifactsForDeletion({ artifacts });
  assert.equal(toDelete.length, 0);
  assert.equal(skippedUnknown.length, 3);
});

test('an already-expired artifact is selected for deletion even if it would otherwise be in the newest-N', () => {
  const artifacts = [
    artifact(1, 'drift-report', '2026-01-01T00:00:00Z'),
    artifact(2, 'drift-report', '2026-01-10T00:00:00Z', true), // newest but expired
  ];
  const { toKeep, toDelete } = selectArtifactsForDeletion({ artifacts });
  assert.deepEqual(toDelete.map((a) => a.id), [2]);
  assert.deepEqual(toKeep.map((a) => a.id), [1]);
});

test('malformed API responses fail safe to "delete nothing", never mass deletion', () => {
  for (const bad of [null, undefined, 'not-an-array', 42, {}, [null, undefined, 'x', 5]]) {
    const { toDelete, toKeep, skippedUnknown } = selectArtifactsForDeletion({ artifacts: bad });
    assert.deepEqual(toDelete, []);
    assert.deepEqual(toKeep, []);
    assert.deepEqual(skippedUnknown, []);
  }
});

test('a record missing required fields is skipped rather than crashing selection', () => {
  const artifacts = [
    { id: 1 }, // no name
    { name: 'android-apks' }, // no id
    artifact(2, 'android-apks', '2026-01-01T00:00:00Z'),
  ];
  const { toKeep, toDelete } = selectArtifactsForDeletion({ artifacts });
  assert.deepEqual([...toKeep, ...toDelete].map((a) => a.id), [2]);
});

test('streams are independent: pruning one project/workflow stream never touches another', () => {
  const artifacts = [
    artifact(1, 'android-apks', '2026-01-01T00:00:00Z'),
    artifact(2, 'satis-index', '2026-01-01T00:00:00Z'),
  ];
  const { toKeep } = selectArtifactsForDeletion({ artifacts });
  // Both are alone in their stream (count 1 <= keep 2), so both are kept —
  // a naive "keep newest 2 globally" rule would have deleted one of these.
  assert.deepEqual(toKeep.map((a) => a.id).sort(), [1, 2]);
});

test('dry-run selection is identical to live selection — only execution differs', async () => {
  const artifacts = Array.from({ length: 4 }, (_, i) =>
    artifact(i + 1, 'android-apks', `2026-01-0${i + 1}T00:00:00Z`)
  );
  const deletedIds = [];
  const fakeGithub = {
    paginate: async () => artifacts,
    rest: {
      actions: {
        listArtifactsForRepo: () => {},
        deleteArtifact: async ({ artifact_id }) => deletedIds.push(artifact_id),
      },
    },
  };
  const core = { info() {}, warning() {}, error() {} };
  const context = { repo: { owner: 'o', repo: 'r' } };

  const dryResult = await runArtifactCleanup({ github: fakeGithub, context, core, dryRun: true });
  assert.deepEqual(deletedIds, [], 'dry-run must not call the delete API');

  const liveResult = await runArtifactCleanup({ github: fakeGithub, context, core, dryRun: false });
  assert.deepEqual(
    dryResult.toDelete.map((a) => a.id).sort(),
    liveResult.toDelete.map((a) => a.id).sort(),
    'live selection must match dry-run selection'
  );
  assert.deepEqual(deletedIds.sort(), liveResult.toDelete.map((a) => a.id).sort());
});

test('a listing API error fails safe: no deletions attempted', async () => {
  const deletedIds = [];
  const fakeGithub = {
    paginate: async () => {
      throw new Error('simulated GitHub API outage');
    },
    rest: {
      actions: {
        listArtifactsForRepo: () => {},
        deleteArtifact: async ({ artifact_id }) => deletedIds.push(artifact_id),
      },
    },
  };
  const core = { info() {}, warning() {}, error() {} };
  const context = { repo: { owner: 'o', repo: 'r' } };

  const result = await runArtifactCleanup({ github: fakeGithub, context, core, dryRun: false });
  assert.deepEqual(result.toDelete, []);
  assert.deepEqual(deletedIds, []);
});

test('pagination: artifacts spread across multiple pages are all considered', async () => {
  const page1 = [artifact(1, 'android-apks', '2026-01-01T00:00:00Z')];
  const page2 = [artifact(2, 'android-apks', '2026-01-02T00:00:00Z')];
  const page3 = [artifact(3, 'android-apks', '2026-01-03T00:00:00Z')];
  const fakeGithub = {
    // github.paginate already resolves pagination internally in the real
    // Octokit client; this fake proves our orchestrator awaits the full
    // resolved list rather than only reading the first page.
    paginate: async () => [...page1, ...page2, ...page3],
    rest: { actions: { listArtifactsForRepo: () => {}, deleteArtifact: async () => {} } },
  };
  const core = { info() {}, warning() {}, error() {} };
  const context = { repo: { owner: 'o', repo: 'r' } };

  const result = await runArtifactCleanup({ github: fakeGithub, context, core, dryRun: true });
  assert.equal(result.toKeep.length + result.toDelete.length, 3);
});

test('PR cache pruning only selects caches scoped to the closed PR ref', () => {
  const caches = [
    { id: 1, ref: 'refs/pull/42/merge', key: 'node-cache-a' },
    { id: 2, ref: 'refs/heads/main', key: 'node-cache-main' },
    { id: 3, ref: 'refs/pull/7/merge', key: 'node-cache-other-pr' },
  ];
  const toDelete = selectPrCachesForDeletion({ caches, prRef: 'refs/pull/42/merge' });
  assert.deepEqual(toDelete.map((c) => c.id), [1]);
});

test('PR cache pruning fails safe on malformed cache list', () => {
  for (const bad of [null, undefined, 'x', 5]) {
    assert.deepEqual(selectPrCachesForDeletion({ caches: bad, prRef: 'refs/pull/1/merge' }), []);
  }
});

test('runPrCachePrune never deletes in dry-run mode', async () => {
  const deleted = [];
  const fakeGithub = {
    paginate: async () => [{ id: 9, ref: 'refs/pull/5/merge' }],
    rest: {
      actions: {
        getActionsCacheList: () => {},
        deleteActionsCacheById: async ({ cache_id }) => deleted.push(cache_id),
      },
    },
  };
  const core = { info() {}, warning() {}, error() {} };
  const context = { repo: { owner: 'o', repo: 'r' } };
  await runPrCachePrune({ github: fakeGithub, context, core, prRef: 'refs/pull/5/merge', dryRun: true });
  assert.deepEqual(deleted, []);
});

test('classify() marks distributable binaries as release, reports as temp, and unrecognized names as unknown', () => {
  assert.equal(classify('android-apks').category, 'release');
  assert.equal(classify('macos-build').category, 'release');
  assert.equal(classify('drift-report').category, 'temp');
  assert.equal(classify('cs-frontend-parity-report').category, 'temp');
  assert.equal(classify('some-future-workflow-artifact').category, 'unknown');
});
