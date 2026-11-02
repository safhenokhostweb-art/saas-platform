/*
 * ADR-076 — the Canvas page-tab strip, shared by both editors.
 *
 * The page tabs scroll horizontally inside their own viewport instead of
 * pushing the toolbar wider than the screen. Chevrons appear only on an edge
 * that still hides tabs, the mouse wheel scrolls the strip, and the active
 * tab is scrolled into view whenever it changes or a page is added.
 *
 * Markup (theme-runtime::canvas.tab-strip documents it):
 *   [data-tb-tabstrip] > [data-tbts-dir="start"], [data-tbts-viewport], [data-tbts-dir="end"]
 * The active tab carries aria-current="page".
 *
 * edges(), step() and revealDelta() are pure and unit-tested
 * (tests/js/canvas-tab-strip.test.mjs). scrollLeft follows the standard
 * model: 0 at the inline start, negative toward the end in RTL.
 */
(function (root) {
    'use strict';

    var EPSILON = 2;

    /** Which edges still hide tabs, and whether the strip overflows at all. */
    function edges(scrollLeft, scrollWidth, clientWidth, rtl) {
        var max = Math.max(0, scrollWidth - clientWidth);
        var travelled = Math.min(max, Math.max(0, rtl ? -scrollLeft : scrollLeft));
        return {
            overflow: max > EPSILON,
            start: travelled > EPSILON,
            end: travelled < max - EPSILON,
        };
    }

    /** The physical scrollLeft delta for one chevron press toward 'start' or 'end'. */
    function step(direction, clientWidth, rtl) {
        var amount = Math.max(120, Math.round(clientWidth * 0.8));
        return (direction === 'end' ? 1 : -1) * amount * (rtl ? -1 : 1);
    }

    /** The scrollLeft delta that brings an item fully into view (physical px, with a margin for the chevrons). */
    function revealDelta(itemLeft, itemRight, viewLeft, viewRight, margin) {
        if (itemRight - itemLeft > viewRight - viewLeft - 2 * margin) {
            return itemLeft - viewLeft - margin;
        }
        if (itemLeft < viewLeft + margin) {
            return itemLeft - viewLeft - margin;
        }
        if (itemRight > viewRight - margin) {
            return itemRight - viewRight + margin;
        }
        return 0;
    }

    function mount(el) {
        if (!el || el.__tbTabStrip) {
            return el && el.__tbTabStrip;
        }
        var viewport = el.querySelector('[data-tbts-viewport]');
        if (!viewport) {
            return null;
        }
        var buttons = {
            start: el.querySelector('[data-tbts-dir="start"]'),
            end: el.querySelector('[data-tbts-dir="end"]'),
        };
        var win = el.ownerDocument.defaultView || root;
        var reduceMotion = !!(win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches);

        function rtl() {
            return win.getComputedStyle(viewport).direction === 'rtl';
        }
        function state() {
            return edges(viewport.scrollLeft, viewport.scrollWidth, viewport.clientWidth, rtl());
        }
        function update() {
            var s = state();
            el.toggleAttribute('data-overflow', s.overflow);
            if (buttons.start) buttons.start.hidden = !s.start;
            if (buttons.end) buttons.end.hidden = !s.end;
        }
        function scrollByDelta(delta) {
            if (delta) {
                viewport.scrollBy({ left: delta, behavior: reduceMotion ? 'auto' : 'smooth' });
            }
        }
        function reveal() {
            var active = viewport.querySelector('[aria-current="page"]');
            if (!active) {
                return;
            }
            var item = active.closest('[data-canvas-page-tab]') || active;
            var r = item.getBoundingClientRect();
            var v = viewport.getBoundingClientRect();
            scrollByDelta(revealDelta(r.left, r.right, v.left, v.right, 36));
        }

        ['start', 'end'].forEach(function (direction) {
            var button = buttons[direction];
            if (button) {
                button.addEventListener('click', function () {
                    scrollByDelta(step(direction, viewport.clientWidth, rtl()));
                });
            }
        });
        viewport.addEventListener('scroll', update, { passive: true });
        // A vertical wheel scrolls the strip; trackpads already scroll it
        // sideways, and the page keeps the wheel once an edge is reached.
        viewport.addEventListener('wheel', function (event) {
            if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
                return;
            }
            var s = state();
            var towardEnd = event.deltaY > 0;
            if (!s.overflow || (towardEnd && !s.end) || (!towardEnd && !s.start)) {
                return;
            }
            event.preventDefault();
            viewport.scrollLeft += event.deltaY * (rtl() ? -1 : 1);
        }, { passive: false });

        if (win.ResizeObserver) {
            var resize = new win.ResizeObserver(update);
            resize.observe(el);
            resize.observe(viewport);
        } else {
            win.addEventListener('resize', update);
        }
        if (win.MutationObserver) {
            new win.MutationObserver(function (records) {
                var moved = records.some(function (r) { return r.type === 'attributes' || r.addedNodes.length > 0; });
                win.requestAnimationFrame(function () {
                    update();
                    if (moved) reveal();
                });
            }).observe(viewport, { childList: true, subtree: true, attributes: true, attributeFilter: ['aria-current'] });
        }

        var api = { update: update, reveal: reveal };
        el.__tbTabStrip = api;
        win.requestAnimationFrame(function () {
            update();
            reveal();
        });
        return api;
    }

    function mountAll(doc) {
        var list = (doc || root.document).querySelectorAll('[data-tb-tabstrip]');
        for (var i = 0; i < list.length; i++) {
            mount(list[i]);
        }
    }

    root.TbTabStrip = { version: 1, edges: edges, step: step, revealDelta: revealDelta, mount: mount, mountAll: mountAll };

    if (root.document) {
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', function () { mountAll(root.document); });
        } else {
            mountAll(root.document);
        }
    }
})(typeof window !== 'undefined' ? window : globalThis);
