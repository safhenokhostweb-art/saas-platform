'use strict';

/**
 * Repository-scoped GitHub Actions storage maintenance.
 *
 * Design note: GITHUB_TOKEN is repo-scoped, so this exact file is deployed
 * unmodified to every repo that produces GitHub Actions artifacts (currently
 * saas-platform and customer-site — deployment-system has no GHA workflows
 * at all, see docs/storage-maintenance-audit.md). It is one design, not one
 * architecture per project: only the runtime `context.repo` differs.
 *
 * Nothing here talks to deployment-system's Release Gate / Artifact DB —
 * that system builds via local agents and never consumes GitHub Actions
 * artifacts, so there is no cross-system state to reason about or risk
 * corrupting.
 */

/**
 * Known artifact-name streams. Anything not matched here is 'unknown' and
 * is never deleted by keep-newest-N pruning — only GitHub's own
 * retention-days expiry (set explicitly on every upload-artifact step)
 * ever removes an unrecognized artifact. Streams map 1:1 to the
 * upload-artifact `name:` a producing workflow uses, which is already a
 * stable identifier for "which build/job produced this".
 */
const KNOWN_STREAMS = {
  'android-apks': 'release',
  'android-aab': 'release',
  'windows-exe': 'release',
  'linux-appimage': 'release',
  'ios-ipa': 'release',
  'macos-build': 'release',
  'satis-index': 'temp',
  'drift-report': 'temp',
  'component-authority-report': 'temp',
  'responsive-governance-report': 'temp',
  'enterprise-governance-report': 'temp',
  'frontend-parity-report': 'temp',
  'cs-component-authority-report': 'temp',
  'cs-responsive-governance-report': 'temp',
  'cs-enterprise-governance-report': 'temp',
  'cs-frontend-parity-report': 'temp',
  'test-results-platform': 'temp',
  'test-results-customer': 'temp',
};

const KEEP_PER_STREAM = {
  release: 2,
  temp: 2,
};

function classify(name) {
  const category = KNOWN_STREAMS[name];
  if (category === undefined) {
    return { stream: name, category: 'unknown' };
  }
  return { stream: name, category };
}

/**
 * Pure decision function: given a flat list of artifacts (as returned by
 * the Actions API), decide which ones are safe to delete right now.
 *
 * Never throws on bad input — a malformed or missing artifact list must
 * fail safe to "delete nothing", never "delete everything".
 *
 * @param {object} params
 * @param {unknown} params.artifacts - expected Array<{id, name, created_at, expired}>
 * @returns {{ toDelete: Array<object>, toKeep: Array<object>, skippedUnknown: Array<object> }}
 */
function selectArtifactsForDeletion({ artifacts }) {
  if (!Array.isArray(artifacts)) {
    return { toDelete: [], toKeep: [], skippedUnknown: [] };
  }

  const byStream = new Map();
  const skippedUnknown = [];

  for (const artifact of artifacts) {
    if (!artifact || typeof artifact !== 'object') continue;
    if (typeof artifact.id !== 'number' || typeof artifact.name !== 'string') continue;

    const { category } = classify(artifact.name);
    if (category === 'unknown') {
      skippedUnknown.push(artifact);
      continue;
    }

    if (!byStream.has(artifact.name)) byStream.set(artifact.name, []);
    byStream.get(artifact.name).push(artifact);
  }

  const toDelete = [];
  const toKeep = [];

  for (const [name, list] of byStream.entries()) {
    const { category } = classify(name);
    const keep = KEEP_PER_STREAM[category] ?? 2;

    // Newest first. created_at is ISO-8601; string sort is safe for that,
    // but parse explicitly so a missing/garbled timestamp doesn't silently
    // misorder — treat unparseable dates as "oldest" so they never win a
    // "newest N" slot on bad data.
    const sorted = [...list].sort((a, b) => {
      const ta = Date.parse(a.created_at);
      const tb = Date.parse(b.created_at);
      const va = Number.isNaN(ta) ? -Infinity : ta;
      const vb = Number.isNaN(tb) ? -Infinity : tb;
      return vb - va;
    });

    sorted.forEach((artifact, index) => {
      // GitHub's own retention-days expiry already deleted (or will delete)
      // expired artifacts; if the API still returns one flagged `expired`,
      // treat it as safe to delete regardless of its rank.
      if (index < keep && artifact.expired !== true) {
        toKeep.push(artifact);
      } else {
        toDelete.push(artifact);
      }
    });
  }

  return { toDelete, toKeep, skippedUnknown };
}

/**
 * Selects which caches to delete for a just-closed pull request. Only
 * caches scoped to that PR's own ref are eligible — this never touches
 * default-branch or other-PR caches. Matches the pattern GitHub's own docs
 * recommend for proactive cache pruning (branch-scoped caches otherwise
 * sit until the 7-day-unused / 10GB-total eviction).
 *
 * @param {object} params
 * @param {unknown} params.caches - expected Array<{id, ref, key}>
 * @param {string} params.prRef - e.g. "refs/pull/123/merge"
 */
function selectPrCachesForDeletion({ caches, prRef }) {
  if (!Array.isArray(caches) || typeof prRef !== 'string' || prRef === '') {
    return [];
  }
  return caches.filter((c) => c && typeof c === 'object' && c.ref === prRef);
}

/**
 * Orchestration entry point, called from the workflow via
 * actions/github-script. All actual GitHub API I/O lives here so the
 * decision logic above stays a pure, unit-testable function.
 */
async function runArtifactCleanup({ github, context, core, dryRun }) {
  let artifacts;
  try {
    artifacts = await github.paginate(github.rest.actions.listArtifactsForRepo, {
      owner: context.repo.owner,
      repo: context.repo.repo,
      per_page: 100,
    });
  } catch (err) {
    core.error(`Failed to list artifacts — failing safe, deleting nothing. ${err.message}`);
    return { toDelete: [], toKeep: [], skippedUnknown: [] };
  }

  const result = selectArtifactsForDeletion({ artifacts });

  core.info(
    `Artifacts: ${artifacts.length} total, keeping ${result.toKeep.length}, ` +
      `deleting ${result.toDelete.length}, ${result.skippedUnknown.length} unknown (protected).`
  );
  for (const a of result.toDelete) {
    core.info(`${dryRun ? '[dry-run] would delete' : 'deleting'} artifact #${a.id} "${a.name}" (${a.created_at})`);
  }

  if (!dryRun) {
    for (const a of result.toDelete) {
      try {
        await github.rest.actions.deleteArtifact({
          owner: context.repo.owner,
          repo: context.repo.repo,
          artifact_id: a.id,
        });
      } catch (err) {
        core.warning(`Failed to delete artifact #${a.id}: ${err.message}`);
      }
    }
  }

  return result;
}

async function runPrCachePrune({ github, context, core, prRef, dryRun }) {
  let caches;
  try {
    caches = await github.paginate(github.rest.actions.getActionsCacheList, {
      owner: context.repo.owner,
      repo: context.repo.repo,
      ref: prRef,
      per_page: 100,
    });
  } catch (err) {
    core.error(`Failed to list caches — failing safe, deleting nothing. ${err.message}`);
    return [];
  }

  const toDelete = selectPrCachesForDeletion({ caches, prRef });
  core.info(`Caches for ${prRef}: ${toDelete.length} to ${dryRun ? '(dry-run) delete' : 'delete'}.`);

  if (!dryRun) {
    for (const c of toDelete) {
      try {
        await github.rest.actions.deleteActionsCacheById({
          owner: context.repo.owner,
          repo: context.repo.repo,
          cache_id: c.id,
        });
      } catch (err) {
        core.warning(`Failed to delete cache #${c.id}: ${err.message}`);
      }
    }
  }

  return toDelete;
}

module.exports = {
  classify,
  selectArtifactsForDeletion,
  selectPrCachesForDeletion,
  runArtifactCleanup,
  runPrCachePrune,
  KNOWN_STREAMS,
  KEEP_PER_STREAM,
};
