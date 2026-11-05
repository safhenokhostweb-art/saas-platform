/*
 * «سلامت ویجت» (WU-3): the editor sidebar tab over the Design HUD.
 *
 * Shared by both Canvas editors through theme-runtime::canvas.health. The
 * panel holds the preview's checks (TbAudit) while its tab is open, without
 * the Inspect overlay, and lists every widget of the page red → amber →
 * green with its findings. A row selects its block; «کپی پرامپت اصلاحی»
 * copies the same fix prompt as the Inspect action (TbAudit.fixTask).
 *
 * It talks only to the editor's TbInspect.host() controller, so the hosts
 * add one tab and one `select(blockId)` option to attachHost() — nothing
 * else. rows(), badge() and headline() are pure and unit-tested
 * (tests/js/canvas-health.test.mjs).
 */
(function (root) {
    'use strict';

    if (root.TbHealth && root.TbHealth.version === 1) return;

    var RANK = { error: 0, warn: 1, ok: 2 };

    function countOf(findings, severity) {
        return (findings || []).filter(function (f) { return f.severity === severity; }).length;
    }

    /** Summary widgets → panel rows: red first, then amber, then green; page order inside a tone. */
    function rows(summary, labelOf) {
        var widgets = (summary && summary.widgets) || [];
        return widgets.map(function (w, i) {
            var findings = w.findings || [];
            var label = '';
            try { label = (labelOf && labelOf(w.type)) || ''; } catch (_) { label = ''; }
            return {
                key: w.key, type: w.type, blockId: w.blockId || '', tone: w.tone || 'ok', order: i,
                label: label || w.type, findings: findings,
                blockers: countOf(findings, 'blocker'), warnings: countOf(findings, 'warning'), infos: countOf(findings, 'info'),
            };
        }).sort(function (a, b) { return (RANK[a.tone] - RANK[b.tone]) || (a.order - b.order); });
    }

    function badge(row) {
        var parts = [];
        if (row.blockers) parts.push(row.blockers + ' خطا');
        if (row.warnings) parts.push(row.warnings + ' هشدار');
        return parts.length ? parts.join(' · ') : 'سالم';
    }

    function headline(summary) {
        if (!summary) return '';
        var n = (summary.widgets || []).length;
        if (!n) return 'ویجتی برای بررسی نیست';
        var bad = (summary.widgets || []).filter(function (w) { return w.tone !== 'ok'; }).length;
        return bad ? bad + ' از ' + n + ' ویجت ایراد دارد' : 'هر ' + n + ' ویجت سالم است';
    }

    var pure = { rows: rows, badge: badge, headline: headline };

    /** Alpine component for the tab body: x-data="tbHealthPanel()" x-effect="setActive(<tab open>)". */
    function panel() {
        return {
            active: false,
            summary: null,
            list: [],
            expanded: {},
            copiedKey: null,
            note: '',
            showHealthy: false,
            _unsub: null,
            _timer: null,

            host() { return root.TbInspect && typeof root.TbInspect.host === 'function' ? root.TbInspect.host() : null; },

            setActive(on) {
                on = !!on;
                if (on === this.active) return;
                var h = this.host();
                if (!h) { this.note = 'پیش‌نمایش هنوز آماده نیست.'; return; }
                this.active = on;
                if (this._unsub) { this._unsub(); this._unsub = null; }
                h.setAudit(on);
                if (on) {
                    this.note = '';
                    this._unsub = h.subscribe((s) => this.apply(s));
                }
            },

            apply(s) {
                var h = this.host();
                this.summary = s;
                this.list = rows(s, (type) => (h ? h.describe(type).label : ''));
            },

            issues() { return this.list.filter((r) => r.tone !== 'ok'); },
            healthy() { return this.list.filter((r) => r.tone === 'ok'); },
            visible() { return this.showHealthy ? this.list : this.issues(); },
            headline() { return headline(this.summary); },
            badge(row) { return badge(row); },

            select(row) {
                var h = this.host();
                if (h) h.reveal(row.key, row.blockId);
            },

            toggle(row) { this.expanded = Object.assign({}, this.expanded, { [row.key]: !this.expanded[row.key] }); },

            flash(key, text) {
                this.copiedKey = key;
                this.note = text || '';
                root.clearTimeout(this._timer);
                this._timer = root.setTimeout(() => { this.copiedKey = null; this.note = ''; }, 1800);
            },

            copyText(text, key) {
                var I = root.TbInspect;
                if (!text) { this.flash(null, 'این ویجت در پیش‌نمایش پیدا نشد؛ پیش‌نمایش را تازه کنید.'); return; }
                Promise.resolve(I && typeof I.copy === 'function' ? I.copy(text) : false).then((ok) => {
                    this.flash(ok ? key : null, ok ? '' : 'کپی نشد؛ مرورگر اجازه دسترسی به کلیپ‌بورد نداد.');
                });
            },

            copy(row) {
                var h = this.host();
                this.copyText(h ? h.fixTask(row.key) : null, row.key);
            },

            copyAll() {
                var h = this.host();
                if (!h) return;
                var tasks = this.issues().map((r, i) => {
                    var t = h.fixTask(r.key);
                    return t ? '### ' + (i + 1) + '. ' + r.key + '\n\n' + t : '';
                }).filter(Boolean);
                this.copyText(tasks.join('\n\n---\n\n'), '__all__');
            },

            destroy() { this.setActive(false); },
        };
    }

    root.TbHealth = Object.freeze(Object.assign({ version: 1, panel: panel }, pure));
    root.tbHealthPanel = panel;
}(typeof window !== 'undefined' ? window : globalThis));
