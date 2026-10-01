/*!
 * Modern RTL Header — `header_modern_v1`
 * Client behavior is intentionally limited to progressive enhancement:
 * color repainting through the Block SDK and the responsive menu toggle.
 */
(function (root) {
    'use strict';

    if (!root) return;

    function register() {
        if (!root.ThemeBlockSDK || root.__tbHeaderModernV1Registered) return;
        root.__tbHeaderModernV1Registered = true;

        var BaseBlock = root.ThemeBlockSDK.BaseBlock;
        var EVENTS = root.ThemeBlockSDK.EVENTS;

    function HeaderModern(definition) {
        BaseBlock.call(this, definition);
    }

    HeaderModern.prototype = Object.create(BaseBlock.prototype);
    HeaderModern.prototype.constructor = HeaderModern;

    HeaderModern.type = 'header_modern_v1';
    HeaderModern.version = 1;
    HeaderModern.defaultProps = {
        bgColor: '#ffffff',
        textColor: '#172033',
        ctaColor: '#4f46e5',
    };
    HeaderModern.deps = { js: [], css: [] };

    function isSafeColor(value) {
        return typeof value === 'string'
            && /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(value.trim());
    }

    function readableOn(hex) {
        var value = hex.replace('#', '');
        if (value.length === 3) value = value.split('').map(function (part) { return part + part; }).join('');
        var channels = [0, 2, 4].map(function (offset) {
            var channel = parseInt(value.slice(offset, offset + 2), 16) / 255;
            return channel <= 0.04045 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
        });
        var luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
        return luminance > 0.43 ? '#111827' : '#ffffff';
    }

    HeaderModern.prototype.paint = function () {
        var el = this.state.headerEl;
        if (!el) return;

        var props = this.state.props || {};
        if (isSafeColor(props.bgColor)) el.style.setProperty('--tb-header-bg', props.bgColor.trim());
        if (isSafeColor(props.textColor)) el.style.setProperty('--tb-header-text', props.textColor.trim());
        if (isSafeColor(props.ctaColor)) {
            el.style.setProperty('--tb-header-cta', props.ctaColor.trim());
            el.style.setProperty('--tb-header-cta-text', readableOn(props.ctaColor.trim()));
        }
    };

    HeaderModern.prototype.setMenuOpen = function (open) {
        var button = this.state.toggleEl;
        var panel = this.state.menuEl;
        if (!button || !panel) return;

        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.setAttribute('aria-label', open ? 'بستن منو' : 'باز کردن منو');
        panel.classList.toggle('is-open', open);
    };

    HeaderModern.prototype.init = function (ctx) {
        var self = this;

        return ctx.ready().then(function () {
            self.state.headerEl = ctx.$('.tb-header-modern')[0] || null;
            self.state.toggleEl = ctx.$('.tb-header-modern__toggle')[0] || null;
            self.state.menuEl = ctx.$('.tb-header-modern__menu')[0] || null;
            self.state.props = ctx.props;

            if (self.state.toggleEl) {
                self.state.toggleEl.addEventListener('click', function () {
                    self.setMenuOpen(this.getAttribute('aria-expanded') !== 'true');
                });
            }

            if (self.state.menuEl) {
                self.state.menuEl.addEventListener('click', function (event) {
                    if (event.target.closest('a')) self.setMenuOpen(false);
                });
            }

            if (self.state.headerEl) {
                self.state.headerEl.addEventListener('keydown', function (event) {
                    if (event.key !== 'Escape') return;
                    self.setMenuOpen(false);
                    if (self.state.toggleEl) self.state.toggleEl.focus();
                });
            }

            ctx.on(EVENTS.STATE_UPDATE, function (payload) {
                if (!payload || payload.blockId !== ctx.blockId || payload.type !== HeaderModern.type) return;
                self.state.props = payload.props || ctx.props;
                self.paint();
            });

            self.paint();
        });
    };

    HeaderModern.prototype.onUpdate = function () {
        // Replacement nodes are initialized by ThemeCanvasRuntime before this
        // phase is delivered. Repainting is sufficient and avoids duplicate
        // native event listeners on the new node.
        this.paint();
    };

    HeaderModern.prototype.onDestroy = function () {
        this.state.headerEl = null;
        this.state.toggleEl = null;
        this.state.menuEl = null;
    };

        root.ThemeBlockSDK.defineBlock(HeaderModern);
    }

    if (root.ThemeBlockSDK) {
        register();
    } else if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', register, { once: true });
    }
}(typeof window !== 'undefined' ? window : globalThis));
