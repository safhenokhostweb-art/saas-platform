import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

// Phase 9 / Step 2 — the shared carousel controller's LIFECYCLE contract.
//
// These tests load the REAL carousel.js source, exactly as a page would
// receive it, rather than a reimplementation. A hand-written stub would pass
// happily while the shipped file leaked, which is the whole failure mode
// under test here.
//
// The property that matters: initCarousel() returns a handle whose destroy()
// must leave NOTHING behind. A carousel element that gets replaced (the SDK
// calls onUpdate) is detached from the document, but any listener the
// controller registered on `window` outlives it and pins the whole detached
// subtree through its closure. Do that a hundred times and the page holds a
// hundred dead carousels for its lifetime.

const pkgDir = fileURLToPath(new URL('../../packages/saas-platform/modules/theme-runtime/', import.meta.url));
const source = fs.readFileSync(path.join(pkgDir, 'resources/js/blocks/carousel.js'), 'utf8');

// ------------------------------------------------------------------ harness

class FakeStyle {
    constructor() { this.props = new Map(); }
    setProperty(name, value) { this.props.set(name, value); }
    getPropertyValue(name) { return this.props.get(name) || ''; }
}

function makeElement(tag, doc) {
    const el = {
        tagName: String(tag).toLowerCase(),
        ownerDocument: doc,
        dataset: {},
        style: new FakeStyle(),
        attributes: new Map(),
        childNodes: [],
        listeners: new Map(),
        textContent: '',
        classList: {
            _set: new Set(),
            toggle(name, force) { force ? this._set.add(name) : this._set.delete(name); },
            contains(name) { return this._set.has(name); },
        },
        setAttribute(n, v) { this.attributes.set(n, String(v)); },
        getAttribute(n) { return this.attributes.has(n) ? this.attributes.get(n) : null; },
        addEventListener(type, fn) {
            if (!this.listeners.has(type)) this.listeners.set(type, []);
            this.listeners.get(type).push(fn);
        },
        removeEventListener(type, fn) {
            const list = this.listeners.get(type);
            if (!list) return;
            const i = list.indexOf(fn);
            if (i !== -1) list.splice(i, 1);
            if (list.length === 0) this.listeners.delete(type);
        },
        listenerCount(type) { return (this.listeners.get(type) || []).length; },
        querySelector(sel) { return this.querySelectorAll(sel)[0] || null; },
        querySelectorAll(sel) {
            const want = sel.replace(/^\./, '');
            const out = [];
            const walk = (node) => {
                for (const child of node.childNodes) {
                    if (child.classList.contains(want)) out.push(child);
                    walk(child);
                }
            };
            walk(this);
            return out;
        },
        add(...children) { for (const c of children) { c.parentNode = this; this.childNodes.push(c); } return this; },
    };
    return el;
}

/**
 * Build a minimal but faithful carousel DOM: a root carrying the data-*
 * attributes the server writes, a track, and N slides.
 */
function buildCarousel(doc, { slides = 4, autoplay = 'true', interval = '1000' } = {}) {
    const root = makeElement('div', doc);
    root.dataset.autoplay = autoplay;
    root.dataset.interval = interval;
    root.dataset.pauseHover = 'true';
    root.dataset.fade = 'false';

    const track = makeElement('div', doc);
    track.classList._set.add('tb-carousel-track');
    root.add(track);

    for (let i = 0; i < slides; i++) {
        const slide = makeElement('div', doc);
        slide.classList._set.add('tb-carousel-slide');
        track.add(slide);
    }

    return { root, track };
}

/**
 * Run carousel.js in a fresh fake window. Returns the controller plus the
 * window itself so a test can inspect what was registered on it.
 */
function loadController({ items = 3 } = {}) {
    const win = {
        document: { readyState: 'complete', querySelectorAll: () => [], addEventListener() {} },
        listeners: new Map(),
        timers: new Set(),
        setInterval(fn, ms) { const t = { fn, ms, cancelled: false }; win.timers.add(t); return t; },
        clearInterval(t) { if (t) { t.cancelled = true; win.timers.delete(t); } },
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
        getComputedStyle(el) {
            return {
                direction: 'rtl',
                getPropertyValue(name) { return name === '--tb-items' ? String(items) : ''; },
            };
        },
        console,
        setTimeout,
        clearTimeout,
    };

    vm.runInNewContext(source, { window: win, globalThis: win, console });
    return { win, controller: win.ThemeCarousel };
}

function windowListenerCount(win, type) {
    return (win.listeners.get(type) || []).length;
}

// -------------------------------------------------------------------- tests

test('the controller registers exactly one window resize listener per carousel', () => {
    const { win, controller } = loadController();
    const { root } = buildCarousel(win.document);

    assert.equal(windowListenerCount(win, 'resize'), 0, 'nothing is registered before init');
    controller.initCarousel(root);
    assert.equal(windowListenerCount(win, 'resize'), 1, 'init must register the resize listener');
});

test('destroy() removes the window resize listener the carousel registered', () => {
    const { win, controller } = loadController();
    const { root } = buildCarousel(win.document);

    const handle = controller.initCarousel(root);
    assert.ok(handle, 'init must return a handle');

    handle.destroy();

    assert.equal(
        windowListenerCount(win, 'resize'),
        0,
        'destroy() must unregister the window resize listener — it is the one listener that outlives the element, and its closure pins the whole detached carousel'
    );
});

test('destroy() clears the autoplay timer', () => {
    const { win, controller } = loadController();
    const { root } = buildCarousel(win.document, { autoplay: 'true' });

    const handle = controller.initCarousel(root);
    assert.equal(win.timers.size, 1, 'autoplay must schedule a timer');

    handle.destroy();
    assert.equal(win.timers.size, 0, 'destroy() must clear the autoplay timer');
});

test('a carousel that is never destroyed does not double-initialise', () => {
    const { win, controller } = loadController();
    const { root } = buildCarousel(win.document);

    controller.initCarousel(root);
    const handle = controller.initCarousel(root);

    assert.equal(handle, null, 'the data-tb-carousel-init guard must reject a second init');
    assert.equal(windowListenerCount(win, 'resize'), 1, 'and must not register a second resize listener');
});

test('repeated init/destroy cycles do not accumulate window listeners', () => {
    const { win, controller } = loadController();

    // This is the leak the whole test exists for: without a real unregister,
    // every replacement of a carousel element strands another permanent
    // resize listener holding a detached DOM subtree.
    for (let i = 0; i < 100; i++) {
        const { root } = buildCarousel(win.document);
        const handle = controller.initCarousel(root);
        handle.destroy();
    }

    assert.equal(
        windowListenerCount(win, 'resize'),
        0,
        '100 init/destroy cycles must leave zero window listeners behind'
    );
    assert.equal(win.timers.size, 0, 'and zero live timers');
});

test('the autoplay timer is not scheduled when autoplay is off', () => {
    const { win, controller } = loadController();
    const { root } = buildCarousel(win.document, { autoplay: 'false' });

    const handle = controller.initCarousel(root);

    assert.equal(win.timers.size, 0, 'data-autoplay="false" must not start a timer');
    handle.destroy();
    assert.equal(windowListenerCount(win, 'resize'), 0);
});
