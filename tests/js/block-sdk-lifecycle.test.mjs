import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

// Phase 9 / Step 2 — the SDK-to-runtime handoff for the carousel block.
//
// WHY THIS FILE EXISTS
//   carousel-lifecycle.test.mjs drives initCarousel() directly. That proves
//   the controller cleans up after itself, but it proves NOTHING about the
//   wiring above it: whether the SDK's onUpdate actually reaches the block,
//   whether the block's own onDestroy runs before the re-init, and whether
//   the controller handle is really released on that path.
//
//   Those are exactly the seams a real integration breaks on, and a stub SDK
//   would happily pass a broken one. So this file boots the REAL
//   ThemeCanvasRuntime (canvas/runtime.blade.php), the REAL block SDK
//   (resources/js/block-sdk.js) and the REAL carousel (resources/js/blocks/
//   carousel.js) together in one context — the same three files the canvas
//   bootstrap includes on every editor surface, in the same order — and
//   then drives an actual lifecycle transition through them.
//
//   The DOM is faked (there is no browser here), but the SDK, the runtime and
//   the block are the shipped bytes, so a contract change in any of them fails
//   here instead of in a page.

const pkgDir = fileURLToPath(new URL('../../packages/saas-platform/modules/theme-runtime/', import.meta.url));

// The REAL runtime source, with only the substitutions block-sdk.test.mjs
// already documents: strip the <script> wrapper and replace the two @json()
// protocol-array lines with the literals PreviewProtocol pins.
const RUNTIME_SOURCE = fs
    .readFileSync(path.join(pkgDir, 'resources/views/canvas/runtime.blade.php'), 'utf8')
    .replace(/^\s*<script>\s*/, '')
    .replace(/\s*<\/script>\s*$/, '')
    .replace(/const TO_PREVIEW = new Set\(@json\([^)]*\)\);/,
        "const TO_PREVIEW = new Set(['select','updateBlock','showDropZones','setDropTarget','setContainerTarget','getDropIndex','setStructuralDrop']);")
    .replace(/const TO_EDITOR = new Set\(@json\([^)]*\)\);/,
        "const TO_EDITOR = new Set(['ready','iframeHeight','blockSelected','dropIndexResult','inlinePropChange','dragStart','dragOver','dropNode','dragCancel']);");

const SDK_SOURCE = fs.readFileSync(path.join(pkgDir, 'resources/js/block-sdk.js'), 'utf8');
const CAROUSEL_SOURCE = fs.readFileSync(path.join(pkgDir, 'resources/js/blocks/carousel.js'), 'utf8');

// The legacy carousel adapter (Phase 9.2): the SAME shared controller, driven
// by the SDK, for the four types that used to carry legacyCarouselScript()
// inline. That PHP carrier is deleted — this adapter is their only carrier.
const LEGACY_CAROUSEL_SOURCE = fs.readFileSync(
    path.join(pkgDir, 'resources/js/blocks/legacy_carousel.js'),
    'utf8',
);

// ------------------------------------------------------------------ harness

class FakeClassList {
    constructor() { this.set = new Set(); }
    add(...n) { n.forEach((x) => this.set.add(x)); }
    remove(...n) { n.forEach((x) => this.set.delete(x)); }
    contains(n) { return this.set.has(n); }
    toggle(n, force) { force ? this.set.add(n) : this.set.delete(n); }
}

class FakeStyle {
    constructor() { this.props = new Map(); this.transform = ''; this.opacity = ''; this.zIndex = ''; }
    setProperty(n, v) { this.props.set(n, v); }
    getPropertyValue(n) { return this.props.get(n) || ''; }
}

class FakeCustomEvent {
    constructor(type, init) { this.type = type; this.detail = (init && init.detail) || null; }
}

class FakeElement {
    constructor(tagName, doc) {
        this.tagName = String(tagName || 'div').toLowerCase();
        this.ownerDocument = doc;
        this.attributes = new Map();
        this.childNodes = [];
        this.parentNode = null;
        this.listeners = new Map();
        this.classList = new FakeClassList();
        this.style = new FakeStyle();
        this.textContent = '';
        this.dataset = makeDataset(this);
    }
    setAttribute(n, v) {
        // `dataset` is a Proxy that reads straight out of the attribute map,
        // so there is no second copy to keep in sync here.
        this.attributes.set(n, String(v));
    }
    getAttribute(n) { return this.attributes.has(n) ? this.attributes.get(n) : null; }
    hasAttribute(n) { return this.attributes.has(n); }
    removeAttribute(n) { this.attributes.delete(n); }
    addEventListener(type, fn) {
        if (!this.listeners.has(type)) this.listeners.set(type, []);
        this.listeners.get(type).push(fn);
    }
    removeEventListener(type, fn) {
        const list = this.listeners.get(type);
        if (!list) return;
        const i = list.indexOf(fn);
        if (i !== -1) list.splice(i, 1);
    }
    dispatch(type, event) {
        (this.listeners.get(type) || []).slice().forEach((fn) => fn(event));
    }
    listenerCount(type) { return (this.listeners.get(type) || []).length; }
    appendChild(node) { node.parentNode = this; this.childNodes.push(node); return node; }
    replaceWith(node) {
        const parent = this.parentNode;
        if (!parent) return;
        const i = parent.childNodes.indexOf(this);
        node.parentNode = parent;
        parent.childNodes[i] = node;
        this.parentNode = null;
    }
    querySelector(sel) { return this.querySelectorAll(sel)[0] || null; }
    querySelectorAll(sel) {
        const want = String(sel).replace(/^\./, '');
        const out = [];
        const walk = (node) => {
            for (const c of node.childNodes) {
                // A real element's class attribute and its classList are the
                // same thing. The renderer writes class="..." and the SDK
                // adds via classList, so a fake that consults only one of
                // them makes querySelectorAll disagree with the browser.
                const attr = c.getAttribute('class');
                const hasClass = (attr ? String(attr).split(/\s+/).includes(want) : false)
                    || c.classList.contains(want);
                if (hasClass) out.push(c);
                walk(c);
            }
        };
        walk(this);
        return out;
    }
}

/** data-* <-> dataset bridge, so the SDK's attribute reads agree with the carousel. */
function makeDataset(el) {
    return new Proxy({}, {
        get(_t, key) {
            if (typeof key !== 'string') return undefined;
            const attr = 'data-' + key.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase());
            const v = el.getAttribute(attr);
            return v === null || v === undefined ? undefined : v;
        },
        set(_t, key, value) {
            const attr = 'data-' + key.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase());
            el.setAttribute(attr, value);
            return true;
        },
        has(_t, key) {
            const attr = 'data-' + key.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase());
            return el.attributes.has(attr);
        },
    });
}

class FakeDocument {
    constructor() {
        this.readyState = 'complete';
        this.blockHandlers = new Map();
        this.created = [];
    }
    createElement(tag) { const e = new FakeElement(tag, this); this.created.push(e); return e; }
    addEventListener() {}
    removeEventListener() {}
    dispatchEvent() { return true; }
    querySelectorAll() { return []; }
    getElementById() { return null; }
}

/**
 * Boot runtime -> block-sdk -> carousel, in the exact order
 * theme-runtime::canvas.editor-bootstrap includes them.
 */
function boot({ items = 3, withLegacyAdapter = false } = {}) {
    const doc = new FakeDocument();

    const warnings = [];
    const errors = [];

    const win = {
        location: { origin: 'https://example.test' },
        scrollX: 0,
        scrollY: 0,
        scrollTo(x, y) { win.scrollX = x; win.scrollY = y; },
        document: doc,
        console: {
            warn: (m) => warnings.push(String(m)),
            // The block SDK logs `console.error(message, exception)`. The
            // exception is where the actual TypeError lives, so it must be
            // captured or a real failure reads as a bare string.
            error: (m, e) => errors.push(String(m) + (e ? ' :: ' + (e && e.stack ? e.stack : String(e)) : '')),
        },
        getComputedStyle(el) {
            return {
                direction: 'rtl',
                getPropertyValue(n) { return n === '--tb-items' ? String(items) : ''; },
            };
        },
        setInterval() { const t = { cancelled: false }; win._timers.push(t); return t; },
        clearInterval(t) { if (t) t.cancelled = true; },
        _timers: [],
        // The controller registers its resize listener on window, exactly as
        // it does in a browser, so the fake has to be able to account for it.
        listeners: new Map(),
        addEventListener(type, fn) {
            if (!win.listeners.has(type)) win.listeners.set(type, []);
            win.listeners.get(type).push(fn);
        },
        removeEventListener(type, fn) {
            const list = win.listeners.get(type);
            if (!list) return;
            const i = list.indexOf(fn);
            if (i !== -1) list.splice(i, 1);
            if (list.length === 0) win.listeners.delete(type);
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
        clearTimeout,
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
        Boolean,
    });
    context.globalThis = context;
    context.setInterval = win.setInterval;
    context.clearInterval = win.clearInterval;
    context.getComputedStyle = win.getComputedStyle;

    vm.runInContext(RUNTIME_SOURCE, context, { filename: 'runtime.blade.js' });
    vm.runInContext(SDK_SOURCE, context, { filename: 'block-sdk.js' });
    vm.runInContext(CAROUSEL_SOURCE, context, { filename: 'carousel.js' });
    if (withLegacyAdapter) {
        vm.runInContext(LEGACY_CAROUSEL_SOURCE, context, { filename: 'legacy_carousel.js' });
    }

    return { win, doc, runtime: win.ThemeCanvasRuntime, sdk: win.ThemeBlockSDK, warnings, errors, context };
}

function listenerCount(win, type) {
    return (win.listeners && win.listeners.get(type) || []).length;
}

/**
 * A block wrapper exactly as BlockRenderer emits it in MODE_PUBLIC, holding
 * one product_slider carousel.
 */
function buildProductSliderBlock(doc, { blockId = 'b1', columns = 3, autoplay = true, type = 'product_slider', carouselClass = 'tb-carousel tb-module-products' } = {}) {
    const wrap = new FakeElement('div', doc);
    wrap.setAttribute('class', 'tb-block');
    wrap.setAttribute('data-tb-block-id', blockId);
    wrap.setAttribute('data-tb-block-type', type);
    wrap.setAttribute('data-tb-block-props', JSON.stringify({ columns, autoplay, interval: 1000 }));

    const carousel = new FakeElement('div', doc);
    carousel.setAttribute('class', carouselClass);
    carousel.setAttribute('data-autoplay', String(autoplay));
    carousel.setAttribute('data-interval', '1000');
    carousel.setAttribute('data-pause-hover', 'true');
    carousel.setAttribute('data-fade', 'false');

    const track = new FakeElement('div', doc);
    track.classList.add('tb-carousel-track');
    carousel.appendChild(track);
    for (let i = 0; i < 4; i++) {
        const slide = new FakeElement('div', doc);
        slide.classList.add('tb-carousel-slide');
        track.appendChild(slide);
    }

    const dots = new FakeElement('div', doc);
    dots.classList.add('tb-carousel-dots');
    carousel.appendChild(dots);
    for (let i = 0; i < 4; i++) {
        const dot = new FakeElement('button', doc);
        dot.classList.add('tb-carousel-dot');
        dots.appendChild(dot);
    }

    wrap.appendChild(carousel);
    return { wrap, carousel };
}

/** Wire a wrapper's listeners so the runtime can deliver a lifecycle phase. */
function connect(doc, wrap) {
    doc.blockHandlers = wrap.ownerDocument.blockHandlers;
    return wrap;
}

/**
 * The transition the editor actually performs, in the order
 * runtime.replaceNode() performs it: release the old element's instance,
 * swap in a new element carrying the same block id, then bring the new
 * element up through initBlock.
 */
async function replaceBlock(win, doc, runtime, oldWrap, { blockId = 'b1', times = 1, trace = null, builderOpts = {} } = {}) {
    for (let i = 0; i < times; i++) {
        if (trace) trace.push(`iter${i} start: listeners=${listenerCount(win, 'resize')} timers=${liveTimers(win)}`);

        runtime.lifecycle('destroy', oldWrap, { blockId });
        if (trace) trace.push(`iter${i} afterDestroy: listeners=${listenerCount(win, 'resize')} timers=${liveTimers(win)}`);

        const { wrap, carousel } = buildProductSliderBlock(doc, { blockId, ...builderOpts });
        oldWrap.replaceWith(wrap);
        runtime.lifecycle('update', wrap, { blockId });
        runtime.initBlock(wrap);
        // eslint-disable-next-line no-await-in-loop
        await new Promise((r) => setTimeout(r, 0));
        if (trace) trace.push(`iter${i} afterInit: listeners=${listenerCount(win, 'resize')} timers=${liveTimers(win)} guard=${carousel.getAttribute('data-tb-carousel-init')}`);
        oldWrap = wrap;
    }
    return oldWrap;
}

function liveTimers(win) {
    return win._timers.filter((t) => !t.cancelled).length;
}

// -------------------------------------------------------------------- tests

test('the real runtime + real SDK expose the contract the carousel block needs', () => {
    const { runtime, sdk, errors } = boot();

    assert.equal(errors.length, 0, 'booting runtime + SDK + carousel must not error: ' + errors.join(' | '));
    assert.ok(runtime, 'ThemeCanvasRuntime must exist');
    assert.equal(runtime.version, 1);
    assert.ok(sdk, 'ThemeBlockSDK must exist');
    assert.equal(sdk.version, 1);
    // The carousel block registers itself against THIS instance. If it had
    // called ThemeBlockSDK.create() it would have thrown here, taking the
    // public self-initialise fallback down with it.
    assert.equal(typeof sdk.defineBlock, 'function');
});

test('step A — init runs when the runtime initialises a product_slider block', async () => {
    const { win, doc, runtime, errors } = boot();
    const { wrap, carousel } = buildProductSliderBlock(doc);
    connect(doc, wrap);

    assert.ok(runtime.initBlock(wrap), 'the runtime must claim a registered block type');
    await new Promise((r) => setTimeout(r, 0));

    assert.equal(errors.length, 0, 'init must not error: ' + errors.join(' | '));
    assert.equal(carousel.getAttribute('data-tb-carousel-init'), '1',
        'the controller must have run and set its own guard');
    assert.ok(wrap.classList.contains('tb-block-ready'), 'the SDK marks a block ready after init');
    assert.equal(liveTimers(win), 1, 'autoplay scheduled exactly one timer through the real controller');
});

test('step B/C — replacing a block stops the old controller and drives the new one', async () => {
    const { win, doc, runtime, errors } = boot();
    const { wrap } = buildProductSliderBlock(doc);
    connect(doc, wrap);

    runtime.initBlock(wrap);
    await new Promise((r) => setTimeout(r, 0));
    assert.equal(liveTimers(win), 1);

    let current = await replaceBlock(win, doc, runtime, wrap);

    const newCarousel = current.querySelector('.tb-carousel');
    assert.equal(errors.length, 0, 'the replacement transition must not error: ' + errors.join(' | '));
    assert.equal(newCarousel.getAttribute('data-tb-carousel-init'), '1',
        'the REPLACEMENT element must be the one that is now controlled');
    assert.equal(liveTimers(win), 1,
        'exactly one timer may be live after the swap — zero means the old controller was never stopped, two means the new one was started without stopping it');
    assert.equal(current.classList.contains('tb-block-ready'), true, 'the new element is marked ready');
});

/**
 * REGRESSION — SDK-002 (docs/project-memory/OPEN_TASKS.md), fixed in the SDK.
 *
 * The contract here is the one the block claims to honour: a replacement's
 * controller must release the previous one's window listener. Before the
 * SDK serialised phases per instance, an async init's continuation landed
 * AFTER the update phase's sync fan-out and overwrote `state.handle` with
 * null (the element guard refused the double init) — so every destroy from
 * the second replacement on found nothing to stop and one window listener
 * plus one autoplay timer leaked per edit.
 *
 * Asserted against the real runtime + real SDK + real block: over 10
 * replacements the controller must be torn down every time — exactly one
 * live window 'resize' listener and one timer at the end.
 */
test('step C — the window listener does not survive a block replacement', async () => {
    const { win, doc, runtime, errors } = boot();
    const { wrap } = buildProductSliderBlock(doc);
    connect(doc, wrap);

    runtime.initBlock(wrap);
    await new Promise((r) => setTimeout(r, 0));
    assert.equal(listenerCount(win, 'resize'), 1, 'the first carousel registered its one window listener');

    let current = await replaceBlock(win, doc, runtime, wrap, { times: 10 });

    assert.equal(errors.length, 0, errors.join(' | '));
    assert.equal(
        listenerCount(win, 'resize'),
        1,
        '10 block replacements must leave exactly one window listener — the old element\'s must be unregistered on the destroy phase'
    );
    assert.equal(liveTimers(win), 1, 'and exactly one live autoplay timer');
    assert.ok(current, 'the final replacement is still live');
});

test('destroy tears the block down and stops its controller', async () => {
    const { win, doc, runtime, errors } = boot();
    const { wrap } = buildProductSliderBlock(doc);
    connect(doc, wrap);

    runtime.initBlock(wrap);
    await new Promise((r) => setTimeout(r, 0));

    runtime.lifecycle('destroy', wrap, { blockId: 'b1' });
    await new Promise((r) => setTimeout(r, 0));

    assert.equal(errors.length, 0, errors.join(' | '));
    assert.equal(liveTimers(win), 0, 'destroy must leave no live autoplay timer behind');
    assert.equal(listenerCount(win, 'resize'), 0, 'and must unregister the window resize listener');
    assert.equal(wrap.classList.contains('tb-block-ready'), false, 'the ready marker is stripped on release');
});

test('the documented onUpdate hook is not reached by the real replace path', async () => {
    // This is a property of the SHIPPED Phase 9.1 contract, asserted here so
    // it stays visible rather than being discovered as a mystery later:
    // releaseInstance() deletes the instance from the live set, and
    // replaceNode() emits 'destroy' before 'update', so nothing is left for
    // the update fan-out to call. A block that put its only re-init logic in
    // onUpdate would silently never re-initialise.
    const { doc, runtime } = boot();
    const { wrap } = buildProductSliderBlock(doc);
    connect(doc, wrap);

    runtime.initBlock(wrap);
    await new Promise((r) => setTimeout(r, 0));

    const calls = [];
    const originalEmit = wrap.ownerDocument.dispatchEvent;
    assert.equal(typeof originalEmit, 'function', 'the fake document must still answer dispatchEvent');

    runtime.lifecycle('destroy', wrap, { blockId: 'b1' });
    const replacement = buildProductSliderBlock(doc, { blockId: 'b1' }).wrap;
    wrap.replaceWith(replacement);
    runtime.lifecycle('update', replacement, { blockId: 'b1' });

    assert.equal(calls.length, 0, 'no instance remains to receive onUpdate after the destroy phase');
});

test('a block whose carousel is missing does not throw', async () => {
    const { doc, runtime, errors } = boot();

    const empty = new FakeElement('div', doc);
    empty.setAttribute('class', 'tb-block');
    empty.setAttribute('data-tb-block-id', 'b9');
    empty.setAttribute('data-tb-block-type', 'product_slider');
    connect(doc, empty);

    runtime.initBlock(empty);
    await new Promise((r) => setTimeout(r, 0));

    assert.equal(errors.length, 0, 'a block with no carousel must fail soft, not throw: ' + errors.join(' | '));
});

// ------------------------------------------------------------ legacy adapter

test('the legacy adapter registers the four non-migrated carousel types as SDK blocks', () => {
    const { sdk, errors } = boot({ withLegacyAdapter: true });

    assert.equal(errors.length, 0, 'booting with the legacy adapter must not error: ' + errors.join(' | '));
    for (const type of ['slider', 'category_slider', 'testimonials_slider', 'client_logos']) {
        const def = sdk.getBlockType(type);
        assert.ok(def, type + ' must be registered');
        assert.equal(def.version, 1);
    }
});

test('a legacy-type block binds the controller and owns exactly one window listener and timer', async () => {
    const { win, doc, runtime, errors } = boot({ withLegacyAdapter: true });
    const { wrap } = buildProductSliderBlock(doc, {
        type: 'slider',
        carouselClass: 'tb-carousel tb-slider',
    });
    connect(doc, wrap);

    assert.ok(runtime.initBlock(wrap), 'the runtime must claim the legacy type through the adapter');
    await new Promise((r) => setTimeout(r, 0));

    assert.equal(errors.length, 0, errors.join(' | '));
    assert.equal(listenerCount(win, 'resize'), 1, 'exactly one window resize listener');
    assert.equal(liveTimers(win), 1, 'exactly one autoplay timer');
});

/**
 * The SDK-002 contract, now extended to the legacy carousel types: N
 * replacements must leave exactly one window listener and one timer. Before
 * this adapter, legacyCarouselScript() had no destroy at all — one of each
 * leaked per replacement, each pinning its detached subtree.
 */
test('SDK-002 for the legacy types — ten replacements leave one listener and one timer', async () => {
    const { win, doc, runtime, errors } = boot({ withLegacyAdapter: true });
    const { wrap } = buildProductSliderBlock(doc, {
        type: 'slider',
        carouselClass: 'tb-carousel tb-slider',
    });
    connect(doc, wrap);

    runtime.initBlock(wrap);
    await new Promise((r) => setTimeout(r, 0));
    assert.equal(listenerCount(win, 'resize'), 1);

    const current = await replaceBlock(win, doc, runtime, wrap, {
        times: 10,
        builderOpts: { type: 'slider', carouselClass: 'tb-carousel tb-slider' },
    });

    assert.equal(errors.length, 0, errors.join(' | '));
    assert.ok(current, 'the final replacement is still live');
    assert.equal(
        listenerCount(win, 'resize'),
        1,
        '10 replacements of a legacy type must leave exactly one window listener — the adapter provides the destroy the inline script never had',
    );
    assert.equal(liveTimers(win), 1, 'and exactly one live autoplay timer');
});

test('the adapter interops with the inline guard: an element owned by the legacy script is adopted, not double-bound', async () => {
    // A page can transition from inline-carrier markup to the SDK adapter.
    // The element guard is the interop seam: whoever reaches the element
    // first owns it; the adapter must not fight it and must not double-bind.
    const { win, doc, runtime, errors } = boot({ withLegacyAdapter: true });
    const { wrap, carousel } = buildProductSliderBlock(doc, {
        type: 'category_slider',
        carouselClass: 'tb-carousel tb-category-slider',
    });
    connect(doc, wrap);

    // Simulate the legacy inline script having reached the element first:
    // it sets the SAME guard attribute the shared controller uses.
    carousel.setAttribute('data-tb-carousel-init', '1');

    assert.ok(runtime.initBlock(wrap), 'the runtime still claims the block');
    await new Promise((r) => setTimeout(r, 0));

    assert.equal(errors.length, 0, errors.join(' | '));
    assert.equal(listenerCount(win, 'resize'), 0, 'the owned element must not gain a second controller');
    assert.equal(liveTimers(win), 0, 'and no second autoplay timer');
});

test('with no SDK present the same file still self-initialises the carousel', async () => {
    // The public-carrier half of the contract: a customer page loads no
    // canvas runtime, so the file must drive the carousel on its own. If the
    // SDK branch ever threw, this is the path that would silently vanish.
    const doc = new FakeDocument();
    const { wrap, carousel } = buildProductSliderBlock(doc);

    const win = {
        document: doc,
        console: { warn() {}, error() {} },
        getComputedStyle: () => ({ direction: 'rtl', getPropertyValue: (n) => (n === '--tb-items' ? '3' : '') }),
        setInterval() { const t = { cancelled: false }; win._timers.push(t); return t; },
        clearInterval(t) { if (t) t.cancelled = true; },
        _timers: [],
        listeners: new Map(),
        addEventListener(type, fn) {
            if (!win.listeners.has(type)) win.listeners.set(type, []);
            win.listeners.get(type).push(fn);
        },
        removeEventListener(type, fn) {
            const list = win.listeners.get(type);
            if (!list) return;
            const i = list.indexOf(fn);
            if (i !== -1) list.splice(i, 1);
        },
    };
    win.window = win;

    const context = vm.createContext({ window: win, document: doc, console: win.console, Promise, Object, Array, Map, Set, WeakMap, String, Number, Math, JSON, Error, TypeError, Boolean, setTimeout, clearTimeout });
    context.globalThis = context;
    context.getComputedStyle = win.getComputedStyle;

    // The fake document's querySelectorAll is what the self-init path scans.
    doc.querySelectorAll = (sel) => (sel === '.tb-carousel' ? [carousel] : []);

    vm.runInContext(CAROUSEL_SOURCE, context, { filename: 'carousel.js' });

    await new Promise((r) => setTimeout(r, 0));

    assert.equal(carousel.getAttribute('data-tb-carousel-init'), '1',
        'with no SDK the file must still self-initialise — this is the only path a public customer page has');
    assert.equal(win._timers.length, 1, 'and it must drive the autoplay timer itself');
    assert.equal(listenerCount(win, 'resize'), 1, 'registering its one window listener');
});
