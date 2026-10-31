import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

// WU-B (2026-10-31) — the shared carousel controller's AUTOPLAY GATES and
// the fade-mode swipe. Like carousel-lifecycle.test.mjs, this loads the real
// carousel.js source into a fake window rather than a reimplementation.
//
// Autoplay may only run when it can be watched and is wanted: never under
// prefers-reduced-motion, never in a hidden tab, never while the carousel is
// out of view, never under the pointer or keyboard focus. Every listener the
// gates add outside the element must be gone after destroy().

const pkgDir = fileURLToPath(new URL('../../packages/saas-platform/modules/theme-runtime/', import.meta.url));
const source = fs.readFileSync(path.join(pkgDir, 'resources/js/blocks/carousel.js'), 'utf8');

// ------------------------------------------------------------------ harness

function listenerBag() {
    const map = new Map();
    return {
        map,
        addEventListener(type, fn) {
            if (!map.has(type)) map.set(type, []);
            map.get(type).push(fn);
        },
        removeEventListener(type, fn) {
            const list = map.get(type);
            if (!list) return;
            const i = list.indexOf(fn);
            if (i !== -1) list.splice(i, 1);
            if (list.length === 0) map.delete(type);
        },
        fire(type, event = {}) { for (const fn of [...(map.get(type) || [])]) fn(event); },
        count(type) { return (map.get(type) || []).length; },
    };
}

function makeElement(classes = []) {
    const bag = listenerBag();
    const el = {
        dataset: {},
        style: {},
        attributes: new Map(),
        childNodes: [],
        clientWidth: 400,
        classList: {
            _set: new Set(classes),
            toggle(name, force) { force ? this._set.add(name) : this._set.delete(name); },
            contains(name) { return this._set.has(name); },
        },
        setAttribute(n, v) { this.attributes.set(n, String(v)); },
        getAttribute(n) { return this.attributes.has(n) ? this.attributes.get(n) : null; },
        addEventListener: bag.addEventListener,
        removeEventListener: bag.removeEventListener,
        fire: bag.fire,
        setPointerCapture() {},
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
        add(...children) { for (const c of children) this.childNodes.push(c); return this; },
    };
    return el;
}

function buildCarousel({ slides = 3, autoplay = 'true', fade = 'false' } = {}) {
    const root = makeElement(['tb-carousel']);
    root.dataset.autoplay = autoplay;
    root.dataset.interval = '1000';
    root.dataset.pauseHover = 'true';
    root.dataset.fade = fade;
    const viewport = makeElement(['tb-carousel-viewport']);
    const track = makeElement(['tb-carousel-track']);
    const slideEls = [];
    const dots = [];
    for (let i = 0; i < slides; i++) {
        const slide = makeElement(['tb-carousel-slide']);
        slideEls.push(slide);
        track.add(slide);
        const dot = makeElement(i === 0 ? ['tb-carousel-dot', 'is-active'] : ['tb-carousel-dot']);
        dots.push(dot);
    }
    const dotsWrap = makeElement(['tb-carousel-dots']).add(...dots);
    root.add(viewport.add(track), dotsWrap);
    return { root, viewport, track, slideEls, dots };
}

function loadController({ reducedMotion = false } = {}) {
    const docBag = listenerBag();
    const motion = { matches: reducedMotion, ...listenerBag() };
    const observers = [];
    const winBag = listenerBag();
    const win = {
        document: { readyState: 'complete', hidden: false, querySelectorAll: () => [], ...docBag },
        timers: new Set(),
        setInterval(fn, ms) { const t = { fn, ms }; win.timers.add(t); return t; },
        clearInterval(t) { win.timers.delete(t); },
        addEventListener: winBag.addEventListener,
        removeEventListener: winBag.removeEventListener,
        matchMedia(query) {
            assert.equal(query, '(prefers-reduced-motion: reduce)');
            return motion;
        },
        IntersectionObserver: class {
            constructor(cb, opts) { this.cb = cb; this.opts = opts; this.targets = []; this.disconnected = false; observers.push(this); }
            observe(el) { this.targets.push(el); }
            disconnect() { this.disconnected = true; }
            report(isIntersecting) { this.cb([{ isIntersecting, target: this.targets[0] }]); }
        },
        getComputedStyle() {
            return { direction: 'rtl', getPropertyValue: (n) => (n === '--tb-items' ? '1' : '') };
        },
        console,
    };
    vm.runInNewContext(source, { window: win, globalThis: win, console });
    return { win, docBag, motion, observers, winBag, controller: win.ThemeCarousel };
}

// -------------------------------------------------------------------- tests

test('prefers-reduced-motion: autoplay never starts, and resumes when the preference is lifted', () => {
    const { win, motion, controller } = loadController({ reducedMotion: true });
    const { root } = buildCarousel();
    controller.initCarousel(root);
    assert.equal(win.timers.size, 0, 'reduced motion must not schedule autoplay');

    motion.matches = false;
    motion.fire('change');
    assert.equal(win.timers.size, 1, 'lifting the preference re-enables autoplay');
});

test('a hidden tab stops autoplay and a visible one restarts it', () => {
    const { win, docBag, controller } = loadController();
    const { root } = buildCarousel();
    controller.initCarousel(root);
    assert.equal(win.timers.size, 1);

    win.document.hidden = true;
    docBag.fire('visibilitychange');
    assert.equal(win.timers.size, 0, 'hidden tab must stop the timer');

    win.document.hidden = false;
    docBag.fire('visibilitychange');
    assert.equal(win.timers.size, 1, 'visible again must restart it');
});

test('scrolling out of view stops autoplay, back into view restarts it', () => {
    const { win, observers, controller } = loadController();
    const { root } = buildCarousel();
    controller.initCarousel(root);
    assert.equal(observers.length, 1);
    assert.equal(observers[0].targets[0], root, 'the observer watches the carousel root');
    assert.equal(observers[0].opts.threshold, 0.1);

    observers[0].report(false);
    assert.equal(win.timers.size, 0);
    observers[0].report(true);
    assert.equal(win.timers.size, 1);
});

test('a visibility change cannot restart autoplay under the pointer', () => {
    const { win, docBag, controller } = loadController();
    const { root } = buildCarousel();
    controller.initCarousel(root);

    root.fire('mouseenter');
    assert.equal(win.timers.size, 0);
    win.document.hidden = true; docBag.fire('visibilitychange');
    win.document.hidden = false; docBag.fire('visibilitychange');
    assert.equal(win.timers.size, 0, 'still hovered: no autoplay');

    root.fire('mouseleave');
    assert.equal(win.timers.size, 1);
});

test('destroy() removes the document, media-query and observer hooks', () => {
    const { win, docBag, motion, observers, winBag, controller } = loadController();
    const { root } = buildCarousel();
    const handle = controller.initCarousel(root);
    assert.equal(docBag.count('visibilitychange'), 1);
    assert.equal(motion.count('change'), 1);

    handle.destroy();
    assert.equal(docBag.count('visibilitychange'), 0);
    assert.equal(motion.count('change'), 0);
    assert.equal(winBag.count('resize'), 0);
    assert.equal(observers[0].disconnected, true);
    assert.equal(win.timers.size, 0);
});

test('dots switch the is-active class, never an inline colour', () => {
    const { controller } = loadController();
    const { root, dots } = buildCarousel({ autoplay: 'false' });
    controller.initCarousel(root);

    dots[2].fire('click');
    assert.deepEqual(dots.map((d) => d.classList.contains('is-active')), [false, false, true]);
    assert.deepEqual(dots.map((d) => d.getAttribute('aria-current')), ['false', 'false', 'true']);
    assert.equal(dots[2].style.background, undefined, 'no inline background');
});

test('fade mode swipes: a long swipe to the reading end shows the next slide without moving the track', () => {
    const { controller } = loadController();
    const { root, viewport, track, slideEls } = buildCarousel({ fade: 'true', autoplay: 'false' });
    controller.initCarousel(root);
    assert.equal(slideEls[0].style.opacity, '1');

    const target = { closest: () => null };
    viewport.fire('pointerdown', { pointerType: 'touch', button: 0, clientX: 100, clientY: 50, pointerId: 7, target });
    viewport.fire('pointermove', { pointerId: 7, clientX: 220, clientY: 52 }); // RTL: swipe right = next
    viewport.fire('pointerup', { pointerId: 7 });

    assert.equal(slideEls[1].style.opacity, '1', 'the next slide is shown');
    assert.equal(slideEls[0].style.opacity, '0');
    assert.equal(track.style.transform, undefined, 'fade never translates the track');
    assert.equal(track.style.transition, undefined, 'and never touches its transition');
});

test('a short fade swipe changes nothing', () => {
    const { controller } = loadController();
    const { root, viewport, slideEls } = buildCarousel({ fade: 'true', autoplay: 'false' });
    controller.initCarousel(root);

    const target = { closest: () => null };
    viewport.fire('pointerdown', { pointerType: 'touch', button: 0, clientX: 100, clientY: 50, pointerId: 3, target });
    viewport.fire('pointermove', { pointerId: 3, clientX: 140, clientY: 50 });
    viewport.fire('pointerup', { pointerId: 3 });

    assert.equal(slideEls[0].style.opacity, '1');
    assert.equal(slideEls[1].style.opacity, '0');
});
