import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

// Phase 9.3 — the countdown adapter's LIFECYCLE contract.
//
// These tests load the REAL countdown_adapter.js source, exactly as a page
// would receive it. A hand-written stub would pass while the shipped file
// leaked timers, which is precisely the regression this migration is meant to
// prevent.
//
// The countdown is the simpler of the two migrated components: it has no
// window listener, so its only teardown obligation is the interval. That is
// exactly what the pre-migration inline copy could not do — it assigned the
// interval to a variable it only ever READ inside tick(), to clear it on
// expiry, and nothing could ever cancel it when the block itself was
// replaced. Every editor-side replacement of a countdown therefore stranded a
// one-second ticker driving a detached DOM subtree.

const pkgDir = fileURLToPath(new URL('../../packages/saas-platform/modules/theme-runtime/', import.meta.url));
const source = fs.readFileSync(path.join(pkgDir, 'resources/js/blocks/countdown_adapter.js'), 'utf8');

// ------------------------------------------------------------------ harness

function makeElement(tag, doc) {
    const el = {
        tagName: String(tag).toLowerCase(),
        ownerDocument: doc,
        dataset: {},
        style: { display: '' },
        classList: {
            _set: new Set(),
            add(...n) { n.forEach((x) => this._set.add(x)); },
            contains(name) { return this._set.has(name); },
        },
        textContent: '',
        childNodes: [],
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
 * Build the DOM renderCountdown() writes for a countdown with an end date:
 * the root, the four unit spans, the timer wrapper and the expired hint.
 */
function buildCountdown(doc, endDate) {
    const root = makeElement('div', doc);
    root.dataset.endDate = endDate;

    const timer = makeElement('div', doc);
    timer.classList.add('tb-countdown__timer');
    root.add(timer);

    const expired = makeElement('p', doc);
    expired.classList.add('tb-countdown__expired');
    root.add(expired);

    for (const unit of ['d', 'h', 'm', 's']) {
        const span = makeElement('span', doc);
        span.classList.add('tb-countdown__' + unit);
        timer.add(span);
    }

    return { root, timer, expired };
}

/**
 * Run countdown_adapter.js in a fresh fake window with a controllable clock,
 * and hand back the live timer set so a test can assert on leaks.
 */
function loadAdapter({ now = Date.parse('2026-01-01T00:00:00Z') } = {}) {
    const win = {
        document: { readyState: 'complete', querySelectorAll: () => [], addEventListener() {} },
        timers: new Set(),
        setInterval(fn, ms) { const t = { fn, ms, cancelled: false }; win.timers.add(t); return t; },
        clearInterval(t) { if (t) { t.cancelled = true; win.timers.delete(t); } },
    };

    // The sandbox gets its own Date so `Date.now()` inside the adapter can be
    // moved without touching the host clock.
    let clock = now;
    class FakeDate extends Date {
        constructor(...args) { super(...(args.length ? args : [clock])); }
        static now() { return clock; }
    }
    win.Date = FakeDate;

    vm.runInNewContext(source, { window: win, globalThis: win, console, Date: FakeDate });
    return {
        win,
        controller: win.ThemeCountdown,
        advance(ms) { clock += ms; for (const t of [...win.timers]) t.fn(); },
    };
}

// -------------------------------------------------------------------- tests

test('a countdown with a future date arms exactly one timer', () => {
    const { win, controller } = loadAdapter();
    const { root } = buildCountdown(win.document, '2026-01-02T00:00:00Z');

    const handle = controller.initCountdown(root);

    assert.ok(handle, 'init must return a handle');
    assert.equal(win.timers.size, 1, 'exactly one interval must be scheduled');
});

test('destroy() clears the interval — the obligation the inline copy could not meet', () => {
    const { win, controller } = loadAdapter();
    const { root } = buildCountdown(win.document, '2026-01-02T00:00:00Z');

    const handle = controller.initCountdown(root);
    assert.equal(win.timers.size, 1);

    handle.destroy();

    assert.equal(
        win.timers.size,
        0,
        'destroy() must clear the interval — this is the whole reason the ticker moved into a returnable handle'
    );
});

test('repeated init/destroy cycles do not accumulate timers', () => {
    const { win, controller } = loadAdapter();

    // The leak this file exists to guard: without a reachable interval handle,
    // every editor-side replacement of a countdown strands another ticker.
    for (let i = 0; i < 100; i++) {
        const { root } = buildCountdown(win.document, '2026-01-02T00:00:00Z');
        controller.initCountdown(root).destroy();
    }

    assert.equal(win.timers.size, 0, '100 init/destroy cycles must leave zero live timers');
});

test('a countdown never destroyed does not double-initialise', () => {
    const { win, controller } = loadAdapter();
    const { root } = buildCountdown(win.document, '2026-01-02T00:00:00Z');

    controller.initCountdown(root);
    const second = controller.initCountdown(root);

    assert.equal(second, null, 'the data-tb-countdown-init guard must reject a second init');
    assert.equal(win.timers.size, 1, 'and must not arm a second interval');
});

test('an unparseable end date arms no timer and does not poison the guard', () => {
    const { win, controller } = loadAdapter();
    const { root } = buildCountdown(win.document, 'not-a-date');

    assert.equal(controller.initCountdown(root), null, 'a bad date is not a live countdown');
    assert.equal(win.timers.size, 0, 'and must arm nothing');

    // The pre-migration inline copy set its init guard BEFORE validating the
    // date, so a corrected value could never revive the block. The adapter
    // validates first precisely so this recovery stays possible.
    assert.equal(root.dataset.tbCountdownInit, undefined, 'a rejected countdown must not be marked initialised');

    root.dataset.endDate = '2026-01-02T00:00:00Z';
    const revived = controller.initCountdown(root);
    assert.ok(revived, 'a corrected end date must still be able to initialise');
    assert.equal(win.timers.size, 1);
    revived.destroy();
});

test('reaching the target date cancels the timer on its own', () => {
    const { win, controller, advance } = loadAdapter();
    const { root, timer, expired } = buildCountdown(win.document, '2026-01-01T00:10:00Z');

    controller.initCountdown(root);
    assert.equal(win.timers.size, 1);

    advance(10 * 60 * 1000);

    assert.equal(win.timers.size, 0, 'expiry must stop the ticker without waiting for a destroy');
    assert.equal(timer.style.display, 'none', 'the timer must be hidden');
    assert.equal(expired.style.display, 'block', 'and the expired hint shown');
});

test('a date already in the past arms no timer at all', () => {
    const { win, controller } = loadAdapter();
    const { root, expired } = buildCountdown(win.document, '2020-01-01T00:00:00Z');

    const handle = controller.initCountdown(root);

    assert.ok(handle, 'an expired countdown still returns a handle');
    assert.equal(
        win.timers.size,
        0,
        'nothing to count means nothing to schedule — arming a timer here would leave it live for a second '
        + 'before the first tick cancelled it'
    );
    assert.equal(expired.style.display, 'block', 'and the expired state must be applied immediately');
});

test('the countdown renders its own values without touching other blocks', () => {
    const { win, controller, advance } = loadAdapter();
    const { root, timer } = buildCountdown(win.document, '2026-01-01T02:03:04Z');

    controller.initCountdown(root);

    const span = (u) => root.querySelector('.tb-countdown__' + u).textContent;
    // Phase 3 (ADR-068): two Persian digits per unit.
    assert.deepEqual([span('d'), span('h'), span('m'), span('s')], ['۰۰', '۰۲', '۰۳', '۰۴']);

    advance(1000);
    assert.deepEqual([span('d'), span('h'), span('m'), span('s')], ['۰۰', '۰۲', '۰۳', '۰۳']);
    assert.equal(timer.style.display, '', 'a running countdown must keep its timer visible');
});

test('a root with no end date is not initialised', () => {
    const { win, controller } = loadAdapter();
    const { root } = buildCountdown(win.document, undefined);

    assert.equal(controller.initCountdown(root), null);
    assert.equal(win.timers.size, 0, 'the server emits no script for these, so they must stay inert');
});