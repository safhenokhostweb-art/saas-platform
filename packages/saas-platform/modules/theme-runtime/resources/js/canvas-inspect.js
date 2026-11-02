/*
 * Canvas Inspect Mode (WU-1), shared by both editors and the widget catalog.
 *
 * Runs in any document that renders widgets (the Canvas preview iframe, the
 * catalog page). While on, every [data-widget-type] element gets a thin
 * outline (an outline, never a border, so nothing moves and the WU-2 checks
 * measure the real layout), the hovered and the selected widget get a label
 * with the official widget type, and the selected widget gets a floating
 * action bar. The UI lives in a shadow root, so no theme CSS can reach it.
 *
 * Actions are plugins: TbInspect.registerAction({ id, label, order, when, run })
 * adds a button without touching this file. run(info) may return a string
 * (or a promise of one), which is copied to the clipboard.
 *
 * Host seam (no preview-protocol message is involved):
 *   window.TbInspectHost = { describe(type), wanted(), changed(enabled) }
 * The parent editor installs it with TbInspect.attachHost(); a widget
 * document finds it on itself or on its same-origin parent. describe(type)
 * returns { label, category, schema (field map), defaults, slots, files }
 * for the copy actions.
 *
 * Ctrl+I / Cmd+I toggles, matched on the physical key (e.code), so it also
 * works on a Persian layout. It is ignored inside inputs and contenteditable
 * text, where Ctrl+I means italic.
 *
 * isToggleShortcut(), rendererMethod(), barPosition() and buildPrompt() are
 * pure and unit-tested (tests/js/canvas-inspect.test.mjs).
 */
(function (root) {
    'use strict';

    if (root.TbInspect && root.TbInspect.version === 1) return;

    var WIDGET = '[data-widget-type]';
    var UI_ATTR = 'data-tb-inspect-ui';
    var FILES = {
        registry: 'packages/saas-platform/modules/theme-runtime/src/BlockRegistry.php',
        renderer: 'packages/saas-platform/modules/theme-runtime/src/BlockRenderer.php',
        css: 'packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css',
    };

    /* ── Pure helpers ─────────────────────────────────────────────── */

    function isToggleShortcut(e) {
        return !!e && !!(e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey
            && (e.code === 'KeyI' || e.key === 'i' || e.key === 'I');
    }

    /** Where Ctrl+I already means something (italic, a field's own shortcut). */
    function isEditableTarget(el) {
        if (!el || el.nodeType !== 1) return false;
        var tag = el.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
        if (el.isContentEditable) return true;
        return typeof el.closest === 'function' && !!el.closest('[contenteditable=""], [contenteditable="true"], [data-tb-editable][contenteditable]');
    }

    function studly(type) {
        return String(type).split(/[_\-\s]+/).filter(Boolean).map(function (part) {
            return part.charAt(0).toUpperCase() + part.slice(1);
        }).join('');
    }

    /** The BlockRenderer method for a type, as the generic dispatch names it; module:* types have none. */
    function rendererMethod(type) {
        return type && String(type).indexOf(':') === -1 ? 'render' + studly(type) : null;
    }

    /**
     * Where the action bar goes: under the widget, else above it, else just
     * inside its top edge; aligned to the inline start (right in RTL) and
     * kept inside the viewport.
     */
    function barPosition(rect, barW, barH, vw, vh, rtl, gap) {
        gap = gap == null ? 8 : gap;
        var top = rect.bottom + gap;
        if (top + barH > vh - gap) top = rect.top - gap - barH;
        if (top < gap) top = Math.max(gap, Math.min(vh - barH - gap, rect.top + gap));
        var left = rtl ? rect.right - barW : rect.left;
        left = Math.max(gap, Math.min(left, vw - barW - gap));
        return { left: Math.round(left), top: Math.round(top) };
    }

    /** The AI task for one widget: what it is, where its code lives, what it holds now, what is wrong. */
    function buildPrompt(info, extra) {
        extra = extra || {};
        var files = Object.assign({}, FILES, info.files || {});
        var method = rendererMethod(info.type);
        var lines = [
            'ویجت «' + (info.label || info.type) + '» (' + info.type + ') را در موتور Canvas بررسی و اصلاح کن.',
            '',
            '- شناسه ویجت: ' + info.type + (info.blockId ? '#' + info.blockId : ''),
            '- رندر: ' + files.renderer + (method ? ' → ' + method + '()' : ''),
            '- رجیستری و اسکیما: ' + files.registry,
            '- استایل: ' + files.css,
        ];
        if (files.host) lines.push('- میزبان: ' + files.host);
        if (extra.page) lines.push('- صفحه: ' + extra.page + (extra.width ? ' در عرض ' + extra.width + 'px' : ''));
        if (info.props && Object.keys(info.props).length) {
            lines.push('', 'مقادیر فعلی:', '```json', JSON.stringify(info.props, null, 2), '```');
        }
        var findings = extra.findings || [];
        if (findings.length) {
            lines.push('', 'ایرادهای پیدا شده:');
            findings.forEach(function (f) {
                lines.push('- [' + (f.severity || 'warning') + '] ' + f.code + ': ' + f.message + (f.selector ? ' (' + f.selector + ')' : ''));
                if (f.remedy) lines.push('  راه رفع: ' + f.remedy);
            });
        }
        lines.push('', 'الزامات: استاندارد تجاری PROJECT_CONSTITUTION.md §16 (RTL، موبایل‌اول از ۳۶۰px، هدف لمسی حداقل ۲۴px و ترجیحاً ۴۴px، فونت محلی وزیرمتن)، بدون رگرسیون، و یک تست که ایراد را ثابت کند.');
        return lines.join('\n');
    }

    var pure = { isToggleShortcut: isToggleShortcut, isEditableTarget: isEditableTarget, studly: studly, rendererMethod: rendererMethod, barPosition: barPosition, buildPrompt: buildPrompt };

    if (typeof root.document === 'undefined') {
        root.TbInspect = Object.freeze(Object.assign({ version: 1 }, pure));
        return;
    }

    /* ── State ────────────────────────────────────────────────────── */

    var doc = root.document;
    var actions = [];
    var badges = [];
    var listeners = {};
    var state = { enabled: false, hovered: null, selected: null, selectedId: null, frame: 0 };
    var ui = null;

    function emit(name, detail) {
        (listeners[name] || []).slice().forEach(function (fn) {
            try { fn(detail); } catch (e) { root.console && console.error('TbInspect listener error [' + name + ']:', e); }
        });
    }

    function on(name, fn) {
        (listeners[name] = listeners[name] || []).push(fn);
        return function () { listeners[name] = (listeners[name] || []).filter(function (f) { return f !== fn; }); };
    }

    function host() {
        try {
            if (root.TbInspectHost) return root.TbInspectHost;
            if (root.parent && root.parent !== root && root.parent.TbInspectHost) return root.parent.TbInspectHost;
        } catch (_) { /* a cross-origin parent is simply no host */ }
        return null;
    }

    function widgetOf(node) {
        if (!node || node.nodeType !== 1 || ownsNode(node)) return null;
        return node.closest(WIDGET);
    }

    function ownsNode(node) {
        return !!(ui && (node === ui.hostEl || ui.hostEl.contains(node)));
    }

    /** Everything an action needs about one widget element. */
    function info(el) {
        var type = el.getAttribute('data-widget-type') || '';
        var props = {};
        var raw = el.getAttribute('data-tb-block-props');
        if (raw) { try { props = JSON.parse(raw) || {}; } catch (_) { props = {}; } }
        var described = {};
        var h = host();
        if (h && typeof h.describe === 'function') {
            try { described = h.describe(type) || {}; } catch (_) { described = {}; }
        }
        return {
            element: el,
            type: type,
            blockId: el.getAttribute('data-tb-block-id') || '',
            props: props,
            label: described.label || '',
            category: described.category || '',
            schema: described.schema || null,
            defaults: described.defaults || null,
            slots: described.slots || [],
            files: described.files || null,
        };
    }

    /* ── UI (shadow root) ─────────────────────────────────────────── */

    var CSS = ''
        + ':host{all:initial}'
        + '*{box-sizing:border-box}'
        + '.box{position:fixed;pointer-events:none;border-radius:4px;display:none;z-index:1}'
        + '.box.on{display:block}'
        + '.hover{outline:2px solid rgb(79 70 229 / .75);outline-offset:-1px;background:rgb(79 70 229 / .05)}'
        + '.sel{outline:2px solid #4f46e5;outline-offset:-1px;box-shadow:0 0 0 4px rgb(79 70 229 / .18)}'
        // :host{all:initial} makes the shadow tree LTR, so sides are physical and follow data-rtl.
        + '.label{position:absolute;left:-2px;bottom:100%;margin-bottom:2px;display:inline-flex;align-items:center;gap:.35rem;max-width:min(28rem,90vw);padding:.15rem .5rem;border-radius:.375rem;background:#4f46e5;color:#fff;font:600 11px/1.6 Vazirmatn,Tahoma,sans-serif;direction:ltr;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
        + '.box[data-rtl] .label{left:auto;right:-2px;flex-direction:row-reverse}'
        + '.label.inside{bottom:auto;top:2px;margin:0;left:2px}'
        + '.box[data-rtl] .label.inside{left:auto;right:2px}'
        + '.label .dot{width:.5rem;height:.5rem;border-radius:999px;flex:none;background:#cbd5e1}'
        + '.label .dot[data-tone="ok"]{background:#22c55e}.label .dot[data-tone="warn"]{background:#f59e0b}.label .dot[data-tone="error"]{background:#ef4444}'
        + '.label .name{font-weight:400;opacity:.85;direction:rtl}'
        + '.bar{position:fixed;z-index:2;display:none;align-items:center;gap:.25rem;padding:.25rem;border:1px solid #e2e8f0;border-radius:.75rem;background:#fff;box-shadow:0 10px 30px -10px rgb(15 23 42 / .35);direction:rtl;font:500 12px/1.5 Vazirmatn,Tahoma,sans-serif;color:#0f172a}'
        + '.bar.on{display:flex}'
        + '.bar button{display:inline-flex;align-items:center;justify-content:center;min-height:2.25rem;padding:0 .75rem;border:0;border-radius:.5rem;background:transparent;color:inherit;font:inherit;cursor:pointer;white-space:nowrap}'
        + '.bar button:hover{background:#eef2ff;color:#3730a3}'
        + '.bar button:focus-visible{outline:2px solid #4f46e5;outline-offset:1px}'
        + '.bar .close{min-width:2.25rem;padding:0;color:#64748b;font-size:16px}'
        + '.toast{position:fixed;z-index:3;bottom:1rem;left:50%;transform:translateX(-50%);padding:.5rem .9rem;border-radius:.625rem;background:#0f172a;color:#fff;font:500 12px/1.5 Vazirmatn,Tahoma,sans-serif;direction:rtl;opacity:0;transition:opacity .15s;pointer-events:none}'
        + '.toast.on{opacity:1}'
        + '@media (prefers-reduced-motion: reduce){.toast{transition:none}}';

    var PAGE_CSS = 'html[data-tb-inspect] ' + WIDGET + '{outline:1px dashed rgb(79 70 229 / .35);outline-offset:-1px;cursor:crosshair}';

    function makeBox(cls) {
        var box = doc.createElement('div');
        box.className = 'box ' + cls;
        var label = doc.createElement('div');
        label.className = 'label';
        label.innerHTML = '<span class="dot" aria-hidden="true"></span><span class="type"></span><span class="name"></span>';
        box.appendChild(label);
        return box;
    }

    function ensureUi() {
        if (ui) return ui;
        var hostEl = doc.createElement('div');
        hostEl.setAttribute(UI_ATTR, '');
        var shadow = hostEl.attachShadow({ mode: 'open' });
        var style = doc.createElement('style');
        style.textContent = CSS;
        var hover = makeBox('hover');
        var sel = makeBox('sel');
        var bar = doc.createElement('div');
        bar.className = 'bar';
        bar.setAttribute('role', 'toolbar');
        bar.setAttribute('aria-label', 'اکشن‌های ویجت');
        var toast = doc.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        shadow.append(style, hover, sel, bar, toast);
        var pageStyle = doc.createElement('style');
        pageStyle.setAttribute(UI_ATTR, '');
        pageStyle.textContent = PAGE_CSS;
        (doc.head || doc.documentElement).appendChild(pageStyle);
        doc.documentElement.appendChild(hostEl);
        bar.addEventListener('click', onBarClick);
        ui = { hostEl: hostEl, hover: hover, sel: sel, bar: bar, toast: toast, toastTimer: 0 };
        return ui;
    }

    function badgeFor(widgetInfo) {
        var worst = null;
        var rank = { ok: 0, warn: 1, error: 2 };
        badges.forEach(function (fn) {
            try {
                var b = fn(widgetInfo);
                if (b && rank[b.tone] != null && (!worst || rank[b.tone] > rank[worst.tone])) worst = b;
            } catch (_) { /* a broken badge provider never breaks inspection */ }
        });
        return worst;
    }

    function pageIsRtl() {
        var attr = doc.documentElement.getAttribute('dir');
        return (attr || root.getComputedStyle(doc.body || doc.documentElement).direction) === 'rtl';
    }

    function paintBox(box, el, rtl) {
        if (!el || !el.isConnected) { box.classList.remove('on'); return; }
        var r = el.getBoundingClientRect();
        if (r.width === 0 && r.height === 0) { box.classList.remove('on'); return; }
        box.toggleAttribute('data-rtl', rtl);
        box.style.left = r.left + 'px';
        box.style.top = r.top + 'px';
        box.style.width = r.width + 'px';
        box.style.height = r.height + 'px';
        var wi = info(el);
        var label = box.firstChild;
        label.classList.toggle('inside', r.top < 22);
        label.querySelector('.type').textContent = wi.type;
        label.querySelector('.name').textContent = wi.label;
        var dot = label.querySelector('.dot');
        var badge = badgeFor(wi);
        dot.setAttribute('data-tone', badge ? badge.tone : '');
        dot.title = badge && badge.text ? badge.text : '';
        box.classList.add('on');
    }

    function paintBar(rtl) {
        var bar = ui.bar;
        var el = state.selected;
        if (!el || !el.isConnected) { bar.classList.remove('on'); return; }
        bar.classList.add('on');
        var r = el.getBoundingClientRect();
        var pos = barPosition(r, bar.offsetWidth, bar.offsetHeight, root.innerWidth, root.innerHeight, rtl);
        bar.style.left = pos.left + 'px';
        bar.style.top = pos.top + 'px';
    }

    function renderBar() {
        var bar = ui.bar;
        bar.textContent = '';
        if (!state.selected) return;
        var wi = info(state.selected);
        actions.slice().sort(function (a, b) { return a.order - b.order; }).forEach(function (action) {
            var ok = true;
            try { ok = !action.when || action.when(wi); } catch (_) { ok = false; }
            if (!ok) return;
            var b = doc.createElement('button');
            b.type = 'button';
            b.textContent = action.label;
            b.setAttribute('data-action', action.id);
            if (action.title) b.title = action.title;
            bar.appendChild(b);
        });
        var close = doc.createElement('button');
        close.type = 'button';
        close.className = 'close';
        close.setAttribute('data-action', '__close');
        close.setAttribute('aria-label', 'بستن');
        close.textContent = '×';
        bar.appendChild(close);
    }

    function schedule() {
        if (state.frame || !ui) return;
        state.frame = root.requestAnimationFrame(function () {
            state.frame = 0;
            if (state.selectedId && state.selected && !state.selected.isConnected) {
                // The editor replaced the node (live prop edit): follow the block id.
                state.selected = doc.querySelector('[data-tb-block-id="' + cssEscape(state.selectedId) + '"]');
                renderBar();
            }
            var rtl = pageIsRtl();
            paintBox(ui.hover, state.hovered && state.hovered !== state.selected ? state.hovered : null, rtl);
            paintBox(ui.sel, state.selected, rtl);
            paintBar(rtl);
        });
    }

    function cssEscape(value) {
        return root.CSS && typeof root.CSS.escape === 'function' ? root.CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&');
    }

    function toast(text) {
        var t = ensureUi().toast;
        t.textContent = text;
        // The editor grows its preview iframe to the full content height, so
        // the frame's bottom can be far off screen: show the toast by the bar.
        var bar = ui.bar;
        if (bar.classList.contains('on')) {
            var r = bar.getBoundingClientRect();
            t.style.top = Math.max(8, r.top - 44) + 'px';
            t.style.bottom = 'auto';
            t.style.left = (r.left + r.width / 2) + 'px';
        } else {
            t.style.top = '';
            t.style.bottom = '';
            t.style.left = '';
        }
        t.classList.add('on');
        root.clearTimeout(ui.toastTimer);
        ui.toastTimer = root.setTimeout(function () { t.classList.remove('on'); }, 1800);
    }

    function copy(text) {
        function fallback() {
            var area = doc.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
            doc.body.appendChild(area);
            area.select();
            var ok = false;
            try { ok = doc.execCommand('copy'); } catch (_) { ok = false; }
            area.remove();
            return ok;
        }
        if (root.navigator && root.navigator.clipboard && typeof root.navigator.clipboard.writeText === 'function') {
            return root.navigator.clipboard.writeText(text).then(function () { return true; }, function () { return fallback(); });
        }
        return Promise.resolve(fallback());
    }

    function onBarClick(e) {
        var button = e.target.closest('button[data-action]');
        if (!button || !state.selected) return;
        var id = button.getAttribute('data-action');
        if (id === '__close') { select(null); return; }
        var action = actions.find(function (a) { return a.id === id; });
        if (!action) return;
        var wi = info(state.selected);
        Promise.resolve().then(function () { return action.run(wi, api); }).then(function (text) {
            if (typeof text !== 'string' || text === '') return;
            return copy(text).then(function (ok) { toast(ok ? 'کپی شد: ' + action.label : 'کپی نشد؛ مرورگر اجازه دسترسی به کلیپ‌بورد نداد'); });
        }).catch(function (err) {
            toast('اجرای «' + action.label + '» ناموفق بود');
            root.console && console.error('TbInspect action error [' + id + ']:', err);
        });
    }

    /* ── Pointer and keyboard ─────────────────────────────────────── */

    function onMove(e) {
        var w = widgetOf(e.target);
        if (w !== state.hovered) { state.hovered = w; schedule(); emit('hover', w ? info(w) : null); }
    }

    function onLeave(e) {
        if (!e.relatedTarget) { state.hovered = null; schedule(); }
    }

    function onClick(e) {
        if (ownsNode(e.target)) return;
        var w = widgetOf(e.target);
        if (!w) return;
        // Inspecting must not follow links or submit forms; the editor's own
        // selection still runs (no stopPropagation).
        if (e.target.closest('a[href], button[type="submit"], input[type="submit"]')) e.preventDefault();
        select(w);
    }

    function onKey(e) {
        if (isToggleShortcut(e) && !isEditableTarget(e.target)) {
            if (!state.enabled && !doc.querySelector(WIDGET)) return;
            e.preventDefault();
            toggle();
            return;
        }
        if (e.key === 'Escape' && state.enabled && state.selected && !isEditableTarget(e.target)) select(null);
    }

    function select(el) {
        state.selected = el || null;
        state.selectedId = el ? el.getAttribute('data-tb-block-id') : null;
        if (!ui) return;
        renderBar();
        schedule();
        emit('select', el ? info(el) : null);
    }

    function enable() {
        if (state.enabled) return;
        state.enabled = true;
        ensureUi();
        doc.documentElement.setAttribute('data-tb-inspect', '');
        root.addEventListener('mousemove', onMove, { capture: true, passive: true });
        doc.addEventListener('mouseout', onLeave, true);
        root.addEventListener('click', onClick, true);
        root.addEventListener('scroll', schedule, { capture: true, passive: true });
        root.addEventListener('resize', schedule, { passive: true });
        doc.addEventListener('theme:canvas-lifecycle', schedule);
        notifyHost(true);
        emit('toggle', true);
        schedule();
    }

    function disable() {
        if (!state.enabled) return;
        state.enabled = false;
        doc.documentElement.removeAttribute('data-tb-inspect');
        root.removeEventListener('mousemove', onMove, true);
        doc.removeEventListener('mouseout', onLeave, true);
        root.removeEventListener('click', onClick, true);
        root.removeEventListener('scroll', schedule, true);
        root.removeEventListener('resize', schedule);
        doc.removeEventListener('theme:canvas-lifecycle', schedule);
        state.hovered = null;
        select(null);
        if (ui) { ui.hover.classList.remove('on'); ui.sel.classList.remove('on'); ui.bar.classList.remove('on'); }
        notifyHost(false);
        emit('toggle', false);
    }

    function toggle() { if (state.enabled) disable(); else enable(); }

    function notifyHost(enabled) {
        var h = host();
        if (h && h !== root.TbInspectHost__self && typeof h.changed === 'function') {
            try { h.changed(enabled); } catch (_) { /* the host UI is optional */ }
        }
    }

    function registerAction(action) {
        if (!action || typeof action.id !== 'string' || !action.id || typeof action.run !== 'function') throw new TypeError('TbInspect action needs an id and run()');
        actions = actions.filter(function (a) { return a.id !== action.id; });
        actions.push(Object.assign({ order: 100, label: action.id }, action));
        if (state.selected) renderBar();
        return function () { actions = actions.filter(function (a) { return a.id !== action.id; }); if (state.selected) renderBar(); };
    }

    function registerBadge(fn) {
        if (typeof fn !== 'function') throw new TypeError('TbInspect badge must be a function');
        badges.push(fn);
        schedule();
        return function () { badges = badges.filter(function (b) { return b !== fn; }); schedule(); };
    }

    /**
     * The parent editor's side: exposes window.TbInspectHost to its preview
     * frame and owns the toolbar state and the shortcut while focus is in the
     * editor chrome (key events inside the frame never reach the parent).
     */
    var hostController = null;

    function attachHost(options) {
        // Idempotent: an editor whose init runs twice (Alpine x-init="init()"
        // plus the automatic init()) must not get two shortcut listeners,
        // which would toggle on and straight back off.
        if (hostController) { hostController.options = options; return hostController.api; }
        var ctl = hostController = { options: options, api: null };
        var wanted = false;
        function frameApi() {
            try {
                var f = ctl.options.frame && ctl.options.frame();
                return f && f.contentWindow && f.contentWindow.TbInspect && f.contentWindow.TbInspect !== api ? f.contentWindow.TbInspect : null;
            } catch (_) { return null; }
        }
        function changed(enabled) {
            wanted = !!enabled;
            if (typeof ctl.options.onChange === 'function') ctl.options.onChange(wanted);
        }
        function set(enabled) {
            var target = frameApi();
            if (target) { if (enabled) target.enable(); else target.disable(); }
            changed(enabled);
        }
        root.TbInspectHost = {
            describe: function (type) { return typeof ctl.options.describe === 'function' ? ctl.options.describe(type) : null; },
            wanted: function () { return wanted; },
            changed: changed,
        };
        root.TbInspectHost__self = root.TbInspectHost;
        root.addEventListener('keydown', function (e) {
            if (isToggleShortcut(e) && !isEditableTarget(e.target)) { e.preventDefault(); set(!wanted); }
        });
        ctl.api = { set: set, toggle: function () { set(!wanted); }, enabled: function () { return wanted; } };
        return ctl.api;
    }

    /* ── Built-in actions ─────────────────────────────────────────── */

    registerAction({ id: 'copy-type', label: 'کپی شناسه', order: 10, title: 'شناسه رسمی ویجت (data-widget-type)', run: function (wi) { return wi.type; } });
    registerAction({
        id: 'copy-schema', label: 'کپی اسکیما', order: 20,
        when: function (wi) { return !!wi.schema; },
        run: function (wi) { return JSON.stringify({ type: wi.type, label: wi.label, category: wi.category, slots: wi.slots, schema: wi.schema, defaults: wi.defaults }, null, 2); },
    });
    registerAction({
        id: 'copy-prompt', label: 'کپی پرامپت AI', order: 30,
        run: function (wi) { return buildPrompt(wi, { page: root.location.pathname, width: root.innerWidth }); },
    });

    var api = Object.freeze(Object.assign({
        version: 1,
        enable: enable,
        disable: disable,
        toggle: toggle,
        isEnabled: function () { return state.enabled; },
        select: select,
        selected: function () { return state.selected ? info(state.selected) : null; },
        info: info,
        copy: copy,
        toast: toast,
        on: on,
        registerAction: registerAction,
        registerBadge: registerBadge,
        refresh: schedule,
        attachHost: attachHost,
    }, pure));
    root.TbInspect = api;

    function boot() {
        var h = host();
        if (h && h !== root.TbInspectHost__self && typeof h.wanted === 'function' && h.wanted() && doc.querySelector(WIDGET)) enable();
    }
    root.addEventListener('keydown', function (e) {
        // Widget documents own their shortcut; the parent's comes from attachHost().
        if (root.TbInspectHost__self) return;
        onKey(e);
    }, true);
    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
}(typeof window !== 'undefined' ? window : globalThis));
