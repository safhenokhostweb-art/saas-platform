/*!
 * Reference block: `reference_counter`
 * ===================================
 * The worked example for the ThemeBlockSDK (Phase 9 / Step 1). It exists to
 * prove the SDK is usable, not to be a product block — a developer copies this
 * file, changes the type name, and has a working block.
 *
 * WHAT IT DEMONSTRATES
 *   - the CLASS form: extends ThemeBlockSDK.BaseBlock for real per-instance
 *     state (no closures-per-element bookkeeping).
 *   - `ctx.ready()` inside an async init: the block's own declared deps are
 *     resolved before its body runs.
 *   - `ctx.$` scoped queries that can never escape the block root.
 *   - `ctx.setProp` + the `state:update` event for LOCAL state.
 *   - block-local events under the standard `block:<type>:<verb>` prefix.
 *   - all four lifecycle phases: init / onUpdate / onRefresh / onDestroy.
 *
 * DEPENDENCIES
 *   `deps` is empty here on purpose: this package ships no HTTP-served asset,
 *   and a demo must not claim to load a file that does not exist. A real block
 *   looks exactly like this:
 *
 *       static deps = {
 *           js:  ['/vendor/acme/blocks/1.0.0/counter.js'],
 *           css: ['/vendor/acme/blocks/1.0.0/counter.css'],
 *       };
 *
 *   and the SDK resolves js through the runtime's own trackScript() and css
 *   through a deduped <link> injection, both awaited by ctx.ready().
 */
(function (root) {
    'use strict';

    if (!root || !root.ThemeBlockSDK) return;

    // The renderer inlines this file once per block instance on the page.
    // Defining the type twice would be harmless (the SDK replaces the
    // previous definition) but it is wasted work and a console warning, so
    // the registration is guarded on a page-level flag.
    if (root.__tbReferenceCounterRegistered) return;
    root.__tbReferenceCounterRegistered = true;

    var BaseBlock = root.ThemeBlockSDK.BaseBlock;
    var EVENTS = root.ThemeBlockSDK.EVENTS;

    function ReferenceCounter(definition) {
        BaseBlock.call(this, definition);
    }

    ReferenceCounter.prototype = Object.create(BaseBlock.prototype);
    ReferenceCounter.prototype.constructor = ReferenceCounter;

    ReferenceCounter.type = 'reference_counter';
    ReferenceCounter.version = 1;

    ReferenceCounter.defaultProps = {
        step: 1,
        min: 0,
        max: 99,
    };

    ReferenceCounter.deps = { js: [], css: [] };

    function toInt(value, fallback) {
        var n = parseInt(value, 10);
        return isFinite(n) ? n : fallback;
    }

    ReferenceCounter.prototype.paint = function () {
        if (this.state.valueEl) {
            this.state.valueEl.textContent = String(this.state.value);
        }
    };

    ReferenceCounter.prototype.init = function (ctx) {
        var self = this;

        // Everything below runs only after the declared deps resolved.
        return ctx.ready().then(function () {
            // ctx.props carries the props AS AUTHORED (the server clamps only
            // what it renders), so the same clamp is applied here — otherwise
            // an out-of-range authored value would paint a number the markup
            // never showed.
            self.state.min = toInt(ctx.props.min, 0);
            self.state.max = toInt(ctx.props.max, 99);
            self.state.step = toInt(ctx.props.step, 1) || 1;
            self.state.value = Math.min(self.state.max,
                Math.max(self.state.min, toInt(ctx.props.value, self.state.min)));

            self.state.valueEl = ctx.$('.tb-ref-counter__value')[0];
            var decrease = ctx.$('.tb-ref-counter__decrease')[0];
            var increase = ctx.$('.tb-ref-counter__increase')[0];
            var reset = ctx.$('.tb-ref-counter__reset')[0];

            function clamp(value) {
                return Math.min(self.state.max, Math.max(self.state.min, value));
            }

            function stepBy(delta) {
                var next = clamp(self.state.value + delta * self.state.step);
                if (next === self.state.value) return;
                self.state.value = next;
                // Local state only: never round-trips through the editor.
                ctx.setProp('value', next);
                ctx.emit('block:reference_counter:change', { value: next, delta: delta });
            }

            if (decrease) decrease.addEventListener('click', function () { stepBy(-1); });
            if (increase) increase.addEventListener('click', function () { stepBy(1); });
            if (reset) reset.addEventListener('click', function () {
                self.state.value = self.state.min;
                ctx.setProp('value', self.state.value);
                ctx.emit('block:reference_counter:reset', { value: self.state.value });
            });

            ctx.on(EVENTS.STATE_UPDATE, function (payload) {
                if (!payload || payload.blockId !== ctx.blockId || payload.type !== ReferenceCounter.type) return;
                var props = payload.props || ctx.props;
                self.state.min = toInt(props.min, 0);
                self.state.max = toInt(props.max, 99);
                self.state.step = toInt(props.step, 1) || 1;
                self.state.value = Math.min(self.state.max,
                    Math.max(self.state.min, toInt(props.value, self.state.value)));
                self.paint();
            });

            self.paint();
            ctx.emit('block:reference_counter:ready', { value: self.state.value });
        });
    };

    ReferenceCounter.prototype.onUpdate = function (ctx, detail) {
        // The element was replaced: its listeners died with the old node, so
        // re-attach them exactly as init does.
        this.init(ctx);
    };

    ReferenceCounter.prototype.onRefresh = function (ctx) {
        ctx.emit('block:reference_counter:refresh', { value: this.state.value });
    };

    ReferenceCounter.prototype.onDestroy = function (ctx, detail) {
        // ctx.on() subscriptions are already gone — the runtime released
        // them before this ran. Native listeners die with the removed node.
        ctx.emit(EVENTS.STATE_RESET, { reason: (detail && detail.blockId) || null });
    };

    root.ThemeBlockSDK.defineBlock(ReferenceCounter);
}(typeof window !== 'undefined' ? window : globalThis));
