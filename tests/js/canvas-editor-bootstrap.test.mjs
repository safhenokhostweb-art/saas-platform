import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

// Phase 6.1-B — packaging + fail-safe contract for the shared editor
// bootstrap. The contract file, its identity Blade view and the bootstrap
// view must all ship inside the theme-runtime package artifact, the include
// seam must stay resolvable, and the version gate must fail fast — a
// missing or mismatched contract version may never be silently ignored.
//
// Phase 9 / Step 1 extended the same seam to the Block SDK: the SDK is
// included at position 2 (immediately after the runtime it wraps, before the
// identity contract) and carries the same fail-fast gate.

const pkgDir = fileURLToPath(new URL('../../packages/saas-platform/modules/theme-runtime/', import.meta.url));

const identitySource = fs.readFileSync(path.join(pkgDir, 'resources/js/canvas-identity.js'), 'utf8');
const identityView = fs.readFileSync(path.join(pkgDir, 'resources/views/canvas/identity.blade.php'), 'utf8');
const blockSdkSource = fs.readFileSync(path.join(pkgDir, 'resources/js/block-sdk.js'), 'utf8');
const blockSdkView = fs.readFileSync(path.join(pkgDir, 'resources/views/canvas/block-sdk.blade.php'), 'utf8');
const bootstrapView = fs.readFileSync(path.join(pkgDir, 'resources/views/canvas/editor-bootstrap.blade.php'), 'utf8');

test('the identity contract asset and its blade views ship inside the package', () => {
  assert.ok(identitySource.length > 0, 'canvas-identity.js must exist in the package');
  assert.match(identitySource, /version:\s*1/, 'the distributable must pin contract version 1');

  // The identity view is the include seam hosts consume — it must be a
  // literal file_get_contents of the package asset, so the packaged JS file
  // and the runtime include can never drift apart.
  assert.match(
    identityView,
    /file_get_contents\(\\SaaSPlatform\\ThemeRuntime\\Providers\\ThemeRuntimeServiceProvider::canvasIdentityContractPath\(\)\)/,
  );
  assert.match(identityView, /<script>/);

  // The bootstrap is the one-include seam; its default services list must
  // point at the platform's own partial so the default stays working.
  assert.match(bootstrapView, /@include\('theme-runtime::canvas.runtime'\)/);
  assert.match(bootstrapView, /@include\('theme-runtime::canvas.identity'\)/);
  assert.match(bootstrapView, /canvas-structural-services/);
});

test('the block SDK contract ships inside the package behind the same provider seam', () => {
  assert.ok(blockSdkSource.length > 0, 'block-sdk.js must exist in the package');
  assert.match(blockSdkSource, /VERSION = 1;/, 'the distributable must pin contract version 1');

  // Same anti-drift rule as the identity contract: the view is a literal
  // file_get_contents of the packaged asset.
  assert.match(
    blockSdkView,
    /file_get_contents\(\\SaaSPlatform\\ThemeRuntime\\Providers\\ThemeRuntimeServiceProvider::blockSdkContractPath\(\)\)/,
  );
  assert.match(blockSdkView, /<script>/);
});

test('the provider resolves both packaged contract paths on disk', () => {
  const provider = fs.readFileSync(path.join(pkgDir, 'src/Providers/ThemeRuntimeServiceProvider.php'), 'utf8');
  const providerDir = path.join(pkgDir, 'src/Providers');

  for (const [method, expected] of [
    ['canvasIdentityContractPath', 'canvas-identity.js'],
    ['blockSdkContractPath', 'block-sdk.js'],
  ]) {
    const match = provider.match(new RegExp(`${method}\\(\\)[^}]*?return\\s+([^;]+);`, 's'));
    assert.ok(match, `ThemeRuntimeServiceProvider::${method}() must exist`);

    // Resolve the same relative seam the method returns and require the file
    // to actually exist there — this is what a packaging cut (or a
    // composer mirror copy, symlink:false) would break silently.
    const expr = match[1].trim();
    assert.match(expr, /^__DIR__/, 'the contract path must stay anchored to the provider __DIR__');
    const literals = Array.from(expr.matchAll(/'([^']*)'/g), (m) => m[1]);
    assert.ok(literals.length >= 1, 'the seam must be built from string path segments');
    // join, NOT resolve: the PHP literal starts with a leading '/' and resolve
    // would treat that as a drive-root reset on Windows.
    const resolved = path.join(providerDir, literals.join(''));
    assert.equal(fs.existsSync(resolved), true, `packaged contract missing at ${resolved}`);
    assert.equal(path.basename(resolved), expected);
  }
});

test('the block SDK is included between the runtime and the identity contract', () => {
  const order = [...bootstrapView.matchAll(/@include\('theme-runtime::canvas\.([a-z-]+)'\)/g)]
    .map((m) => m[1]);

  assert.deepEqual(order.slice(0, 3), ['runtime', 'block-sdk', 'identity'],
    'the SDK wraps the runtime, so it must load after it and before identity');
  assert.equal(order.indexOf('runtime'), 0, 'the runtime bridge stays first');
  assert.equal(order.indexOf('block-sdk'), 1);
  assert.equal(order.indexOf('identity'), 2);
});

test('the bootstrap version gate fails fast on a missing or mismatched contract', () => {
  // Extract the gate script body out of the bootstrap view.
  const gate = bootstrapView.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
  assert.ok(gate, 'bootstrap must contain its fail-fast version gate script');

  const runGate = (window) => {
    const context = vm.createContext({ window });
    vm.runInContext(gate[1], context);
  };

  // Version 1 (number, as the contract pins it) passes.
  const full = {
    CanvasIdentity: { version: 1 },
    ThemeCanvasRuntime: { version: 1 },
    ThemeBlockSDK: { version: 1 },
  };
  assert.doesNotThrow(() => runGate(full));

  // No contract at all, a string version (the truthy-but-wrong case), and a
  // future/mismatched major version must all throw — never silently pass.
  assert.throws(() => runGate({}), /CanvasIdentity contract is required/);
  assert.throws(() => runGate({ CanvasIdentity: { version: '1' } }), /CanvasIdentity contract is required/);
  assert.throws(() => runGate({ CanvasIdentity: { version: 2 } }), /CanvasIdentity contract is required/);
  // And a stale runtime bridge is rejected too.
  assert.throws(
    () => runGate({ CanvasIdentity: { version: 1 }, ThemeBlockSDK: { version: 1 } }),
    /ThemeCanvasRuntime contract is required/,
  );
  // The SDK gate: a host running a stale package (runtime + identity
  // present, SDK missing) must fail loudly rather than render a canvas whose
  // blocks silently do nothing.
  assert.throws(
    () => runGate({ CanvasIdentity: { version: 1 }, ThemeCanvasRuntime: { version: 1 } }),
    /ThemeBlockSDK contract is required/,
  );
  assert.throws(
    () => runGate({ ...full, ThemeBlockSDK: { version: 2 } }),
    /ThemeBlockSDK contract is required/,
  );
  assert.throws(
    () => runGate({ ...full, ThemeBlockSDK: { version: '1' } }),
    /ThemeBlockSDK contract is required/,
  );
});
