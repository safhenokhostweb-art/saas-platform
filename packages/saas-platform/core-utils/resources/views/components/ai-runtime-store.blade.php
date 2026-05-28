{{-- AI Runtime Alpine.js Store — registers window.AiRuntime --}}
{{-- Include once in your layout head AFTER Alpine.js CDN --}}
<script>
(function () {
    'use strict';

    // ─── AI Runtime Store ─────────────────────────────────────────────────
    document.addEventListener('alpine:init', function () {

        Alpine.store('aiRuntime', {
            // State
            loading:     false,
            streaming:   false,
            error:       null,
            lastResult:  null,
            correlationId: null,

            // Config (overridable per-page)
            baseUrl:     '{{ rtrim(config("app.url"), "/") }}/api/ai',
            timeout:     30000,
            retryMax:    2,
            retryDelay:  1000,

            // ── Core executor ──────────────────────────────────────────────
            async execute(endpoint, payload, opts = {}) {
                this.loading    = true;
                this.error      = null;
                this.lastResult = null;

                const url        = `${this.baseUrl}/${endpoint}`;
                const correlId   = crypto.randomUUID();
                this.correlationId = correlId;

                let attempts = 0;
                while (attempts <= this.retryMax) {
                    try {
                        const controller = new AbortController();
                        const timer      = setTimeout(() => controller.abort(), this.timeout);

                        const resp = await fetch(url, {
                            method:  'POST',
                            headers: {
                                'Content-Type':    'application/json',
                                'Accept':          'application/json',
                                'X-CSRF-TOKEN':    document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                'X-Correlation-Id': correlId,
                            },
                            body:   JSON.stringify(payload),
                            signal: controller.signal,
                        });
                        clearTimeout(timer);

                        const json = await resp.json();

                        if (! resp.ok) {
                            throw Object.assign(new Error(json.message ?? 'AI request failed'), { status: resp.status, data: json });
                        }

                        this.lastResult = json.data ?? json;
                        this.loading    = false;
                        if (opts.onSuccess) opts.onSuccess(this.lastResult);
                        return this.lastResult;

                    } catch (err) {
                        attempts++;
                        if (err.name === 'AbortError') {
                            this.error = { code: 'TIMEOUT', message: 'درخواست AI زمان‌بر شد. لطفاً دوباره امتحان کنید.' };
                            break;
                        }
                        if (err.status === 429) {
                            this.error = { code: 'QUOTA', message: 'سهمیه AI تمام شده. لطفاً بعداً امتحان کنید.' };
                            break;
                        }
                        if (err.status === 422) {
                            this.error = { code: 'BLOCKED', message: err.data?.reason ?? 'درخواست مسدود شد.' };
                            break;
                        }
                        if (attempts > this.retryMax) {
                            this.error = { code: 'ERROR', message: err.message ?? 'خطای سرویس AI' };
                            break;
                        }
                        await new Promise(r => setTimeout(r, this.retryDelay * attempts));
                    }
                }

                this.loading = false;
                if (this.error && opts.onError) opts.onError(this.error);
                if (this.error) this._toast(this.error.message, 'error');
                return null;
            },

            // ── Named operations ───────────────────────────────────────────
            suggest(prompt, domain = 'general', opts = {}) {
                return this.execute('suggest', { prompt, domain }, opts);
            },
            chat(messages, domain = 'general', opts = {}) {
                return this.execute('chat', { messages, domain }, opts);
            },
            complete(prompt, opts = {}) {
                return this.execute('complete', { prompt }, opts);
            },
            analyze(content, analysisType = 'summary', opts = {}) {
                return this.execute('analyze', { content, analysis_type: analysisType }, opts);
            },
            generate(type, context, tone = 'professional', opts = {}) {
                return this.execute('generate', { type, context, tone }, opts);
            },
            translate(text, targetLang, opts = {}) {
                return this.execute('translate', { text, target_lang: targetLang }, opts);
            },
            summarize(text, length = 'standard', opts = {}) {
                return this.execute('summarize', { text, length }, opts);
            },
            formAssistant(action, payload = {}, opts = {}) {
                return this.execute('form-assistant', { action, ...payload }, opts);
            },
            ticketAssistant(action, payload = {}, opts = {}) {
                return this.execute('ticket-assistant', { action, ...payload }, opts);
            },
            automationAssistant(context, module = 'general', opts = {}) {
                return this.execute('automation-assistant', { context, module }, opts);
            },

            // ── Polling realtime fallback ──────────────────────────────────
            _pollInterval: null,
            _pollSince: null,

            startPolling(tenantId, onEvent, intervalMs = 5000) {
                this.stopPolling();
                this._pollSince = new Date().toISOString();
                this._pollInterval = setInterval(async () => {
                    try {
                        const resp = await fetch(
                            `/api/realtime/poll?tenant_id=${tenantId}&since=${encodeURIComponent(this._pollSince)}`,
                            { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' } }
                        );
                        if (resp.ok) {
                            const data = await resp.json();
                            (data.events ?? []).forEach(ev => onEvent(ev));
                            if ((data.events ?? []).length > 0) {
                                this._pollSince = data.events[data.events.length - 1].timestamp ?? new Date().toISOString();
                            }
                        }
                    } catch (_) {}
                }, intervalMs);
            },

            stopPolling() {
                if (this._pollInterval) {
                    clearInterval(this._pollInterval);
                    this._pollInterval = null;
                }
            },

            // ── Notification center ───────────────────────────────────────
            notifications: [],
            unreadCount: 0,

            async loadNotifications(tenantId, userId) {
                try {
                    const resp = await fetch(`/api/notifications?tenant_id=${tenantId}&user_id=${userId}`, {
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' }
                    });
                    if (resp.ok) {
                        const data     = await resp.json();
                        this.notifications = data.notifications ?? [];
                        this.unreadCount   = this.notifications.filter(n => !n.read).length;
                    }
                } catch (_) {}
            },

            async markRead(notificationId) {
                this.notifications = this.notifications.map(n =>
                    n.id === notificationId ? { ...n, read: true } : n
                );
                this.unreadCount = this.notifications.filter(n => !n.read).length;
                // Best-effort API call
                fetch(`/api/notifications/${notificationId}/read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' }
                }).catch(() => {});
            },

            // ── Toast helper ──────────────────────────────────────────────
            _toast(message, type = 'info') {
                if (window.Alpine && Alpine.store('toast')) {
                    Alpine.store('toast').show(message, type);
                } else {
                    console.warn('[AI Runtime]', type, message);
                }
            },
        });

    });

    // ─── UX Telemetry Tracker (rage-click + interaction) ─────────────────
    (function () {
        let clickTimes   = [];
        let lastElement  = null;

        document.addEventListener('click', function (e) {
            const el      = e.target.closest('[data-ux-track]') ?? e.target;
            const now     = Date.now();
            const sameEl  = el === lastElement;

            if (sameEl) {
                clickTimes.push(now);
                // Keep only clicks within 2 seconds
                clickTimes = clickTimes.filter(t => now - t < 2000);

                if (clickTimes.length >= 3) {
                    // Rage-click detected
                    const track = el.getAttribute?.('data-ux-track') ?? el.tagName;
                    fetch('/api/ux/rage-click', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        body: JSON.stringify({ element: track, clicks: clickTimes.length, page: location.pathname }),
                    }).catch(() => {});
                    clickTimes = [];
                }
            } else {
                clickTimes  = [now];
                lastElement = el;
            }
        });
    })();

})();
</script>
