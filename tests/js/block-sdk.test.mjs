import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

// Phase 9 / Step 1 — the BaseBlock SDK contract.
//
// The point of these tests is that the SDK is tested against the REAL
// ThemeCanvasRuntime source (canvas/runtime.blade.php, loaded with only its
// two @json() protocol-array lines substituted), not against a hand-written
// stub. A stub would let the SDK and the runtime drift apart silently, which
// is precisely the failure the runtime's own zero-consumer state is prone to.

const pkgDir = fileURLToPath(new URL('../../packages/saas-platform/modules/theme-runtime/', import.meta.url));

// The contract exposes its factory surface through a CommonJS export (the
// same distributable-JS seam canvas-identity.js uses). Load it the same way
// the package ships it: read the file, run it in an isolated context with a
// CommonJS shim, and take the export.
function loadSdkModule() {
    const source = fs.readFileSync(path.join(pkgDir, 'resources/js/block-sdk.js'), 'utf8');
    const module = { exports: {} };
    vm.runInNewContext(source, { module, exports: module.exports, console });
    return module.exports;
}

const sdkModule = loadSdkModule();

// ------------------------------------------------------------------ harness

class FakeClassList {
    constructor(el) { this.el = el; this.set = new Set(); }
    add(...names) { names.forEach((n) => this.set.add(n)); }
    remove(...names) { names.forEach((n) => this.set.delete(n)); }
    contains(name) { return this.set.has(name); }
    toggle(name, force) { force ? this.set.add(name) : this.set.delete(name); }
}

class FakeElement {
    constructor(tagName, doc) {
        this.tagName = String(tagName || 'div').toLowerCase();
        this.ownerDocument = doc;
        this.attributes = new Map();
        this.childNodes = [];
        this.parentNode = null;
        this.listeners = new Map();
        this.classList = new FakeClassList(this);
        this.textContent = '';
        this.style = {};
    }
    setAttribute(name, value) { this.attributes.set(name, String(value)); }
    getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
    removeAttribute(name) { this.attributes.delete(name); }
    hasAttribute(name) { return this.attributes.has(name); }
    appendChild(node) {
        node.parentNode = this;
        this.childNodes.push(node);
        // A real <script src> / <link rel=stylesheet> fires load (or error)
        // after insertion, and both the runtime's trackScript() and the SDK's
        // stylesheet loader wait on that — so the fake document must too.
        if (node.tagName === 'script' || node.tagName === 'link') {
            setTimeout(() => { if (typeof node.onload === 'function') node.onload(); }, 0);
        }
        return node;
    }
    replaceWith(node) {
        const parent = this.parentNode;
        if (!parent) return;
        const index = parent.childNodes.indexOf(this);
        node.parentNode = parent;
        parent.childNodes[index] = node;
        this.parentNode = null;
    }
    addEventListener(name, fn) {
        if (!this.listeners.has(name)) this.listeners.set(name, []);
        this.listeners.get(name).push(fn);
    }
    dispatchEvent(event) {
        (this.listeners.get(event.type) || []).forEach((fn) => fn(event));
        return true;
    }
    matches(selector) {
        if (selector.startsWith('[')) {
            const m = /^\[([a-z-]+)(?:=["']([^"']*)["'])?\]$/.exec(selector);
            if (!m) return false;
            if (!this.attributes.has(m[1])) return false;
            return m[2] === undefined || this.attributes.get(m[1]) === m[2];
        }
        if (selector.startsWith('.')) return this.classList.contains(selector.slice(1));
        return this.tagName === selector.toLowerCase();
    }
    querySelectorAll(selector) {
        const out = [];
        const walk = (node) => {
            for (const child of node.childNodes) {
                if (child.matches && child.matches(selector)) out.push(child);
                walk(child);
            }
        };
        walk(this);
        return out;
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}

class FakeCustomEvent {
    constructor(type, init) { this.type = type; this.detail = (init || {}).detail; }
}

class FakeDocument {
    constructor() {
        this.readyState = 'complete';
        this.documentElement = new FakeElement('html', this);
        this.head = this.documentElement.appendChild(new FakeElement('head', this));
        this.body = this.documentElement.appendChild(new FakeElement('body', this));
        this.listeners = new Map();
        this.templates = new Map();
        this.scripts = [];
    }
    createElement(tagName) { return new FakeElement(tagName, this); }
    appendChild(node) { return this.body.appendChild(node); }
    addEventListener(name, fn) {
        if (!this.listeners.has(name)) this.listeners.set(name, []);
        this.listeners.get(name).push(fn);
    }
    dispatchEvent(event) {
        (this.listeners.get(event.type) || []).forEach((fn) => fn(event));
        return true;
    }
    querySelectorAll(selector) { return this.documentElement.querySelectorAll(selector); }
    querySelector(selector) { return this.documentElement.querySelector(selector); }
}

function makeTemplate(doc) {
    const tpl = new FakeElement('template', doc);
    Object.defineProperty(tpl, 'innerHTML', {
        set(value) {
            tpl.content = { firstElementChild: doc.templates.get(value.trim()) || null };
        },
    });
    tpl.content = { firstElementChild: null };
    return tpl;
}

// The REAL runtime source, with only two substitutions: the <script> wrapper
// is stripped, and the two @json() protocol-array lines are replaced with the
// literals PreviewProtocol actually pins.
const RUNTIME_SOURCE = fs
    .readFileSync(path.join(pkgDir, 'resources/views/canvas/runtime.blade.php'), 'utf8')
    .replace(/^\s*<script>\s*/, '')
    .replace(/\s*<\/script>\s*$/, '')
    .replace(/const TO_PREVIEW = new Set\(@json\([^)]*\)\);/,
        "const TO_PREVIEW = new Set(['select','updateBlock','setProp','showDropZones','setDropTarget','setContainerTarget','getDropIndex','setStructuralDrop']);")
    .replace(/const TO_EDITOR = new Set\(@json\([^)]*\)\);/,
        "const TO_EDITOR = new Set(['ready','iframeHeight','blockSelected','dropIndexResult','inlinePropChange','dragStart','dragOver','dropNode','dragCancel']);");

/** Boot the real runtime + the real SDK against a fresh fake document. */
function boot({ readyState = 'complete' } = {}) {
    const doc = new FakeDocument();
    doc.readyState = readyState;
    doc.createElement = ((original) => (tagName) => (
        tagName === 'template' ? makeTemplate(doc) : original(tagName)
    ))(FakeDocument.prototype.createElement);

    const warnings = [];
    const errors = [];
    const win = {
        location: { origin: 'https://example.test' },
        scrollX: 0,
        scrollY: 0,
        scrollTo(x, y) { win.scrollX = x; win.scrollY = y; },
        console: {
            warn: (m) => warnings.push(m),
            error: (m, e) => errors.push(m),
        },
    };
    win.window = win;
    win.document = doc;

    const context = vm.createContext({
        window: win,
        document: doc,
        console: win.console,
        CSS: { escape: (s) => String(s).replace(/["\\]/g, '\\$&') },
        CustomEvent: FakeCustomEvent,
        Promise,
        setTimeout,
        Object,
        Array,
        Map,
        Set,
        WeakMap,
        String,
        Number,
        Math,
        JSON,
        Error,
        TypeError,
    });
    context.globalThis = context;

    vm.runInContext(RUNTIME_SOURCE, context, { filename: 'runtime.blade.js' });
    vm.runInContext(fs.readFileSync(path.join(pkgDir, 'resources/js/block-sdk.js'), 'utf8'), context, {
        filename: 'block-sdk.js',
    });

    return { runtime: win.ThemeCanvasRuntime, sdk: win.ThemeBlockSDK, doc, win, warnings, errors, context };
}

function blockElement(doc, { type, id, props = null, className = 'tb-block', text = null }) {
    const el = new FakeElement('div', doc);
    el.setAttribute('class', className);
    el.setAttribute('data-tb-block-id', id);
    el.setAttribute('data-tb-block-type', type);
    if (props !== null) el.setAttribute('data-tb-block-props', JSON.stringify(props));
    if (text !== null) {
        const child = new FakeElement('span', doc);
        child.classList.add(text);
        el.appendChild(child);
    }
    doc.body.appendChild(el);
    return el;
}

const tick = () => new Promise((resolve) => setImmediate(resolve));

// -------------------------------------------------------------------- tests

test('the SDK ships as a distributable asset pinned to contract version 1', () => {
    const source = fs.readFileSync(path.join(pkgDir, 'resources/js/block-sdk.js'), 'utf8');
    assert.ok(source.length > 0, 'block-sdk.js must exist in the package');
    assert.match(source, /VERSION = 1;/, 'the distributable must pin contract version 1');
    assert.equal(sdkModule.VERSION, 1);
    assert.equal(typeof sdkModule.create, 'function');
    assert.equal(typeof sdkModule.BaseBlock, 'function');
});

test('create() fails fast on a missing or mismatched runtime contract', () => {
    const cases = [
        [null, /no runtime/],
        [{ version: 2, registerBlock() {}, initBlock() {}, register() {} }, /version 2/],
        [{ version: 1, initBlock() {}, register() {} }, /version 1/],
    ];
    for (const [runtime, expected] of cases) {
        assert.throws(() => sdkModule.create(runtime, {}), expected);
    }
});

test('defineBlock() rejects a missing or empty type', () => {
    const { sdk } = boot();
    assert.throws(() => sdk.defineBlock({ type: '' }), /non-empty "type"/);
    assert.throws(() => sdk.defineBlock({}), /non-empty "type"/);
    assert.throws(() => sdk.defineBlock({ type: 'x', deps: 'nope' }), /"deps" must be an object/);
    assert.throws(() => sdk.defineBlock({ type: 'x', defaultProps: 7 }), /"defaultProps" must be an object/);
});

test('a block gets the documented ctx surface, and the object form registers', async () => {
    const { sdk, runtime, doc } = boot();
    let seen = null;

    const unregister = sdk.defineBlock({
        type: 'demo',
        defaultProps: { step: 2 },
        init(ctx) {
            seen = ctx;
            return ctx.ready();
        },
    });

    const el = blockElement(doc, { type: 'demo', id: 'b1', props: { value: 5 } });
    runtime.initBlock(el);
    await tick();

    assert.ok(seen, 'init must have run');
    // Runtime-owned, passed through untouched.
    assert.equal(seen.element, el);
    assert.equal(seen.type, 'demo');
    assert.equal(seen.blockId, 'b1');
    assert.equal(typeof seen.on, 'function');
    assert.equal(typeof seen.off, 'function');
    // SDK-added surface.
    assert.equal(typeof seen.$, 'function');
    assert.equal(typeof seen.ready, 'function');
    assert.equal(typeof seen.setProp, 'function');
    assert.equal(typeof seen.emit, 'function');
    assert.ok(seen.block, 'ctx.block exposes the instance');
    assert.ok(seen.state && typeof seen.state === 'object', 'ctx.state is the per-instance bag');

    // defaultProps merge UNDER the runtime-parsed props, and freeze.
    assert.equal(seen.props.step, 2);
    assert.equal(seen.props.value, 5);
    assert.ok(Object.isFrozen(seen.props), 'ctx.props must be frozen');

    // The block reached its ready state through the runtime's own class.
    assert.equal(el.classList.contains('tb-block-ready'), true);
    assert.equal(el.getAttribute('data-tb-block-init'), '1');

    unregister();
});

test('ctx.$ is scoped to the block root and never escapes it', () => {
    const { sdk, runtime, doc } = boot();
    let scoped = null;

    sdk.defineBlock({
        type: 'scoped',
        init(ctx) { scoped = ctx; },
    });

    const outside = new FakeElement('div', doc);
    outside.classList.add('needle');
    doc.body.appendChild(outside);

    const el = blockElement(doc, { type: 'scoped', id: 'b1', text: 'needle' });
    runtime.initBlock(el);

    assert.equal(scoped.$('.needle').length, 1);
    assert.equal(scoped.$('.needle')[0], el.childNodes[0]);
    assert.deepEqual(scoped.$('   '), []);
    assert.deepEqual(scoped.$('.nope'), []);
});

test('declared js dependencies resolve before the init body runs', async () => {
    const { sdk, runtime, doc } = boot();
    const order = [];

    sdk.defineBlock({
        type: 'dep_js',
        deps: { js: ['/vendor/acme/blocks/1.0.0/lib.js'] },
        init(ctx) {
            order.push('init');
            return ctx.ready();
        },
    });

    const el = blockElement(doc, { type: 'dep_js', id: 'b1' });
    runtime.initBlock(el);
    assert.deepEqual(order, ['init'], 'init itself runs immediately and awaits its own deps');

    await tick();
    assert.equal(el.classList.contains('tb-block-ready'), true, 'ready is only set after deps resolved');
});

test('declared css dependencies are injected once and deduped across blocks', async () => {
    const { sdk, runtime, doc } = boot();

    sdk.defineBlock({
        type: 'dep_css',
        deps: { css: ['/vendor/acme/blocks/1.0.0/counter.css'] },
        init(ctx) { return ctx.ready(); },
    });

    const a = blockElement(doc, { type: 'dep_css', id: 'a' });
    const b = blockElement(doc, { type: 'dep_css', id: 'b' });
    runtime.initBlock(a);
    runtime.initBlock(b);
    await tick();

    const links = doc.head.childNodes.filter((n) => n.tagName === 'link');
    assert.equal(links.length, 1, 'a stylesheet must be injected once, not per block');
    assert.equal(links[0].rel, 'stylesheet');
    assert.equal(links[0].href, '/vendor/acme/blocks/1.0.0/counter.css');
});

test('getBlockType() and assetsFor() report the registered definition', () => {
    const { sdk } = boot();
    sdk.defineBlock({ type: 'probe', version: 3, deps: { js: ['/a.js'], css: ['/a.css'] } });

    const info = sdk.getBlockType('probe');
    assert.equal(info.type, 'probe');
    assert.equal(info.version, 3);
    assert.equal(info.instanceCount, 0);
    assert.deepEqual(Array.from(info.deps.js), ['/a.js']);
    assert.deepEqual(Array.from(sdk.assetsFor('probe').css), ['/a.css']);
    assert.equal(sdk.getBlockType('missing'), null);
    assert.equal(sdk.assetsFor('missing'), null);
    assert.ok(Object.isFrozen(info));
});

test('duplicate deps entries are collapsed and non-strings dropped', () => {
    const { sdk } = boot();
    sdk.defineBlock({ type: 'dedupe', deps: { js: ['/a.js', '/a.js', '', 42, null, '/b.js'] } });
    assert.deepEqual(Array.from(sdk.assetsFor('dedupe').js), ['/a.js', '/b.js']);
});

test('ctx.emit stamps blockId and type, and warns once on a non-namespaced name', () => {
    const { sdk, runtime, doc, warnings } = boot();
    const seen = [];

    sdk.defineBlock({
        type: 'emitter',
        init(ctx) {
            ctx.on('block:emitter:hello', (payload) => seen.push(payload));
            ctx.emit('block:emitter:hello', { n: 1 });
            ctx.emit('loose_name', { n: 2 });
            ctx.emit('loose_name', { n: 3 });
        },
    });

    const el = blockElement(doc, { type: 'emitter', id: 'b7' });
    runtime.initBlock(el);

    assert.equal(seen.length, 1);
    assert.equal(seen[0].n, 1);
    assert.equal(seen[0].blockId, 'b7');
    assert.equal(seen[0].type, 'emitter');

    const namespaceWarnings = warnings.filter((w) => /not namespaced/.test(w));
    assert.equal(namespaceWarnings.length, 1, 'a repeated bad event name warns exactly once');
    assert.match(namespaceWarnings[0], /loose_name/);

    assert.equal(sdk.EVENT_PATTERN.test('block:a:b'), true);
    assert.equal(sdk.EVENT_PATTERN.test('state:update'), true);
    assert.equal(sdk.EVENT_PATTERN.test('nope'), false);
});

test('ctx.emit rejects a non-string event name', () => {
    const { sdk, runtime, doc } = boot();
    let thrown = null;
    sdk.defineBlock({ type: 'bademit', init(ctx) { try { ctx.emit(''); } catch (e) { thrown = e; } } });
    runtime.initBlock(blockElement(doc, { type: 'bademit', id: 'b1' }));
    assert.ok(thrown instanceof TypeError);
});

test('setProp is local: it updates props, re-serialises the attribute and emits state:update', () => {
    const { sdk, runtime, doc } = boot();
    let ctxRef = null;
    const states = [];

    runtime.events.on('state:update', (payload) => states.push(payload));

    sdk.defineBlock({ type: 'setter', init(ctx) { ctxRef = ctx; } });
    const el = blockElement(doc, { type: 'setter', id: 'b1', props: { value: 1 } });
    runtime.initBlock(el);

    ctxRef.setProp('value', 9);
    ctxRef.setProp('', 'ignored');
    ctxRef.setProp(5, 'ignored');

    assert.equal(ctxRef.block.props.value, 9);
    assert.equal(JSON.parse(el.getAttribute('data-tb-block-props')).value, 9);
    assert.equal(states.length, 1, 'an ignored key emits nothing');
    assert.equal(states[0].blockId, 'b1');
    assert.equal(states[0].type, 'setter');
    assert.equal(states[0].props.value, 9);
});

test('sdk.setProp addresses a live instance by data-tb-block-id and delegates to ctx.setProp', () => {
    const { sdk, runtime, doc } = boot();
    const states = [];

    runtime.events.on('state:update', (payload) => states.push(payload));
    sdk.defineBlock({ type: 'external_setter' });

    const first = blockElement(doc, { type: 'external_setter', id: 'block-a', props: { value: 1 } });
    const second = blockElement(doc, { type: 'external_setter', id: 'block-b', props: { value: 2 } });
    runtime.initBlock(first);
    runtime.initBlock(second);

    assert.equal(sdk.instances, undefined, 'the private instance registry must not leak');
    assert.equal(sdk.setProp('block-b', 'value', 27), true);
    assert.equal(JSON.parse(first.getAttribute('data-tb-block-props')).value, 1);
    assert.equal(JSON.parse(second.getAttribute('data-tb-block-props')).value, 27);
    assert.equal(states.length, 1);
    assert.equal(states[0].blockId, 'block-b');
    assert.equal(states[0].type, 'external_setter');
    assert.equal(states[0].props.value, 27);
});

test('sdk.setProp warns and returns false when no live block has the requested id', () => {
    const { sdk, warnings } = boot();

    assert.doesNotThrow(() => {
        assert.equal(sdk.setProp('missing-block', 'value', 9), false);
        assert.equal(sdk.setProp(null, 'value', 9), false);
    });
    assert.equal(warnings.length, 2);
    assert.match(warnings[0], /setProp could not find a live block instance for id "missing-block"/);
    assert.match(warnings[1], /setProp requires a non-empty data-tb-block-id/);
});

test('the preview protocol validates setProp payloads before routing the command to the SDK', () => {
    const { sdk, runtime, doc } = boot();
    const states = [];
    runtime.events.on('state:update', (payload) => states.push(payload));
    sdk.defineBlock({ type: 'protocol_setter' });

    const el = blockElement(doc, { type: 'protocol_setter', id: 'message-target', props: { title: 'Before' } });
    runtime.initBlock(el);

    const message = {
        protocolVersion: 1,
        action: 'setProp',
        blockId: 'message-target',
        propKey: 'title',
        value: 'After',
    };

    assert.equal(runtime.valid(message, 'to-preview'), true);
    assert.equal(runtime.valid({ ...message, protocolVersion: 2 }, 'to-preview'), false);
    assert.equal(runtime.valid({ ...message, blockId: '' }, 'to-preview'), false);
    assert.equal(runtime.valid({ ...message, propKey: 4 }, 'to-preview'), false);

    sdk.setProp(message.blockId, message.propKey, message.value);
    assert.equal(JSON.parse(el.getAttribute('data-tb-block-props')).title, 'After');
    assert.equal(states.length, 1);
    assert.equal(states[0].blockId, 'message-target');
});

test('a throwing init marks the block failed and never takes down a sibling', async () => {
    const { sdk, runtime, doc, errors } = boot();
    const survivors = [];
    const blockErrors = [];

    runtime.events.on('block:error', (p) => blockErrors.push(p));

    sdk.defineBlock({ type: 'broken', init() { throw new Error('boom'); } });
    sdk.defineBlock({ type: 'healthy', init(ctx) { survivors.push(ctx.blockId); } });

    const bad = blockElement(doc, { type: 'broken', id: 'bad' });
    const good = blockElement(doc, { type: 'healthy', id: 'good' });

    assert.doesNotThrow(() => runtime.initBlock(bad), 'the runtime already contains init errors');
    runtime.initBlock(good);

    assert.equal(bad.getAttribute('data-tb-block-error'), '1');
    assert.equal(bad.classList.contains('tb-block-ready'), false);
    assert.deepEqual(survivors, ['good']);
    assert.equal(blockErrors.length, 1);
    assert.equal(blockErrors[0].blockId, 'bad');
    assert.match(blockErrors[0].error, /boom/);
    assert.ok(errors.some((m) => /broken/.test(m)));
});

test('a rejected async init also marks the block failed and stays off ready', async () => {
    const { sdk, runtime, doc } = boot();
    const blockErrors = [];
    runtime.events.on('block:error', (p) => blockErrors.push(p));

    sdk.defineBlock({ type: 'asyncbroken', init() { return Promise.reject(new Error('late boom')); } });
    const el = blockElement(doc, { type: 'asyncbroken', id: 'x' });
    runtime.initBlock(el);
    await tick();

    assert.equal(el.getAttribute('data-tb-block-error'), '1');
    assert.equal(el.classList.contains('tb-block-ready'), false);
    assert.equal(blockErrors.length, 1);
    assert.match(blockErrors[0].error, /late boom/);
});

test('an element is initialised once, however many times initBlock runs', () => {
    const { sdk, runtime, doc } = boot();
    let inits = 0;
    sdk.defineBlock({ type: 'once', init() { inits += 1; } });

    const el = blockElement(doc, { type: 'once', id: 'b1' });
    runtime.initBlock(el);
    runtime.initBlock(el);
    assert.equal(inits, 1);
    assert.equal(sdk.getBlockType('once').instanceCount, 1);
});

test('scan() initialises every block already in the document', () => {
    const { sdk, runtime, doc } = boot();
    const seen = [];
    sdk.defineBlock({ type: 'scanned', init(ctx) { seen.push(ctx.blockId); } });

    blockElement(doc, { type: 'scanned', id: 'a' });
    blockElement(doc, { type: 'scanned', id: 'b' });
    blockElement(doc, { type: 'unregistered_type', id: 'c' });

    assert.equal(sdk.scan(doc), 2, 'unknown types are skipped, not failed');
    assert.deepEqual(seen, ['a', 'b']);
    void runtime;
});

test('the SDK initialises every block through the runtime mount phase', () => {
    // readyState 'loading' defers the runtime's own mountDocument() call to
    // DOMContentLoaded — exactly the real page ordering.
    const { sdk, runtime, doc } = boot({ readyState: 'loading' });
    const seen = [];
    sdk.defineBlock({ type: 'mounted', init(ctx) { seen.push(ctx.blockId); } });

    const el = blockElement(doc, { type: 'mounted', id: 'm1' });
    assert.deepEqual(seen, [], 'nothing runs before DOMContentLoaded');

    doc.dispatchEvent(new FakeCustomEvent('DOMContentLoaded'));
    assert.deepEqual(seen, ['m1']);
    void runtime;
});

test('replaceNode destroys the old element, inits the new one and calls onUpdate', async () => {
    const { sdk, runtime, doc } = boot();
    const log = [];
    let oldEl = null;

    sdk.defineBlock({
        type: 'swapped',
        init(ctx) { log.push(['init', ctx.blockId, ctx.element === oldEl]); },
        onUpdate(ctx) { log.push(['update', ctx.blockId]); },
        onDestroy(ctx) { log.push(['destroy', ctx.blockId]); },
    });

    oldEl = blockElement(doc, { type: 'swapped', id: 's1' });
    runtime.initBlock(oldEl);

    const replacement = blockElement(doc, { type: 'swapped', id: 's1' });
    const html = '<div class="tb-block" data-tb-block-id="s1" data-tb-block-type="swapped"></div>';
    doc.templates.set(html, replacement);
    const returned = runtime.replaceNode('s1', html);

    assert.equal(returned, replacement);
    assert.deepEqual(log, [
        ['init', 's1', true],
        ['destroy', 's1'],
        ['init', 's1', false],
        ['update', 's1'],
    ]);
    assert.equal(oldEl.classList.contains('tb-block-ready'), false, 'the detached node keeps no state');
    assert.equal(replacement.classList.contains('tb-block-ready'), true);
    assert.equal(sdk.getBlockType('swapped').instanceCount, 1, 'the old instance is released');
});

test('a destroy landing during a pending async init is delivered AFTER the init continuation, then the instance is released (SDK-002 gate)', async () => {
    // replaceNode() drives destroy and update back-to-back, synchronously
    // after initBlock() returns — but an init that returns a promise has not
    // finished its body yet. The SDK must serialise phases per instance:
    // the update is dropped (init's continuation is the post-init run) and
    // the destroy is HELD until the continuation has completed, so
    // releaseInstance() can never overtake onDestroy and orphan the block's
    // own teardown. This is the ordering whose absence leaked one window
    // listener per block replacement.
    const { sdk, runtime, doc, errors } = boot();
    const log = [];
    let resolveInit = null;

    sdk.defineBlock({
        type: 'gated',
        init(ctx) {
            log.push(['init', ctx.blockId]);
            return new Promise((resolve) => {
                resolveInit = () => {
                    log.push(['init:continuation', ctx.blockId]);
                    resolve();
                };
            });
        },
        onUpdate(ctx) { log.push(['update', ctx.blockId]); },
        onDestroy(ctx) { log.push(['destroy', ctx.blockId]); },
    });

    const el = blockElement(doc, { type: 'gated', id: 'g1' });
    runtime.initBlock(el);
    await tick();
    assert.deepEqual(log, [['init', 'g1']], 'init is running but its continuation has not run yet');

    // An update landing while init is pending is DROPPED, not queued: the
    // pending continuation is the authoritative post-init run.
    runtime.lifecycle('update', el, { blockId: 'g1' });
    assert.deepEqual(log, [['init', 'g1']], 'an update during a pending init is dropped');

    // A destroy landing while init is pending is HELD until it settles.
    runtime.lifecycle('destroy', el, { blockId: 'g1' });
    assert.deepEqual(log, [['init', 'g1']], 'onDestroy must not run before the init continuation');
    assert.equal(sdk.getBlockType('gated').instanceCount, 1, 'the instance is not released while its init is pending');

    resolveInit();
    await tick();

    assert.deepEqual(log, [
        ['init', 'g1'],
        ['init:continuation', 'g1'],
        ['destroy', 'g1'],
    ], 'onDestroy runs only after the init continuation has completed');
    assert.equal(sdk.getBlockType('gated').instanceCount, 0, 'the instance is released after onDestroy completed');
    assert.equal(errors.length, 0, errors.join(' | '));
});

test('the runtime WeakMap subscription cleanup is what tears down ctx.on handlers', () => {
    // This is the destroy contract: the runtime releases every ctx.on()
    // subscription BEFORE destroy handlers run, so a block never has to keep
    // its own registry — and a destroy handler provably cannot still be
    // receiving its own element's events.
    const { sdk, runtime, doc } = boot();
    const hits = [];
    let duringDestroy = null;

    sdk.defineBlock({
        type: 'weakmap',
        init(ctx) { ctx.on('state:update', (p) => hits.push(p.type)); },
        onDestroy(ctx) {
            runtime.events.emit('state:update', { type: 'probe' });
            duringDestroy = hits.length;
        },
    });

    const el = blockElement(doc, { type: 'weakmap', id: 'w1' });
    runtime.initBlock(el);

    runtime.events.emit('state:update', { type: 'live' });
    assert.equal(hits.length, 1, 'the subscription is live before destroy');

    runtime.lifecycle('destroy', el, { blockId: 'w1' });

    assert.equal(duringDestroy, 1, 'the runtime released the subscription before onDestroy ran');
    runtime.events.emit('state:update', { type: 'after' });
    assert.equal(hits.length, 1, 'no subscription survives destroy');
    assert.equal(sdk.getBlockType('weakmap').instanceCount, 0);
});

test('a destroy handler that throws is contained and still releases the instance', () => {
    const { sdk, runtime, doc, errors } = boot();
    sdk.defineBlock({
        type: 'badteardown',
        onDestroy() { throw new Error('teardown boom'); },
    });

    const el = blockElement(doc, { type: 'badteardown', id: 'b1' });
    runtime.initBlock(el);

    assert.doesNotThrow(() => runtime.lifecycle('destroy', el, { blockId: 'b1' }));
    assert.equal(sdk.getBlockType('badteardown').instanceCount, 0);
    assert.ok(errors.some((m) => /onDestroy failed/.test(m)));
});

test('a refresh phase reaches every live block instance', () => {
    const { sdk, runtime, doc } = boot();
    const refreshed = [];
    sdk.defineBlock({ type: 'refreshing', onRefresh(ctx) { refreshed.push(ctx.blockId); } });

    blockElement(doc, { type: 'refreshing', id: 'r1' });
    const r2 = blockElement(doc, { type: 'refreshing', id: 'r2' });
    runtime.initBlock(r2);
    runtime.lifecycle('refresh', doc, {});

    // r1 was never init'd, so it has no instance; r2 is the only live one.
    assert.deepEqual(refreshed, ['r2']);
});

test('the class form subclasses BaseBlock and keeps per-instance state', async () => {
    const { sdk, runtime, doc } = boot();

    class Counter extends sdk.BaseBlock {
        init(ctx) {
            this.state.n = Number(ctx.props.n || 0) + 1;
            return ctx.ready();
        }
    }
    Counter.type = 'class_counter';
    Counter.defaultProps = { n: 1 };

    const unregister = sdk.defineBlock(Counter);

    const a = blockElement(doc, { type: 'class_counter', id: 'a', props: { n: 10 } });
    const b = blockElement(doc, { type: 'class_counter', id: 'b' });
    runtime.initBlock(a);
    runtime.initBlock(b);
    await tick();

    assert.equal(sdk.getBlockType('class_counter').instanceCount, 2);
    assert.equal(typeof unregister, 'function');
    unregister();
    assert.equal(sdk.getBlockType('class_counter'), null);
});

test('unregister() releases live instances and stops further init', () => {
    const { sdk, runtime, doc } = boot();
    let inits = 0;
    const unregister = sdk.defineBlock({ type: 'gone', init() { inits += 1; } });

    const el = blockElement(doc, { type: 'gone', id: 'g1' });
    runtime.initBlock(el);
    assert.equal(inits, 1);

    unregister();
    assert.equal(sdk.getBlockType('gone'), null);

    const el2 = blockElement(doc, { type: 'gone', id: 'g2' });
    assert.equal(runtime.initBlock(el2), false, 'the runtime no longer routes the type');
});

test('redefining a type replaces the previous definition exactly once', () => {
    const { sdk, runtime, doc, warnings } = boot();
    const order = [];
    sdk.defineBlock({ type: 'dup', init() { order.push('first'); } });
    sdk.defineBlock({ type: 'dup', init() { order.push('second'); } });

    runtime.initBlock(blockElement(doc, { type: 'dup', id: 'd1' }));
    assert.deepEqual(order, ['second']);
    assert.ok(warnings.some((w) => /redefining block type/.test(w)));
});

test('the reserved and runtime event names are published as one schema', () => {
    const { sdk } = boot();
    assert.equal(sdk.EVENTS.READY, 'block:ready');
    assert.equal(sdk.EVENTS.STATE_UPDATE, 'state:update');
    assert.deepEqual(Array.from(sdk.RUNTIME_EVENTS), [
        'theme:content-updated',
        'theme:canvas-lifecycle',
        'theme:interaction',
    ]);
    assert.equal(sdk.version, 1);
    assert.ok(Object.isFrozen(sdk), 'the published SDK surface is frozen');
});

test('the reference block registers itself and drives the real DOM', async () => {
    const { sdk, runtime, doc, context } = boot();

    vm.runInContext(
        fs.readFileSync(path.join(pkgDir, 'resources/js/blocks/reference-counter.js'), 'utf8'),
        context,
        { filename: 'reference-counter.js' },
    );
    // The reference block is class-based, so read it back off the SDK's own
    // definition through a live instance rather than by re-parsing the file.
    assert.equal(typeof sdk.getBlockType('reference_counter'), 'object');

    const changes = [];
    runtime.events.on('block:reference_counter:change', (p) => changes.push(p));

    const el = blockElement(doc, {
        type: 'reference_counter',
        id: 'rc1',
        props: { step: 2, min: 0, max: 10, value: 4 },
        text: 'tb-ref-counter__value',
    });
    const valueEl = el.querySelector('.tb-ref-counter__value');
    valueEl.textContent = '4';
    el.appendChild(new FakeElement('button', doc)).classList.add('tb-ref-counter__increase');

    runtime.initBlock(el);
    await tick();

    assert.equal(el.classList.contains('tb-block-ready'), true);
    assert.equal(valueEl.textContent, '4');

    sdk.setProp('rc1', 'value', 7);
    assert.equal(valueEl.textContent, '7', 'the state:update subscriber paints an external fast-path value');

    sdk.setProp('rc1', 'max', 5);
    assert.equal(valueEl.textContent, '5', 'the subscriber re-clamps and paints when a bound limit changes');

    el.querySelector('.tb-ref-counter__increase').dispatchEvent(new FakeCustomEvent('click'));
    assert.equal(valueEl.textContent, '5', 'a counter already at its new maximum stays clamped');
    assert.deepEqual(changes, [], 'a clamped step emits nothing');

    sdk.setProp('rc1', 'max', 10);
    sdk.setProp('rc1', 'value', 4);
    el.querySelector('.tb-ref-counter__increase').dispatchEvent(new FakeCustomEvent('click'));
    assert.equal(valueEl.textContent, '6', 'a +2 step on a 4 must land on 6');
    assert.deepEqual(changes.map((c) => c.value), [6]);
    assert.equal(changes[0].blockId, 'rc1');

    // Clamp: 8 then 10, then one more step is a no-op and must emit nothing.
    for (let i = 0; i < 3; i += 1) {
        el.querySelector('.tb-ref-counter__increase').dispatchEvent(new FakeCustomEvent('click'));
    }
    assert.equal(valueEl.textContent, '10');
    assert.deepEqual(changes.map((c) => c.value), [6, 8, 10], 'a clamped step emits nothing');
});

test('the reference block script is self-guarding against double registration', () => {
    const { sdk, context, warnings } = boot();
    const source = fs.readFileSync(path.join(pkgDir, 'resources/js/blocks/reference-counter.js'), 'utf8');

    vm.runInContext(source, context);
    vm.runInContext(source, context);

    assert.ok(sdk.getBlockType('reference_counter'), 'registered on the first pass');
    assert.equal(
        warnings.filter((w) => /redefining block type/.test(w)).length,
        0,
        'a second inline of the same asset must not redefine the type',
    );
});
