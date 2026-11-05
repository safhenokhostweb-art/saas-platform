/*
 * Canvas Design HUD (WU-2): live, per-widget checks while Inspect Mode is on.
 *
 * The rules are the Sentinel UI suite's (tests/sentinel/support/measure.ts),
 * scoped to one widget and using the same finding codes, so a HUD finding
 * and a Sentinel finding mean the same thing:
 *
 *   WIDGET_OVERFLOW          blocker  an element crosses the viewport edge and no ancestor clips it
 *   WIDGET_CONTENT_OVERFLOW  warning  content sticks out of its widget box by more than 2 px
 *   TEXT_CLIPPED             warning  nowrap + overflow hidden text cut off without an ellipsis/title
 *   TAP_TARGET_SMALL         warning  a target under 24x24 px crowded by another (WCAG 2.2 SC 2.5.8)
 *   BROKEN_IMAGE             warning  an image that does not load
 *   DEAD_LINK                warning  a visible link to "#" (the site leaves these out)
 *   RTL_MISSING              warning  an LTR widget on an RTL page
 * plus two the HUD adds:
 *   CONTRAST_LOW             warning  text under 4.5:1 (3:1 for large text) on a solid background
 *   TAP_TARGET_COMFORT       info     a phone-width target under 44x44 px (the Constitution's target)
 *
 * Work never blocks a frame: a ResizeObserver, preview-width changes, image
 * loads and Canvas lifecycle updates only queue the widget; the queue drains
 * in idle callbacks with a per-widget element budget.
 *
 * Results reach TbInspect (label dot, "کپی تسک رفع ایراد" action) and the
 * editor through TbInspectHost.audit(summary) for its toolbar and sidebar.
 *
 * WU-3: the checks run while anyone holds them (hold(name, on)): Inspect
 * Mode, the editor's «سلامت ویجت» tab (TbInspectHost.auditWanted(), which
 * survives a preview reload) or start()/stop(). fixTask(key) and
 * reveal(key) serve that tab by summary key (type#blockId).
 *
 * parseColor(), contrastRatio(), isLargeText(), crowded() and tone() are pure
 * and unit-tested (tests/js/canvas-audit.test.mjs).
 */
(function (root) {
    'use strict';

    if (root.TbAudit && root.TbAudit.version === 1) return;

    /* ── Pure helpers ─────────────────────────────────────────────── */

    /** rgb()/rgba() (space or comma syntax) or #hex → {r,g,b,a}; anything else → null. */
    function parseColor(value) {
        if (typeof value !== 'string') return null;
        var v = value.trim().toLowerCase();
        var hex = /^#([0-9a-f]{3,8})$/.exec(v);
        if (hex) {
            var h = hex[1];
            if (h.length === 3 || h.length === 4) h = h.split('').map(function (c) { return c + c; }).join('');
            if (h.length !== 6 && h.length !== 8) return null;
            return { r: parseInt(h.slice(0, 2), 16), g: parseInt(h.slice(2, 4), 16), b: parseInt(h.slice(4, 6), 16), a: h.length === 8 ? parseInt(h.slice(6, 8), 16) / 255 : 1 };
        }
        var m = /^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)$/.exec(v);
        if (!m) return null;
        var a = m[4] == null ? 1 : (m[4].slice(-1) === '%' ? parseFloat(m[4]) / 100 : parseFloat(m[4]));
        return { r: +m[1], g: +m[2], b: +m[3], a: a };
    }

    function blend(top, bottom) {
        var a = top.a;
        return { r: top.r * a + bottom.r * (1 - a), g: top.g * a + bottom.g * (1 - a), b: top.b * a + bottom.b * (1 - a), a: 1 };
    }

    function luminance(c) {
        function ch(v) { v /= 255; return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }
        return 0.2126 * ch(c.r) + 0.7152 * ch(c.g) + 0.0722 * ch(c.b);
    }

    /** WCAG contrast of a (possibly translucent) text colour on an opaque background. */
    function contrastRatio(fg, bg) {
        var f = fg.a < 1 ? blend(fg, bg) : fg;
        var l1 = luminance(f);
        var l2 = luminance(bg);
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    }

    /** WCAG large text: 24 px, or 18.66 px (14 pt) bold. */
    function isLargeText(fontSizePx, fontWeight) {
        return fontSizePx >= 24 || (fontSizePx >= 18.66 && Number(fontWeight) >= 700);
    }

    /**
     * Indexes of undersized (<24 px) targets that fail the SC 2.5.8 spacing
     * exception: a 24 px circle on their centre touches another target, or
     * another undersized target's circle. nested(i, j) excludes a target
     * containing another.
     */
    function crowded(rects, nested) {
        nested = nested || function () { return false; };
        function centre(r) { return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; }
        function hits(c, r) {
            var dx = Math.max(r.left - c.x, 0, c.x - r.right);
            var dy = Math.max(r.top - c.y, 0, c.y - r.bottom);
            return dx * dx + dy * dy < 144;
        }
        function small(r) { return r.width < 24 || r.height < 24; }
        var out = [];
        rects.forEach(function (r, i) {
            if (!small(r)) return;
            var c = centre(r);
            var bad = rects.some(function (o, j) {
                if (j === i || nested(i, j)) return false;
                if (hits(c, o)) return true;
                if (!small(o)) return false;
                var oc = centre(o);
                return Math.hypot(oc.x - c.x, oc.y - c.y) < 24;
            });
            if (bad) out.push(i);
        });
        return out;
    }

    /** The traffic light for a list of findings. */
    function tone(findings) {
        if (!findings || !findings.length) return 'ok';
        if (findings.some(function (f) { return f.severity === 'blocker'; })) return 'error';
        if (findings.some(function (f) { return f.severity === 'warning'; })) return 'warn';
        return 'ok';
    }

    var pure = { parseColor: parseColor, contrastRatio: contrastRatio, isLargeText: isLargeText, crowded: crowded, tone: tone };

    if (typeof root.document === 'undefined') {
        root.TbAudit = Object.freeze(Object.assign({ version: 1 }, pure));
        return;
    }

    /* ── Checks ───────────────────────────────────────────────────── */

    var doc = root.document;
    var WIDGET = '[data-widget-type]';
    // The editor's own chrome inside a block wrapper (BlockRenderer::wrapEditorMode()
    // and the preview's drop zones) is not site content: never audited.
    var EDITOR_CHROME = '.tb-drag-handle, .tb-block-badge, .tb-drop-zone, [data-tb-editor-chrome]';

    // A widget's own root may repeat the block wrapper's hook (the header
    // root carries it for live selection): the outermost element of one
    // block id is the widget, and its inner twin is part of its content.
    function widgetRoot(el) {
        var id = el && el.getAttribute('data-tb-block-id');
        if (!id) return el;
        for (var up = el.parentElement && el.parentElement.closest(WIDGET); up && up.getAttribute('data-tb-block-id') === id; up = up.parentElement && up.parentElement.closest(WIDGET)) el = up;
        return el;
    }
    var BUDGET = 600;
    var REMEDY = {
        WIDGET_OVERFLOW: 'عرض ثابت، کلمه‌های بلند یا ستون‌های grid را مهار کن (min-width:0، عرض منطقی، شکستن خط) تا صفحه افقی اسکرول نخورد.',
        WIDGET_CONTENT_OVERFLOW: 'محتوا را داخل جعبه ویجت نگه دار (overflow-wrap:anywhere، min-width:0 روی فرزندان flex/grid) یا عمداً برش بده.',
        TEXT_CLIPPED: 'اجازه بده متن بشکند یا جا داشته باشد؛ بدون ellipsis یا title بریده شده است.',
        TAP_TARGET_SMALL: 'هدف لمسی را حداقل ۲۴×۲۴ پیکسل کن یا فاصله‌اش را طوری بده که دایره ۲۴ پیکسلی دورش به هدف دیگری نخورد (WCAG 2.2 SC 2.5.8).',
        TAP_TARGET_COMFORT: 'روی موبایل هدف لمسی را ۴۴×۴۴ پیکسل کن (استاندارد قانون اساسی §16).',
        BROKEN_IMAGE: 'تصویر را دوباره بارگذاری یا لینک کن؛ آدرس بارگذاری نمی‌شود.',
        DEAD_LINK: 'برای لینک مقصد واقعی تعیین کن یا حذفش کن (قانون اساسی §16، تعامل صادقانه).',
        RTL_MISSING: 'ویجت را راست‌به‌چپ رندر کن (dir/direction از صفحه به ارث برسد).',
        CONTRAST_LOW: 'رنگ متن یا پس‌زمینه را تغییر بده تا کنتراست حداقل ۴٫۵:۱ (متن بزرگ ۳:۱) شود؛ از جفت‌رنگ‌های --tb-on-* استفاده کن.',
    };

    var results = new Map();   // element → { key, type, blockId, findings }
    var queue = new Set();
    var idleHandle = 0;
    var enabled = false;
    var ro = null;
    var listeners = [];
    var summaryTimer = 0;

    function describe(el) {
        var tag = el.tagName.toLowerCase();
        if (el.id) return tag + '#' + el.id;
        var cls = typeof el.className === 'string' ? el.className.trim().split(/\s+/).filter(Boolean).slice(0, 3) : [];
        return tag + (cls.length ? '.' + cls.join('.') : '');
    }

    function visible(el, cs) {
        cs = cs || root.getComputedStyle(el);
        if (cs.display === 'none' || cs.visibility === 'hidden' || Number(cs.opacity) === 0) return false;
        if (cs.clip && cs.clip !== 'auto') return false;
        var r = el.getBoundingClientRect();
        return r.width > 2 && r.height > 2;
    }

    function clips(cs) { return ['hidden', 'clip', 'auto', 'scroll'].indexOf(cs.overflowX) !== -1; }

    function ancestorsClip(el) {
        for (var p = el.parentElement; p && p !== doc.body; p = p.parentElement) {
            if (clips(root.getComputedStyle(p))) return true;
        }
        return false;
    }

    /** The first solid background under an element; null when an image or gradient decides it. */
    function backgroundOf(el) {
        for (var n = el; n && n.nodeType === 1; n = n.parentElement) {
            var cs = root.getComputedStyle(n);
            if (cs.backgroundImage && cs.backgroundImage !== 'none') return null;
            var c = parseColor(cs.backgroundColor);
            if (c && c.a >= 0.95) return c;
            if (c && c.a > 0.05) return null; // a translucent layer over something unknown
        }
        return { r: 255, g: 255, b: 255, a: 1 };
    }

    function ownText(el) {
        var text = '';
        for (var i = 0; i < el.childNodes.length; i++) {
            var n = el.childNodes[i];
            if (n.nodeType === 3) text += n.textContent;
        }
        return text.trim();
    }

    function auditWidget(widget) {
        var findings = [];
        function add(code, severity, message, el) {
            findings.push({ code: code, severity: severity, message: message, selector: el ? describe(el) : '', remedy: REMEDY[code] || '' });
        }
        var wcs = root.getComputedStyle(widget);
        if (!visible(widget, wcs)) return findings;
        var blockId = widget.getAttribute('data-tb-block-id');
        var vw = doc.documentElement.clientWidth;
        var box = widget.getBoundingClientRect();
        var pageRtl = (doc.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';
        if (pageRtl && wcs.direction !== 'rtl') add('RTL_MISSING', 'warning', 'صفحه راست‌به‌چپ است ولی این ویجت چپ‌به‌راست رندر می‌شود.', widget);

        var seen = 0;
        var targets = [];
        var contrastHits = 0;
        var overflowHits = 0;
        var clippedRoot = ancestorsClip(widget);
        // quiet: an ancestor was already reported as overflowing. Only the
        // outermost culprit of a branch is an overflow finding (Sentinel's
        // rule), but every other check still runs inside it.
        function walk(el, clipped, inBox, quiet) {
            for (var i = 0; i < el.children.length; i++) {
                var child = el.children[i];
                if (++seen > BUDGET) return;
                // Nested widgets are audited on their own; the widget's inner twin is not nested.
                if (child.matches(WIDGET) && (!blockId || child.getAttribute('data-tb-block-id') !== blockId)) continue;
                if (child.matches(EDITOR_CHROME)) continue;
                var cs = root.getComputedStyle(child);
                if (cs.display === 'none') continue;
                var r = child.getBoundingClientRect();
                var flowing = cs.position !== 'absolute' && cs.position !== 'fixed';
                var reported = false;
                if (!quiet && !clipped && cs.position !== 'fixed' && r.width > 0 && (r.right > vw + 1 || r.left < -1)) {
                    if (overflowHits++ < 3) add('WIDGET_OVERFLOW', 'blocker', describe(child) + ' از لبه پیش‌نمایش بیرون زده (' + Math.round(r.left) + '..' + Math.round(r.right) + 'px از ' + vw + 'px).', child);
                    reported = true;
                } else if (!quiet && inBox && flowing && r.width > 0) {
                    var by = Math.max(r.right - box.right, box.left - r.left);
                    if (by > 2) {
                        if (overflowHits++ < 3) add('WIDGET_CONTENT_OVERFLOW', 'warning', describe(child) + ' ' + Math.round(by) + 'px از جعبه ویجت بیرون زده است.', child);
                        reported = true;
                    }
                }
                inspectNode(child, cs, r);
                walk(child, clipped || clips(cs), inBox && !clips(cs), quiet || reported);
            }
        }
        function inspectNode(el, cs, r) {
            if (cs.visibility === 'hidden' || Number(cs.opacity) === 0 || r.width <= 2 || r.height <= 2) return;
            var tag = el.tagName;
            if (tag === 'IMG' && el.complete && el.naturalWidth === 0 && (el.currentSrc || el.src)) {
                add('BROKEN_IMAGE', 'warning', 'تصویر بارگذاری نمی‌شود: ' + String(el.currentSrc || el.src).slice(0, 160), el);
            }
            if (tag === 'A') {
                var href = el.getAttribute('href');
                if (href === '#' || href === '') {
                    add('DEAD_LINK', 'warning', 'لینک «' + (el.textContent || '').trim().slice(0, 40) + '» مقصد ندارد (href="#")' + (el.hasAttribute('data-tb-dead-link') ? ' و در سایت نمایش داده نمی‌شود.' : '.'), el);
                }
            }
            if (el.matches('a[href], button, input:not([type="hidden"]), select, [role="button"]') && !el.closest('p, [data-tb-inline-text]')) {
                targets.push({ el: el, r: r });
            }
            if (cs.whiteSpace === 'nowrap' && cs.overflowX === 'hidden' && cs.textOverflow !== 'ellipsis' && !el.getAttribute('title')) {
                var t = ownText(el);
                if (t && el.scrollWidth > el.clientWidth + 1) add('TEXT_CLIPPED', 'warning', 'متن بریده شده: «' + t.slice(0, 60) + '».', el);
            }
            if (contrastHits < 3) {
                var text = ownText(el);
                if (text.length > 1) {
                    var fg = parseColor(cs.color);
                    var bg = fg && backgroundOf(el);
                    if (fg && bg) {
                        var size = parseFloat(cs.fontSize) || 16;
                        var need = isLargeText(size, cs.fontWeight) ? 3 : 4.5;
                        var ratio = contrastRatio(fg, bg);
                        if (ratio < need) {
                            add('CONTRAST_LOW', 'warning', 'کنتراست «' + text.slice(0, 30) + '» ' + ratio.toFixed(2) + ':1 است (حداقل ' + need + ':1).', el);
                            contrastHits++;
                        }
                    }
                }
            }
        }
        walk(widget, clippedRoot || clips(wcs), !clips(wcs), false);

        var rects = targets.map(function (t) { return t.r; });
        crowded(rects, function (i, j) { return targets[i].el.contains(targets[j].el) || targets[j].el.contains(targets[i].el); }).slice(0, 5).forEach(function (i) {
            var r = rects[i];
            add('TAP_TARGET_SMALL', 'warning', describe(targets[i].el) + ' ' + Math.round(r.width) + '×' + Math.round(r.height) + 'px است و هدف لمسی دیگری کنارش است.', targets[i].el);
        });
        if (vw < 768) {
            var comfort = targets.filter(function (t) { return t.r.width < 44 || t.r.height < 44; });
            if (comfort.length) {
                var first = comfort[0];
                add('TAP_TARGET_COMFORT', 'info', comfort.length + ' هدف لمسی زیر ۴۴×۴۴ پیکسل در عرض موبایل؛ اولی ' + describe(first.el) + ' (' + Math.round(first.r.width) + '×' + Math.round(first.r.height) + 'px).', first.el);
            }
        }
        return findings;
    }

    /* ── Scheduling ───────────────────────────────────────────────── */

    var idle = root.requestIdleCallback
        ? function (fn) { return root.requestIdleCallback(fn, { timeout: 500 }); }
        : function (fn) { return root.setTimeout(function () { fn({ timeRemaining: function () { return 8; }, didTimeout: false }); }, 16); };

    function enqueue(el) {
        el = widgetRoot(el);
        if (!enabled || !el || !el.isConnected) return;
        queue.add(el);
        if (!idleHandle) idleHandle = idle(drain);
    }

    function enqueueAll() { doc.querySelectorAll(WIDGET).forEach(enqueue); }

    function drain(deadline) {
        idleHandle = 0;
        var changed = false;
        var it = queue.values();
        while (queue.size && (deadline.didTimeout || deadline.timeRemaining() > 4)) {
            var el = it.next().value;
            queue.delete(el);
            if (!el.isConnected) { results.delete(el); changed = true; continue; }
            var findings;
            try { findings = auditWidget(el); } catch (e) { findings = []; root.console && console.error('TbAudit check error:', e); }
            var type = el.getAttribute('data-widget-type') || '';
            var blockId = el.getAttribute('data-tb-block-id') || '';
            results.set(el, { key: type + (blockId ? '#' + blockId : ''), type: type, blockId: blockId, tone: tone(findings), findings: findings });
            changed = true;
        }
        if (queue.size) idleHandle = idle(drain);
        if (changed) publish();
    }

    function summary() {
        var counts = { blocker: 0, warning: 0, info: 0 };
        var widgets = [];
        results.forEach(function (res, el) {
            if (!el.isConnected) return;
            res.findings.forEach(function (f) { counts[f.severity] = (counts[f.severity] || 0) + 1; });
            widgets.push({ key: res.key, type: res.type, blockId: res.blockId, tone: res.tone, findings: res.findings });
        });
        return { width: doc.documentElement.clientWidth, counts: counts, tone: counts.blocker ? 'error' : (counts.warning ? 'warn' : 'ok'), widgets: widgets };
    }

    function publish() {
        if (root.TbInspect) root.TbInspect.refresh();
        root.clearTimeout(summaryTimer);
        summaryTimer = root.setTimeout(function () {
            var s = summary();
            listeners.slice().forEach(function (fn) { try { fn(s); } catch (_) { /* listeners are optional */ } });
            var h = hostOf();
            if (h && typeof h.audit === 'function') { try { h.audit(s); } catch (_) { /* the host UI is optional */ } }
        }, 120);
    }

    function hostOf() {
        try {
            if (root.TbInspectHost && root.TbInspectHost !== root.TbInspectHost__self) return root.TbInspectHost;
            if (root.parent && root.parent !== root && root.parent.TbInspectHost) return root.parent.TbInspectHost;
        } catch (_) { /* cross-origin parent */ }
        return null;
    }

    function onLifecycle(e) {
        var r = e.detail && e.detail.root;
        if (r && r.nodeType === 1) {
            var w = r.matches(WIDGET) ? r : r.closest(WIDGET);
            if (w) { observe(w); enqueue(w); }
            r.querySelectorAll && r.querySelectorAll(WIDGET).forEach(function (n) { observe(n); enqueue(n); });
        } else {
            observeAll();
            enqueueAll();
        }
    }

    function onAsset(e) {
        var t = e.target;
        if (t && t.tagName === 'IMG') enqueue(t.closest(WIDGET));
    }

    var lastWidth = 0;
    function onResize() {
        var w = doc.documentElement.clientWidth;
        if (w !== lastWidth) { lastWidth = w; enqueueAll(); }
    }

    function observe(el) { if (ro) ro.observe(el); }
    function observeAll() { doc.querySelectorAll(WIDGET).forEach(observe); }

    function start() {
        if (enabled) return;
        enabled = true;
        lastWidth = doc.documentElement.clientWidth;
        if (root.ResizeObserver) {
            ro = new root.ResizeObserver(function (entries) { entries.forEach(function (en) { enqueue(en.target); }); });
            observeAll();
        }
        root.addEventListener('resize', onResize, { passive: true });
        doc.addEventListener('theme:canvas-lifecycle', onLifecycle);
        doc.addEventListener('load', onAsset, true);
        doc.addEventListener('error', onAsset, true);
        enqueueAll();
    }

    function stop() {
        if (!enabled) return;
        enabled = false;
        if (ro) { ro.disconnect(); ro = null; }
        root.removeEventListener('resize', onResize);
        doc.removeEventListener('theme:canvas-lifecycle', onLifecycle);
        doc.removeEventListener('load', onAsset, true);
        doc.removeEventListener('error', onAsset, true);
        queue.clear();
        results.clear();
        publish();
    }

    // WU-3: several owners keep the checks running — Inspect Mode, the
    // editor's «سلامت ویجت» tab ('host'), a direct start() ('api'). The
    // checks stop only when the last one lets go.
    var holds = {};
    function hold(name, on) {
        holds[name] = !!on;
        if (Object.keys(holds).some(function (k) { return holds[k]; })) start(); else stop();
    }

    function findingsFor(el) {
        var res = el && results.get(widgetRoot(el));
        return res ? res.findings : [];
    }

    /** The audited widget for a summary key (type#blockId), else the first widget with that block id. */
    function widgetFor(key) {
        var hit = null;
        results.forEach(function (res, el) { if (!hit && el.isConnected && res.key === key) hit = el; });
        if (hit) return hit;
        var id = String(key || '').split('#')[1];
        return id ? doc.querySelector(WIDGET + '[data-tb-block-id="' + (root.CSS && root.CSS.escape ? root.CSS.escape(id) : id) + '"]') : null;
    }

    /** WU-3: the «کپی پرامپت اصلاحی» text for one widget — the same prompt as the Inspect action. */
    function fixTask(key) {
        var el = widgetFor(key);
        var I = root.TbInspect;
        if (!el || !I || typeof I.info !== 'function') return null;
        var findings = results.has(el) ? findingsFor(el) : auditWidget(el);
        return I.buildPrompt(I.info(el), { page: root.location.pathname, width: doc.documentElement.clientWidth, findings: findings });
    }

    /** Scrolls a widget into view in its own document (the editor selects it by id on its side). */
    function reveal(key) {
        var el = widgetFor(key);
        if (!el) return false;
        try { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (_) { el.scrollIntoView(); }
        return true;
    }

    /* ── Inspect integration ──────────────────────────────────────── */

    var LABEL = { error: 'خطا', warn: 'هشدار', ok: 'سالم' };
    function hookInspect(I) {
        I.registerBadge(function (info) {
            if (!enabled || !results.has(info.element)) return null;
            var res = results.get(info.element);
            var n = res.findings.filter(function (f) { return f.severity !== 'info'; }).length;
            return { tone: res.tone, text: LABEL[res.tone] + (n ? ' (' + n + ')' : '') };
        });
        I.registerAction({
            id: 'copy-fix-task', label: 'کپی تسک رفع ایراد', order: 40,
            title: 'مشخصات ایرادها، نام ویجت و فایل‌ها به‌صورت پرامپت آماده',
            when: function (info) { return findingsFor(info.element).some(function (f) { return f.severity !== 'info'; }); },
            run: function (info) { return I.buildPrompt(info, { page: root.location.pathname, width: doc.documentElement.clientWidth, findings: findingsFor(info.element) }); },
        });
        I.on('toggle', function (on) { hold('inspect', on); });
        I.on('select', function () { /* re-render picks up copy-fix-task availability */ });
        if (I.isEnabled()) hold('inspect', true);
    }

    var api = Object.freeze(Object.assign({
        version: 1,
        start: function () { hold('api', true); },
        stop: function () { hold('api', false); },
        hold: hold,
        isRunning: function () { return enabled; },
        auditNow: function (el) { return auditWidget(widgetRoot(el)); },
        findings: findingsFor,
        summary: summary,
        fixTask: fixTask,
        reveal: reveal,
        onChange: function (fn) { listeners.push(fn); return function () { listeners = listeners.filter(function (f) { return f !== fn; }); }; },
    }, pure));
    root.TbAudit = api;

    if (root.TbInspect && root.TbInspect.version === 1 && typeof root.TbInspect.registerBadge === 'function') hookInspect(root.TbInspect);

    // WU-3: a preview reload keeps the editor's «سلامت ویجت» tab running.
    function bootHost() {
        var h = hostOf();
        if (h && typeof h.auditWanted === 'function' && h.auditWanted() && doc.querySelector(WIDGET)) hold('host', true);
    }
    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', bootHost, { once: true });
    else bootHost();
}(typeof window !== 'undefined' ? window : globalThis));
