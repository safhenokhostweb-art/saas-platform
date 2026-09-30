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
 *      type. The SDK owns lifecycle; ctx.$ scopes every query to the block
 *      root, so a carousel can never reach into a neighbour's DOM.
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
 * MIGRATION STATUS — read before editing
 *   `product_slider` is the ONLY migrated caller. `slider`,
 *   `category_slider`, `testimonials_slider` and the remaining renderCarousel()
 *   call sites still carry the pre-migration inline copy verbatim. Those
 *   copies are the same logic and the same guard, so a page mixing both
 *   carriers behaves correctly — but if you fix a bug in the controller
 *   below, those inline copies still have it. Migrating a type means
 *   deleting its inline copy, not editing it.
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
            dots.forEach(function (d, j) {
                var active = j === idx;
                d.style.background = active ? 'var(--tb-primary,#4f46e5)' : '#d1d5db';
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

        function resetProgress() {
            if (!progressFill) return;
            progressFill.style.transition = 'none';
            progressFill.style.width = '0';
            void progressFill.offsetWidth;
            if (!manuallyPaused && autoplay) {
                progressFill.style.transition = 'width ' + intervalMs + 'ms linear';
                progressFill.style.width = '100%';
            }
        }

        function start() {
            stop();
            if (!manuallyPaused && autoplay && slideEls.length > itemsPerView()) {
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

        rootEl.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { stepPrev(); start(); e.preventDefault(); }
            else if (e.key === 'ArrowLeft') { stepNext(); start(); e.preventDefault(); }
        });

        if (pauseOnHover) {
            rootEl.addEventListener('mouseenter', stop);
            rootEl.addEventListener('mouseleave', start);
            rootEl.addEventListener('focusin', stop);
            rootEl.addEventListener('focusout', start);
        }

        var touchX = null;
        rootEl.addEventListener('touchstart', function (e) {
            touchX = e.touches[0].clientX;
        }, { passive: true });
        rootEl.addEventListener('touchend', function (e) {
            if (touchX === null) return;
            var dx = e.changedTouches[0].clientX - touchX;
            if (Math.abs(dx) > 40) { (dx > 0 ? stepPrev : stepNext)(); start(); }
            touchX = null;
        }, { passive: true });

        // Registered on `window`, not on rootEl, so it is the ONE listener
        // here that outlives the element. It must therefore be unregistered
        // in destroy(): its closure holds rootEl and everything this
        // controller captured, so a carousel element that is replaced (the
        // SDK's onUpdate path) would otherwise pin its whole detached subtree
        // to the page for the page's lifetime.
        var onResize = function () { go(Math.min(idx, maxIndex())); };
        root.addEventListener('resize', onResize);

        paint();
        start();

        return {
            /**
             * Leave nothing behind. Element-scoped listeners die with the
             * node and need no teardown, but the window listener and the
             * autoplay timer are ours to clean up.
             */
            destroy: function () {
                stop();
                root.removeEventListener('resize', onResize);
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
            var self = this;
            return ctx.ready().then(function () {
                var el = carouselRoot(ctx);
                if (!el) return;
                self.state.element = el;
                self.state.handle = initCarousel(el, optionsFrom(ctx));
                ctx.emit('block:product_slider:ready', { blockId: ctx.blockId });
            });
        };

        CarouselBlock.prototype.onUpdate = function (ctx) {
            // NOTE: on the editor's real replace path this handler is NOT
            // reached. runtime.replaceNode() emits 'destroy' and then
            // 'update', and the SDK's releaseInstance() drops the instance
            // from the live set during 'destroy', so by the time 'update'
            // fans out there is no instance left to call.
            //
            // So this only runs if a caller emits 'update' WITHOUT replacing
            // the element. Re-init is correct then only if the carousel
            // element is genuinely new: re-running the controller against the
            // one we already own would be refused by the
            // data-tb-carousel-init guard, and nulling the handle first would
            // leave the block with no controller at all.
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
