/*!
 * Legacy carousel adapter — Phase 9.2 (SDK-002 aftermath)
 * ========================================================
 * The four carousel types that used to ship the pre-Phase-9.2 inline
 * controller (`legacyCarouselScript()` in src/BlockRenderer.php, deleted
 * in this phase):
 *
 *   slider, category_slider, testimonials_slider, client_logos
 *
 * ...are byte-for-byte copies of one controller that (a) attaches an
 * ANONYMOUS window resize listener it can never remove and (b) never
 * returns a handle, so on every editor replaceNode() of one of these
 * types one window listener + one autoplay interval is stranded, pinning
 * its detached DOM subtree. That is the exact leak class SDK-002 fixed
 * for product_slider through the per-instance phase gate — these types
 * bypass the SDK entirely, so the gate cannot reach them.
 *
 * THIS FILE is the migration vehicle: the same controller, the same
 * data-* attributes, the same data-tb-carousel-init guard — driven by
 * the SDK instead of raw scripts, so destroy() finally exists and the
 * phase gate finally applies.
 *
 * ONE CONTROLLER, NOT A THIRD COPY
 *   The controller itself stays in carousel.js (the shared asset the
 *   product_slider block drives). Its initCarousel() is exposed as
 *   root.ThemeCarousel.initCarousel for exactly this use. This adapter
 *   adds only what a block needs around it: BaseBlock plumbing, the
 *   type list, props/data fallbacks, and a destroy() that tears the
 *   controller down (clearInterval on the autoplay timer + removal of
 *   the resize listener — both captured by the handle returned from
 *   initCarousel and stored in this.state, since the legacy script's
 *   anonymous closure made its listener permanently unremovable).
 *
 * WHAT CHANGES FOR THE PAGE
 *   The data-* contract is identical to the legacy script (autoplay,
 *   interval, pauseHover, fade), the guard attribute is identical, and
 *   the controller behaviour is identical — a page that renders this
 *   block's markup and includes this file instead of the inline copy
 *   cannot tell the difference, except that replacement now tears down.
 *
 * MIGRATION STATUS — Phase 9.2, COMPLETE
 *   Registering these types here did not migrate them by itself; the PHP
 *   switch that completed it was deleting legacyCarouselScript() from
 *   BlockRenderer, so renderCarousel() no longer emits a per-instance
 *   inline copy at all. Every caller now ships this adapter alongside
 *   the shared controller, and each type has a test pinning that
 *   emission (tests/Unit/ThemeBuilder/ProductSliderRenderSnapshotTest.php).
 *
 *   If a caller ever needs its own carrier again, remember what migration
 *   means: delete the old carrier, never add a second one.
 */
(function (root) {
    'use strict';

    if (!root || root.__tbLegacyCarouselAdapter) return;
    root.__tbLegacyCarouselAdapter = true;

    // The adapter is only meaningful where the SDK exists. On public pages
    // the legacy inline script (or the shared carrier's own public fallback
    // via carousel.js carrier 2) remains the active carrier; nothing here
    // competes with it.
    if (!root.ThemeBlockSDK) return;

    var BaseBlock = root.ThemeBlockSDK.BaseBlock;

    /** The four types still carried by legacyCarouselScript(). */
    var LEGACY_TYPES = [
        'slider',
        'category_slider',
        'testimonials_slider',
        'client_logos',
    ];

    /**
     * One adapter class, registered once per legacy type. The extra class
     * indirection exists purely to carry the static `type` each
     * defineBlock() registration reads.
     */
    function buildAdapterClass(type) {
        function LegacyCarouselBlock(definition) {
            BaseBlock.call(this, definition);
        }

        LegacyCarouselBlock.prototype = Object.create(BaseBlock.prototype);
        LegacyCarouselBlock.prototype.constructor = LegacyCarouselBlock;

        LegacyCarouselBlock.type = type;
        LegacyCarouselBlock.version = 1;
        // Deliberately no defaultProps: every knob is read from the same
        // data-* attributes the legacy script read, with the same fallbacks
        // (autoplay/interval/pauseHover/fade). Nothing to fill in.
        LegacyCarouselBlock.deps = { js: [], css: [] };

        LegacyCarouselBlock.prototype.init = function (ctx) {
            // Synchronous by design: the legacy script never waited for
            // anything either. No deps are declared, so there is nothing
            // ctx.ready() could add — and a sync init means the SDK's
            // per-instance phase gate has nothing to hold, exactly as for
            // any other sync block.
            var el = this.findCarouselRoot(ctx);
            if (!el) return; // fail soft, like the legacy script's null checks

            // The controller is its own double-init guard: initCarousel()
            // refuses an element already carrying data-tb-carousel-init and
            // returns null, which is also the interop contract with the
            // legacy inline script and the public self-initialise carrier —
            // whichever reaches an element first owns it, never both.
            var handle = root.ThemeCarousel.initCarousel(el, this.optionsFor(ctx));

            // THE SDK-002 FIX FOR THE LEGACY TYPES: store the handle, not a
            // fresh DOM probe. initCarousel's returned object closes over
            // the autoplay timer id and the NAMED resize handler (the legacy
            // script's was anonymous and permanently unremovable), so
            // destroy() below can clearInterval() and removeEventListener()
            // for real. No `if (el.dataset.tbCarouselInit) return null`
            // short-circuit is duplicated here: a null handle (guard already
            // set) means another carrier owns the element and destroying
            // this instance's state must not touch it.
            this.state.handle = handle;
            this.state.element = el;
        };

        LegacyCarouselBlock.prototype.onUpdate = function (ctx) {
            // Same element with a live controller: nothing to do — the
            // controller already re-clamps on resize and re-reads nothing
            // else. A replaced element is a new instance; the SDK routes
            // that through init, not here.
            var el = this.findCarouselRoot(ctx);
            if (!el) return;
            if (this.state.handle && this.state.element === el) return;

            // Superseded controller: tear it down BEFORE rebinding — the
            // destroy-before-null ordering, so the old window listener and
            // timer are released even if the rebind below no-ops.
            if (this.state.handle) {
                this.state.handle.destroy();
                this.state.handle = null;
            }
            this.state.element = el;
            this.state.handle = root.ThemeCarousel.initCarousel(el, this.optionsFor(ctx));
        };

        LegacyCarouselBlock.prototype.onRefresh = function (ctx) {
            // The controller re-clamps its index on window resize; a
            // document-wide refresh carries no new carousel state.
            ctx.emit('block:' + this.definition.type + ':refresh', { blockId: ctx.blockId });
        };

        /**
         * The whole point of the migration: a real teardown.
         *   - clearInterval() on the autoplay timer,
         *   - window.removeEventListener('resize', ...) for the handler,
         * both through the handle captured during init. The handler was
         * captured NAMED inside the shared controller precisely so this
         * call can remove it — the legacy script's anonymous version made
         * the listener permanently unremovable.
         */
        LegacyCarouselBlock.prototype.onDestroy = function () {
            if (this.state.handle) {
                this.state.handle.destroy(); // stop() + removeEventListener('resize', onResize)
                this.state.handle = null;
            }
            this.state.element = null;
        };

        LegacyCarouselBlock.prototype.findCarouselRoot = function (ctx) {
            var found = ctx.$('.tb-carousel');
            return found.length ? found[0] : null;
        };

        LegacyCarouselBlock.prototype.optionsFor = function (ctx) {
            // Same precedence as the legacy script: data-* attributes are
            // the authority (the server wrote them); ctx.props can only
            // fill values the server did not write. ctx.props comes from
            // data-tb-block-props, which the legacy markup does not carry —
            // so on real legacy markup the attribute branch always wins,
            // exactly as before.
            return {
                autoplay: ctx.props.autoplay !== undefined ? ctx.props.autoplay : undefined,
                interval: ctx.props.interval !== undefined ? ctx.props.interval : undefined,
            };
        };

        return LegacyCarouselBlock;
    }

    LEGACY_TYPES.forEach(function (type) {
        root.ThemeBlockSDK.defineBlock(buildAdapterClass(type));
    });
}(typeof window !== 'undefined' ? window : globalThis));
