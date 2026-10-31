/*!
 * Shared carousel controller — Phase 9 / Step 2
 * ==============================================
 * The extracted client logic of BlockRenderer::renderCarousel(), moved out
 * of the PHP string that used to inline it into EVERY carousel instance.
 *
 * WHY THIS FILE EXISTS
 *   renderCarousel() used to emit the whole controller inline, per instance,
 *   with the block id baked in. A page with three carousels shipped three
 *   byte-identical ~6.4 KB copies. This is the one shared implementation.
 *
 * TWO CARRIERS, ONE LOGIC
 *   This file is reached two different ways and must behave identically:
 *
 *   1. SDK present (canvas editor + live preview, which load
 *      theme-runtime::canvas.runtime and ...block-sdk). Then it registers
 *      CarouselBlock, a real BaseBlock subclass, for the `product_slider`
 *      type. The SDK owns lifecycle (and serialises phases per instance,
 *      so a replacement's destroy -> init -> update ordering is
 *      guaranteed); ctx.$ scopes every query to the block root, so a
 *      carousel can never reach into a neighbour's DOM.
 *
 *   2. SDK absent (every PUBLIC customer page — no public layout loads the
 *      canvas runtime, see canvas-preview/canvas/canvas-live-preview for the
 *      only three includes). Then it self-initialises by scanning the
 *      document for `.tb-carousel` roots.
 *
 *   Interop is the `data-tb-carousel-init` guard, which is the SAME guard the
 *   pre-migration inline scripts already set. A carousel therefore cannot be
 *   initialised twice no matter which carrier reaches it first, so migrating
 *   one type can never double-bind the types that are not migrated yet.
 *
 * MIGRATION STATUS — Phase 9.2, COMPLETE
 *   Every renderCarousel() caller now ships this file plus
 *   legacy_carousel.js; the pre-migration inline copy is deleted from
 *   BlockRenderer. This file registers `product_slider`; the adapter
 *   registers `slider`, `category_slider`, `testimonials_slider` and
 *   `client_logos`, so all types go through the SDK and all of them have
 *   the destroy() the inline copies never had. Both carriers run the same
 *   controller behind the same guard, so a bug fixed here reaches every
 *   type.
 */
(function (root) {
    'use strict';

    if (!root || root.__tbCarouselController) return;
    root.__tbCarouselController = true;

    var doc = root && root.document;

    // No DOM means there is no carousel to drive. Bail before touching
    // anything: this file is inlined into rendered HTML, but it is also
    // plain JavaScript on disk, and a non-browser context (a Node test
    // harness, a server-side import) must load it without throwing.
    if (!doc) return;

    // ───────────────────────────────────────────────────── the controller

    /**
     * The whole carousel behaviour, factored out of the old IIFE exactly as
     * it was. The only change is that the two entry points below decide
     * *which root* to hand it.
     *
     * @param {HTMLElement} rootEl the `.tb-carousel` element
     * @param {object} [options]  { autoplay, interval, pauseOnHover, fade }
     *        overrides, used by the SDK path to apply the props channel.
     *        Omitted on the public path, where the root's data-* attributes
     *        (written by the server) remain the authority.
     */
    function initCarousel(rootEl, options) {
        if (!rootEl || rootEl.dataset.tbCarouselInit) return null;
        rootEl.dataset.tbCarouselInit = '1';

        options = options || {};

        var isFade = options.fade !== undefined
            ? !!options.fade
            : rootEl.dataset.fade === 'true';
        var autoplay = options.autoplay !== undefined
            ? !!options.autoplay
            : rootEl.dataset.autoplay === 'true';
        var pauseOnHover = options.pauseOnHover !== undefined
            ? !!options.pauseOnHover
            : rootEl.dataset.pauseHover === 'true';
        var intervalMs = options.interval !== undefined
            ? (parseInt(options.interval, 10) || 5000)
            : (parseInt(rootEl.dataset.interval, 10) || 5000);

        var track = rootEl.querySelector('.tb-carousel-track');
        if (!track) return null;

        var slideEls = Array.prototype.slice.call(rootEl.querySelectorAll('.tb-carousel-slide'));
        var dots = Array.prototype.slice.call(rootEl.querySelectorAll('.tb-carousel-dot'));
        var prevBtn = rootEl.querySelector('.tb-carousel-prev');
        var nextBtn = rootEl.querySelector('.tb-carousel-next');
        var pauseBtn = rootEl.querySelector('.tb-carousel-pause');
        var progressFill = rootEl.querySelector('.tb-carousel-progress-fill');
        var thumbs = Array.prototype.slice.call(rootEl.querySelectorAll('.tb-carousel-thumb'));

        var idx = 0, timer = null;
        var manuallyPaused = false;
        var isRTL = root.getComputedStyle(rootEl).direction !== 'ltr';
        var sign = isRTL ? 1 : -1;

        function itemsPerView() {
            if (isFade) return 1;
            var v = parseFloat(root.getComputedStyle(rootEl).getPropertyValue('--tb-items'));
            return (v && v > 0) ? v : 1;
        }

        function maxIndex() { return Math.max(0, slideEls.length - itemsPerView()); }

        function paint() {
            if (isFade) {
                slideEls.forEach(function (s, j) {
                    s.style.opacity = (j === idx) ? '1' : '0';
                    s.style.zIndex = (j === idx) ? '1' : '0';
                });
            } else {
                var ipv = itemsPerView();
                track.style.transform = 'translateX(' + (sign * idx * (100 / ipv)) + '%)';
            }
            // WU-B: the colours live in the shared sheet (.tb-carousel-dot,
            // .is-active), so a theme token reaches them.
            dots.forEach(function (d, j) {
                var active = j === idx;
                d.classList.toggle('is-active', active);
                d.setAttribute('aria-current', active ? 'true' : 'false');
            });
            thumbs.forEach(function (t, j) {
                var active = j === idx;
                t.classList.toggle('is-active', active);
                t.setAttribute('aria-current', active ? 'true' : 'false');
            });
        }

        function go(i) { idx = Math.min(Math.max(0, i), maxIndex()); paint(); }
        function stepNext() { go(idx + 1 > maxIndex() ? 0 : idx + 1); }
        function stepPrev() { go(idx - 1 < 0 ? maxIndex() : idx - 1); }

        function stop() { if (timer) { root.clearInterval(timer); timer = null; } }

        // WU-B: autoplay only runs when it can be watched and is wanted —
        // never for prefers-reduced-motion (WCAG 2.2.2 / 2.3.3), never in a
        // hidden tab, never while the carousel is scrolled out of view.
        var motionQuery = typeof root.matchMedia === 'function' ? root.matchMedia('(prefers-reduced-motion: reduce)') : null;
        var inView = true, hovered = false, focused = false;
        function reducedMotion() { return !!(motionQuery && motionQuery.matches); }
        function canAutoplay() {
            return !manuallyPaused && autoplay && !reducedMotion() && !doc.hidden && inView && !hovered && !focused;
        }

        function resetProgress() {
            if (!progressFill) return;
            progressFill.style.transition = 'none';
            progressFill.style.width = '0';
            void progressFill.offsetWidth;
            if (canAutoplay()) {
                progressFill.style.transition = 'width ' + intervalMs + 'ms linear';
                progressFill.style.width = '100%';
            }
        }

        function start() {
            stop();
            if (canAutoplay() && slideEls.length > itemsPerView()) {
                timer = root.setInterval(stepNext, intervalMs);
            }
            resetProgress();
        }

        if (nextBtn) nextBtn.addEventListener('click', function () { stepNext(); start(); });
        if (prevBtn) prevBtn.addEventListener('click', function () { stepPrev(); start(); });
        dots.forEach(function (d, j) { d.addEventListener('click', function () { go(j); start(); }); });
        thumbs.forEach(function (t, j) { t.addEventListener('click', function () { go(j); start(); }); });

        if (pauseBtn) pauseBtn.addEventListener('click', function () {
            manuallyPaused = !manuallyPaused;
            pauseBtn.setAttribute('aria-pressed', manuallyPaused ? 'true' : 'false');
            pauseBtn.setAttribute('aria-label', manuallyPaused ? 'ادامه پخش خودکار' : 'توقف پخش خودکار');
            pauseBtn.textContent = manuallyPaused ? '▶' : 'Ⅱ';
            if (manuallyPaused) { stop(); if (progressFill) progressFill.style.transition = 'none'; }
            else start();
        });

        // Phase 2 (ADR-067): the arrow key on the side the next slide comes
        // from — ArrowLeft in RTL, ArrowRight in LTR — never reversed.
        var nextKey = isRTL ? 'ArrowLeft' : 'ArrowRight';
        var prevKey = isRTL ? 'ArrowRight' : 'ArrowLeft';
        rootEl.addEventListener('keydown', function (e) {
            if (e.key === prevKey) { stepPrev(); start(); e.preventDefault(); }
            else if (e.key === nextKey) { stepNext(); start(); e.preventDefault(); }
        });

        // A hover/focus pause is a gate in canAutoplay(), so a visibility or
        // viewport change cannot restart autoplay under the pointer.
        if (pauseOnHover) {
            rootEl.addEventListener('mouseenter', function () { hovered = true; stop(); });
            rootEl.addEventListener('mouseleave', function () { hovered = false; start(); });
            rootEl.addEventListener('focusin', function () { focused = true; stop(); });
            rootEl.addEventListener('focusout', function () { focused = false; start(); });
        }

        // Phase 2 (ADR-067): pointer drag (touch, pen and mouse) — the track
        // follows the finger, and a swipe towards the reading end advances:
        // in RTL that is a swipe to the right (the next slide sits to the
        // left), in LTR a swipe to the left. The old touch handler had RTL
        // reversed. A drag that moved never clicks the link under it.
        var viewport = rootEl.querySelector('.tb-carousel-viewport') || rootEl;
        var drag = null;
        // WU-B: fade mode swipes too — the gesture is read the same way, but
        // the track never follows the finger; the release picks the slide.
        var trackTransition = track.style.transition; // '' = the sheet's own slide animation
        var suppressClick = false;
        viewport.style.touchAction = 'pan-y';
        viewport.addEventListener('pointerdown', function (e) {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            if (e.target.closest && e.target.closest('button, input, select, textarea, form')) return;
            drag = { x: e.clientX, y: e.clientY, dx: 0, id: e.pointerId, moving: false };
        });
        viewport.addEventListener('pointermove', function (e) {
            if (!drag || e.pointerId !== drag.id) return;
            drag.dx = e.clientX - drag.x;
            if (!drag.moving) {
                if (Math.abs(drag.dx) < 6 || Math.abs(drag.dx) < Math.abs(e.clientY - drag.y)) return;
                drag.moving = true;
                stop();
                if (!isFade) track.style.transition = 'none';
                try { viewport.setPointerCapture(e.pointerId); } catch (_) {}
            }
            if (isFade) return;
            var atEdge = (isForward(drag.dx) && idx >= maxIndex()) || (!isForward(drag.dx) && idx <= 0);
            var offset = atEdge ? drag.dx * 0.35 : drag.dx;
            track.style.transform = 'translateX(calc(' + (sign * idx * (100 / itemsPerView())) + '% + ' + offset + 'px))';
        });
        function isForward(dx) { return isRTL ? dx > 0 : dx < 0; }
        function endDrag(e) {
            if (!drag || (e && e.pointerId !== drag.id)) return;
            var d = drag;
            drag = null;
            if (!d.moving) return;
            if (!isFade) track.style.transition = trackTransition;
            suppressClick = true;
            var threshold = Math.min(80, viewport.clientWidth / itemsPerView() / 5);
            if (Math.abs(d.dx) > threshold) {
                if (isForward(d.dx)) go(Math.min(idx + 1, maxIndex())); else go(Math.max(idx - 1, 0));
            } else {
                paint();
            }
            start();
        }
        viewport.addEventListener('pointerup', endDrag);
        viewport.addEventListener('pointercancel', endDrag);
        viewport.addEventListener('click', function (e) {
            if (suppressClick) { e.preventDefault(); e.stopPropagation(); suppressClick = false; }
        }, true);
        viewport.addEventListener('dragstart', function (e) { e.preventDefault(); });

        // Registered on `window`, not on rootEl, so it is the ONE listener
        // here that outlives the element. It must therefore be unregistered
        // in destroy(): its closure holds rootEl and everything this
        // controller captured, so a carousel element that is replaced (the
        // SDK's onUpdate path) would otherwise pin its whole detached subtree
        // to the page for the page's lifetime.
        var onResize = function () { go(Math.min(idx, maxIndex())); };
        root.addEventListener('resize', onResize);

        // WU-B: the autoplay gates (see canAutoplay()). The document and
        // media-query listeners outlive the element, like onResize, so
        // destroy() removes them.
        var onVisibility = function () { if (doc.hidden) stop(); else start(); };
        var watchesVisibility = typeof doc.addEventListener === 'function' && typeof doc.removeEventListener === 'function';
        if (watchesVisibility) doc.addEventListener('visibilitychange', onVisibility);
        var onMotion = function () { start(); };
        if (motionQuery) {
            if (motionQuery.addEventListener) motionQuery.addEventListener('change', onMotion);
            else if (motionQuery.addListener) motionQuery.addListener(onMotion);
        }
        var observer = null;
        if (typeof root.IntersectionObserver === 'function') {
            observer = new root.IntersectionObserver(function (entries) {
                var visible = entries[entries.length - 1].isIntersecting;
                if (visible === inView) return;
                inView = visible;
                if (inView) start(); else stop();
            }, { threshold: 0.1 });
            observer.observe(rootEl);
        }

        paint();
        start();

        return {
            /**
             * Leave nothing behind. Element-scoped listeners die with the
             * node and need no teardown, but the window/document/media-query
             * listeners, the observer and the autoplay timer are ours.
             */
            destroy: function () {
                stop();
                root.removeEventListener('resize', onResize);
                if (watchesVisibility) doc.removeEventListener('visibilitychange', onVisibility);
                if (motionQuery) {
                    if (motionQuery.removeEventListener) motionQuery.removeEventListener('change', onMotion);
                    else if (motionQuery.removeListener) motionQuery.removeListener(onMotion);
                }
                if (observer) observer.disconnect();
            },
        };
    }

    // Exposed the same way the SDK exposes itself (root.ThemeBlockSDK), so
    // another block — or a test — can drive a carousel without going through
    // a whole canvas. Not a supported authoring API for theme developers;
    // the CarouselBlock below is.
    root.ThemeCarousel = { initCarousel: initCarousel };

    // ───────────────────────────────────────────────────── carrier 1: SDK

    if (root.ThemeBlockSDK && root.ThemeCanvasRuntime) {
        // root.ThemeBlockSDK is ALREADY the created instance: block-sdk.js
        // assigns `root.ThemeBlockSDK = mod.create(root.ThemeCanvasRuntime,
        // ...)` at load time, and that instance exposes defineBlock /
        // BaseBlock / EVENTS. Calling .create() on it is undefined and throws
        // — and because both carriers live in this one file, that throw also
        // takes the public self-initialise fallback down with it.
        var sdk = root.ThemeBlockSDK;
        var BaseBlock = root.ThemeBlockSDK.BaseBlock;
        var EVENTS = root.ThemeBlockSDK.EVENTS;

        /**
         * The carousel, as a real block. `product_slider` is the only type
         * registered here today; the other carousel types keep their inline
         * carrier until they are migrated one at a time.
         */
        function CarouselBlock(definition) {
            BaseBlock.call(this, definition);
        }

        CarouselBlock.prototype = Object.create(BaseBlock.prototype);
        CarouselBlock.prototype.constructor = CarouselBlock;

        CarouselBlock.type = 'product_slider';
        CarouselBlock.version = 1;

        CarouselBlock.defaultProps = {
            autoplay: true,
            interval: 5000,
            columns: 3,
        };

        CarouselBlock.deps = { js: [], css: [] };

        /**
         * The block root is the `.tb-block` / `.tb-canvas-block` wrapper the
         * renderer emits, NOT the carousel element, so every query below is
         * scoped by ctx.$ and can never escape this block.
         */
        function carouselRoot(ctx) {
            var found = ctx.$('.tb-carousel');
            return found.length ? found[0] : null;
        }

        /**
         * Props channel resolution. Precedence: the server's own
         * data-* attributes stay authoritative, because they are what the
         * server actually rendered; ctx.props can only fill a value the
         * server did not write. This is deliberate — if the two ever
         * disagreed, the block would repaint differently from the markup
         * that shipped, which is the exact class of bug the props channel
         * exists to prevent.
         */
        function optionsFrom(ctx) {
            return {
                autoplay: ctx.props.autoplay,
                interval: ctx.props.interval,
            };
        }

        CarouselBlock.prototype.init = function (ctx) {
            // init may be async (deps via ctx.ready()). The SDK holds every
            // later phase on this instance back until the continuation below
            // has run, so state written here is always the state the next
            // phase sees.
            var self = this;
            return ctx.ready().then(function () {
                var el = carouselRoot(ctx);
                if (!el) return;

                // A block-local guard against a re-entered init continuation
                // (a double init on the same instance). Without it, a second
                // continuation would re-call initCarousel(), hit the
                // element's own data-tb-carousel-init guard, and overwrite
                // the live handle with null — orphaning the real controller
                // and leaking its window listener/timer. (This is the
                // concrete mechanism behind SDK-002; the SDK-side fix makes
                // the interleaving unreachable, the guard makes the block
                // safe even if a future caller breaks that invariant.)
                if (self.state.element === el && self.state.handle) return;

                self.state.element = el;
                self.state.handle = initCarousel(el, optionsFrom(ctx));
                ctx.emit('block:product_slider:ready', { blockId: ctx.blockId });
            });
        };

        CarouselBlock.prototype.onUpdate = function (ctx) {
            // On the editor's real replace path the SDK routes this to the
            // NEW element's fresh instance (runtime.replaceNode() emits
            // 'destroy' then 'update', and the new element goes up through
            // initBlock on update). The SDK serialises phases per instance,
            // so when THIS instance gets onUpdate its own init has fully
            // completed.
            //
            // A plain `update` emitted WITHOUT replacing the element reaches
            // the original instance too. The two branches:
            //   - same element, handle alive  -> nothing to do (re-running
            //     initCarousel is refused by the data-tb-carousel-init
            //     guard, and nulling the handle first would leave the block
            //     with no controller at all);
            //   - the rare interleaving where a controller was already
            //     bound to this element but its handle did not reach state
            //     (see the async-init guard note in init) -> tear the
            //     orphaned controller down explicitly so its window
            //     listener and autoplay timer cannot leak, then re-bind.
            //     initCarousel() returns null when the element already
            //     carries the init guard, so a null return after destroy()
            //     means the element is owned and the guard alone keeps it
            //     consistent — never null out the previous handle BEFORE
            //     destroy() has run, or its cleanup becomes unreachable.
            var el = carouselRoot(ctx);
            if (!el) return;
            if (this.state.handle && this.state.element === el) return;

            if (this.state.handle) {
                this.state.handle.destroy();
                this.state.handle = null;
            }
            this.state.element = el;
            this.state.handle = initCarousel(el, optionsFrom(ctx));
        };

        CarouselBlock.prototype.onRefresh = function (ctx) {
            // A refresh re-reads the responsive custom properties; the
            // controller already re-clamps on resize, so repaint by
            // rebuilding is unnecessary. Just report the live state.
            ctx.emit('block:product_slider:refresh', { blockId: ctx.blockId });
        };

        CarouselBlock.prototype.onDestroy = function (ctx, detail) {
            if (this.state.handle) {
                this.state.handle.destroy();
                this.state.handle = null;
            }
            ctx.emit(EVENTS.STATE_RESET, { reason: (detail && detail.blockId) || null });
        };

        sdk.defineBlock(CarouselBlock);
    }

    // ─────────────────────────────────────── carrier 2: public (no SDK)

    function initAllCarousels() {
        var found = Array.prototype.slice.call(doc.querySelectorAll('.tb-carousel'));
        for (var i = 0; i < found.length; i++) initCarousel(found[i]);
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', initAllCarousels, { once: true });
    } else {
        initAllCarousels();
    }
}(typeof window !== 'undefined' ? window : globalThis));
