/*!
 * Countdown adapter — Phase 9 / Step 3
 * ===================================
 * The client logic of BlockRenderer::renderCountdown(), moved out of the PHP
 * string that inlined it into every countdown block.
 *
 * WHY AN ADAPTER AND NOT A CONTROLLER
 *   A carousel needed real behaviour extraction because seven block types
 *   shared one inline script. The countdown never did — but its inline copy
 *   had two defects that the move fixes for free:
 *
 *     1. It never kept the interval handle. `setInterval(tick, 1000)` was
 *        assigned to a variable that was only ever READ inside tick() to
 *        clear it on expiry. Nothing could cancel it on teardown, so a
 *        countdown block replaced in the editor kept ticking against a
 *        detached node until the countdown itself ran out.
 *     2. It bailed out on an unparseable date AFTER setting its init guard,
 *        so a malformed `end_date` produced a block that could never
 *        recover even if the value were corrected live.
 *
 *   So this exposes a handle whose destroy() clears the timer, and does not
 *   poison the guard on a bad date.
 *
 * TWO CARRIERS, ONE LOGIC — same shape as blocks/carousel.js, for the same
 * reason. On canvas surfaces the block SDK is loaded and this registers a
 * real BaseBlock for the `countdown` type. On PUBLIC customer pages no
 * layout loads the canvas runtime, so the same file self-initialises every
 * countdown root it finds. Both paths run the identical controller.
 *
 * THE `data-tb-countdown-init` GUARD
 *   Deliberately the SAME attribute the pre-migration inline script set, so
 *   a page that somehow carries both carriers still initialises each
 *   countdown exactly once.
 *
 * NOT A PROPS-CHANNEL BLOCK
 *   The countdown's configuration lives in `data-end-date` on its own root,
 *   written by the server. That is the authority: the props channel
 *   (`ctx.props`) can only disagree with the markup that actually shipped,
 *   and the whole point of this refactor is that the rendered markup stays
 *   byte-identical.
 */
(function (root) {
    'use strict';

    if (!root || root.__tbCountdownAdapter) return;
    root.__tbCountdownAdapter = true;

    var doc = root && root.document;

    // No DOM means no countdown to drive. Bail rather than throw: this file
    // is plain JavaScript on disk as well as something inlined into HTML.
    if (!doc) return;

    // ───────────────────────────────────────────────────── the controller

    /**
     * @param {HTMLElement} rootEl a `.tb-countdown` element
     * @returns {{destroy: function}|null} null when the element is not a real
     *   countdown (no parseable date, or already initialised).
     */
    function initCountdown(rootEl) {
        if (!rootEl || rootEl.dataset.tbCountdownInit) return null;

        var end = new Date(rootEl.dataset.endDate).getTime();

        // Note the ordering: the date is validated BEFORE the init guard is
        // set. The pre-migration inline script set the guard first and then
        // returned on a bad date, permanently bricking the block. Here a bad
        // date simply means "not a live countdown", and a corrected
        // `data-end-date` can still be initialised later.
        if (isNaN(end)) return null;

        rootEl.dataset.tbCountdownInit = '1';

        var timerEl = rootEl.querySelector('.tb-countdown__timer');
        var expiredEl = rootEl.querySelector('.tb-countdown__expired');
        var dEl = rootEl.querySelector('.tb-countdown__d');
        var hEl = rootEl.querySelector('.tb-countdown__h');
        var mEl = rootEl.querySelector('.tb-countdown__m');
        var sEl = rootEl.querySelector('.tb-countdown__s');

        var interval = null;

        // Phase 3 (ADR-068): Persian digits, two places — «۰۵».
        function pad(n) {
            var s = String(n).padStart(2, '0');
            return s.replace(/[0-9]/g, function (c) { return String.fromCharCode(0x06F0 + Number(c)); });
        }

        function tick() {
            var diff = end - Date.now();
            if (diff <= 0) {
                if (timerEl) timerEl.style.display = 'none';
                if (expiredEl) { expiredEl.hidden = false; expiredEl.style.display = 'block'; }
                if (rootEl.classList) rootEl.classList.add('is-expired');
                stop();
                return false;
            }
            var d = Math.floor(diff / 86400000);
            var h = Math.floor((diff % 86400000) / 3600000);
            var m = Math.floor((diff % 3600000) / 60000);
            var s = Math.floor((diff % 60000) / 1000);
            if (dEl) dEl.textContent = pad(d);
            if (hEl) hEl.textContent = pad(h);
            if (mEl) mEl.textContent = pad(m);
            if (sEl) sEl.textContent = pad(s);
            return true;
        }

        function stop() {
            if (interval) { root.clearInterval(interval); interval = null; }
        }

        // Only arm the interval if there is still time left to count. A block
        // authored with a date already in the past is expired from the first
        // frame, and scheduling a timer it will cancel one second later would
        // leave a live timer pinned to a detached node in the meantime.
        if (tick()) {
            interval = root.setInterval(tick, 1000);
        }

        return {
            /**
             * The whole reason this file exists rather than the old inline
             * copy: an editor replacement of the block can now actually stop
             * the ticker. Without this, every replaced countdown kept
             * running until its target date passed.
             */
            destroy: function () { stop(); },
        };
    }

    root.ThemeCountdown = { initCountdown: initCountdown };

    // ───────────────────────────────────────────────────── carrier 1: SDK

    if (root.ThemeBlockSDK && root.ThemeCanvasRuntime) {
        // root.ThemeBlockSDK is ALREADY the created instance (block-sdk.js
        // assigns it at load time), so there is nothing to call here — the
        // same trap blocks/carousel.js fell into once.
        var sdk = root.ThemeBlockSDK;
        var BaseBlock = root.ThemeBlockSDK.BaseBlock;
        var EVENTS = root.ThemeBlockSDK.EVENTS;

        function CountdownBlock(definition) {
            BaseBlock.call(this, definition);
        }

        CountdownBlock.prototype = Object.create(BaseBlock.prototype);
        CountdownBlock.prototype.constructor = CountdownBlock;

        CountdownBlock.type = 'countdown';
        CountdownBlock.version = 1;

        // The server never renders a script for a countdown with no
        // `end_date`, so there is nothing to bind to in that case; defaults
        // stay empty to match the registry's schema.
        CountdownBlock.defaultProps = { end_date: '', end_time: '', label: '', expired_text: '', style: 'card', show_end_date: true };

        CountdownBlock.deps = { js: [], css: [] };

        // The block root is the .tb-block / .tb-canvas-block wrapper, so
        // ctx.$ can never reach a neighbouring block's countdown.
        function countdownRoot(ctx) {
            var found = ctx.$('.tb-countdown');
            return found.length ? found[0] : null;
        }

        CountdownBlock.prototype.init = function (ctx) {
            var self = this;
            return ctx.ready().then(function () {
                var el = countdownRoot(ctx);
                if (!el) return;
                self.state.element = el;
                self.state.handle = initCountdown(el);
                if (self.state.handle) {
                    ctx.emit('block:countdown:ready', { blockId: ctx.blockId });
                }
            });
        };

        CountdownBlock.prototype.onUpdate = function (ctx) {
            // See blocks/carousel.js: on the editor's real replace path this
            // is NOT reached, because releaseInstance() drops the instance
            // during the preceding 'destroy' phase. It only runs if a caller
            // emits 'update' without replacing the element, and then only a
            // genuinely new countdown element may be re-initialised.
            var el = countdownRoot(ctx);
            if (!el) return;
            if (this.state.handle && this.state.element === el) return;

            if (this.state.handle) {
                this.state.handle.destroy();
                this.state.handle = null;
            }
            this.state.element = el;
            this.state.handle = initCountdown(el);
        };

        CountdownBlock.prototype.onRefresh = function (ctx) {
            ctx.emit('block:countdown:refresh', { blockId: ctx.blockId });
        };

        CountdownBlock.prototype.onDestroy = function (ctx, detail) {
            if (this.state.handle) {
                this.state.handle.destroy();
                this.state.handle = null;
            }
            ctx.emit(EVENTS.STATE_RESET, { reason: (detail && detail.blockId) || null });
        };

        sdk.defineBlock(CountdownBlock);
    }

    // ─────────────────────────────────────── carrier 2: public (no SDK)

    function initAllCountdowns() {
        // Only roots that actually carry a target date are candidates, which
        // mirrors the server rule: a countdown without `end_date` renders no
        // script and must stay inert.
        var found = Array.prototype.slice.call(doc.querySelectorAll('.tb-countdown[data-end-date]'));
        for (var i = 0; i < found.length; i++) initCountdown(found[i]);
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', initAllCountdowns, { once: true });
    } else {
        initAllCountdowns();
    }
}(typeof window !== 'undefined' ? window : globalThis));
