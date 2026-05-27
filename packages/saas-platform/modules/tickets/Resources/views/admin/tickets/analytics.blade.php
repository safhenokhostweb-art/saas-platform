@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'داشبورد عملیاتی — پشتیبانی')

@push('head')
<style>
.dash-card{background:rgba(255,255,255,.88);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.3);border-radius:1.5rem;box-shadow:0 8px 32px rgba(15,23,42,.06);padding:1.5rem}
.kpi-val{font-size:2.25rem;font-weight:900;letter-spacing:-.04em;line-height:1}
.kpi-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;opacity:.55;margin-top:.35rem}
.ring-wrap{position:relative;width:64px;height:64px}
.ring-wrap svg{transform:rotate(-90deg)}
.ring-val{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;color:#374151}
.hbar{height:8px;background:#f1f5f9;border-radius:9999px;overflow:hidden}
.hbar-fill{height:100%;border-radius:9999px;transition:width .8s cubic-bezier(.34,1.56,.64,1)}
.section-title{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:1rem}
.period-btn{padding:.4rem 1rem;border-radius:.75rem;font-size:.75rem;font-weight:600;transition:all .2s;cursor:pointer;border:1px solid transparent}
.period-btn.active{background:#eef2ff;color:#4f46e5;border-color:#c7d2fe}
.period-btn:not(.active){color:#64748b;background:transparent;border-color:#e2e8f0}
.period-btn:not(.active):hover{background:#f8fafc}
</style>
@endpush

@section('content')
<div class="admin-page" x-data="operationalDashboard()">

    {{-- ── Header ────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">داشبورد عملیاتی پشتیبانی</h1>
            <p class="text-sm text-gray-500 mt-0.5">آنالیتیکس زنده و شاخص‌های کلیدی عملکرد</p>
        </div>
        <div class="flex items-center gap-2">
            @foreach(['7d' => '۷ روز', '30d' => '۳۰ روز', '90d' => '۹۰ روز'] as $p => $label)
            <button @click="period = '{{ $p }}'; refresh()"
                    :class="period === '{{ $p }}' ? 'active' : ''"
                    class="period-btn">{{ $label }}</button>
            @endforeach
            <button @click="refresh()" class="flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-600 hover:border-indigo-300 hover:text-indigo-600 transition">
                <svg class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
                بروزرسانی
            </button>
        </div>
    </div>

    {{-- ── Realtime KPIs ──────────────────────────────────────────────────── --}}
    <div class="section-title">شاخص‌های زنده</div>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">

        <div class="dash-card col-span-1">
            <div class="flex items-start justify-between">
                <div>
                    <div class="kpi-val text-indigo-600" x-text="realtime.open_tickets ?? 0"></div>
                    <div class="kpi-label">تیکت باز</div>
                </div>
                <span class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 text-sm">🎫</span>
            </div>
        </div>

        <div class="dash-card col-span-1">
            <div class="flex items-start justify-between">
                <div>
                    <div class="kpi-val text-emerald-600" x-text="(realtime.response_time_avg ?? 0).toFixed(1) + 'h'"></div>
                    <div class="kpi-label">میانگین پاسخ</div>
                </div>
                <span class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 text-sm">⚡</span>
            </div>
        </div>

        <div class="dash-card col-span-1">
            <div class="flex items-start justify-between">
                <div>
                    <div class="kpi-val text-violet-600" x-text="realtime.active_users ?? 0"></div>
                    <div class="kpi-label">آنلاین</div>
                </div>
                <span class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center text-violet-600 text-sm">👥</span>
            </div>
        </div>

        <div class="dash-card col-span-1">
            <div class="flex items-start justify-between">
                <div>
                    <div class="kpi-val text-amber-600" x-text="support.sla_compliance_pct ?? 0"></div>
                    <div class="kpi-label">SLA %</div>
                </div>
                <div class="ring-wrap">
                    <svg viewBox="0 0 64 64" width="64" height="64">
                        <circle cx="32" cy="32" r="28" fill="none" stroke="#f3f4f6" stroke-width="6"/>
                        <circle cx="32" cy="32" r="28" fill="none" stroke="#f59e0b" stroke-width="6"
                                :stroke-dasharray="`${(support.sla_compliance_pct ?? 0) * 1.759} 175.9`"/>
                    </svg>
                    <div class="ring-val" x-text="(support.sla_compliance_pct ?? 0) + '%'"></div>
                </div>
            </div>
        </div>

        <div class="dash-card col-span-1">
            <div class="flex items-start justify-between">
                <div>
                    <div class="kpi-val text-blue-600" x-text="support.tickets_created ?? 0"></div>
                    <div class="kpi-label">ایجاد شده</div>
                </div>
                <span class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 text-sm">📥</span>
            </div>
        </div>

        <div class="dash-card col-span-1">
            <div class="flex items-start justify-between">
                <div>
                    <div class="kpi-val text-teal-600" x-text="support.tickets_resolved ?? 0"></div>
                    <div class="kpi-label">حل شده</div>
                </div>
                <span class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center text-teal-600 text-sm">✅</span>
            </div>
        </div>
    </div>

    {{-- ── Support Performance ─────────────────────────────────────────────── --}}
    <div class="grid md:grid-cols-3 gap-4 mb-6">

        {{-- Resolution Time --}}
        <div class="dash-card">
            <p class="section-title">زمان حل</p>
            <div class="kpi-val text-gray-800" x-text="(support.avg_resolution_h ?? 0) + 'h'"></div>
            <div class="kpi-label">میانگین زمان حل تیکت</div>
            <div class="mt-4 space-y-2">
                @foreach(['critical' => ['label' => 'بحرانی', 'color' => 'bg-red-500', 'target' => '4h'], 'high' => ['label' => 'فوری', 'color' => 'bg-amber-500', 'target' => '8h'], 'normal' => ['label' => 'معمولی', 'color' => 'bg-blue-500', 'target' => '24h']] as $p => $meta)
                <div>
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ $meta['label'] }}</span>
                        <span>هدف: {{ $meta['target'] }}</span>
                    </div>
                    <div class="hbar"><div class="hbar-fill {{ $meta['color'] }}" style="width: 75%"></div></div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- SLA Panel --}}
        <div class="dash-card">
            <p class="section-title">رعایت SLA</p>
            <div class="flex justify-center py-3">
                <div class="ring-wrap" style="width:100px;height:100px">
                    <svg viewBox="0 0 100 100" width="100" height="100">
                        <circle cx="50" cy="50" r="40" fill="none" stroke="#f3f4f6" stroke-width="8"/>
                        <circle cx="50" cy="50" r="40" fill="none"
                                :stroke="(support.sla_compliance_pct ?? 0) >= 90 ? '#10b981' : (support.sla_compliance_pct ?? 0) >= 75 ? '#f59e0b' : '#ef4444'"
                                stroke-width="8"
                                :stroke-dasharray="`${(support.sla_compliance_pct ?? 0) * 2.513} 251.3`"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="ring-val text-base font-black" x-text="(support.sla_compliance_pct ?? 0) + '%'"></div>
                </div>
            </div>
            <p class="text-center text-sm text-gray-500 mt-2"
               :class="(support.sla_compliance_pct ?? 0) >= 90 ? 'text-emerald-600' : (support.sla_compliance_pct ?? 0) >= 75 ? 'text-amber-600' : 'text-red-600'">
               <span x-text="(support.sla_compliance_pct ?? 0) >= 90 ? 'عملکرد عالی' : (support.sla_compliance_pct ?? 0) >= 75 ? 'نیاز به بهبود' : 'هشدار SLA'"></span>
            </p>
        </div>

        {{-- Security Posture --}}
        <div class="dash-card">
            <p class="section-title">وضعیت امنیتی</p>
            <div class="kpi-val text-emerald-600" x-text="security.score ?? 0"></div>
            <div class="kpi-label">امتیاز امنیت</div>
            <div class="mt-4 space-y-2.5">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">ورودی‌های ناموفق (۲۴h)</span>
                    <span class="font-semibold text-gray-800" x-text="security.failed_logins_24h ?? 0"></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">IPهای مشکوک</span>
                    <span class="font-semibold" :class="(security.suspicious_ips ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'" x-text="security.suspicious_ips ?? 0"></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">رعایت پچ‌ها</span>
                    <span class="font-semibold text-emerald-600" x-text="(security.patch_compliance ?? 100) + '%'"></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">سطح تهدید</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                          :class="security.threat_level === 'low' ? 'bg-emerald-100 text-emerald-700' : security.threat_level === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'"
                          x-text="{'low':'پایین','medium':'متوسط','high':'بالا','critical':'بحرانی'}[security.threat_level ?? 'low']"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Queue & Runtime Health ───────────────────────────────────────────── --}}
    <div class="grid md:grid-cols-2 gap-4 mb-6">
        <div class="dash-card">
            <p class="section-title">سلامت صف (Queue)</p>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">در انتظار پردازش</span>
                    <span class="font-bold text-gray-800" x-text="queue.total_pending ?? 0"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">جاب‌های ناموفق (۲۴h)</span>
                    <span class="font-bold" :class="(queue.failed_jobs_24h ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'" x-text="queue.failed_jobs_24h ?? 0"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">worker فعال</span>
                    <span class="font-bold text-emerald-600" x-text="queue.workers_active ?? 1"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">throughput/min</span>
                    <span class="font-bold text-blue-600" x-text="queue.throughput_per_min ?? 0"></span>
                </div>
            </div>
        </div>

        <div class="dash-card">
            <p class="section-title">سلامت tenant</p>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl"
                     :class="(health.score ?? 0) >= 90 ? 'bg-emerald-100' : (health.score ?? 0) >= 70 ? 'bg-amber-100' : 'bg-red-100'">
                    <span x-text="(health.score ?? 0) >= 90 ? '✅' : (health.score ?? 0) >= 70 ? '⚠️' : '🔴'"></span>
                </div>
                <div>
                    <div class="font-bold text-xl text-gray-800" x-text="health.score ?? 0"></div>
                    <div class="text-xs text-gray-500">امتیاز سلامت</div>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">uptime (۲۴h)</span>
                    <span class="font-semibold text-emerald-600" x-text="(health.uptime_24h ?? 100) + '%'"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">نرخ خطا</span>
                    <span class="font-semibold" x-text="(health.error_rate_24h ?? 0) + '%'"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">cache hit rate</span>
                    <span class="font-semibold text-blue-600" x-text="(health.cache_hit_rate ?? 0) + '%'"></span>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function operationalDashboard() {
    return {
        period: '7d',
        loading: false,
        realtime: @json($realtime ?? []),
        support:  @json($support  ?? []),
        forms:    @json($forms    ?? []),
        health:   @json($health   ?? []),
        queue:    @json($queue    ?? []),
        security: @json($security ?? []),

        async refresh() {
            this.loading = true;
            try {
                const r = await fetch(`/admin/tickets/analytics/data?period=${this.period}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (r.ok) {
                    const d = await r.json();
                    this.realtime = d.realtime ?? this.realtime;
                    this.support  = d.support  ?? this.support;
                    this.forms    = d.forms    ?? this.forms;
                    this.health   = d.health   ?? this.health;
                    this.queue    = d.queue    ?? this.queue;
                    this.security = d.security ?? this.security;
                }
            } catch {}
            this.loading = false;
        },

        init() {
            setInterval(() => this.refresh(), 60000); // Auto-refresh every minute
        }
    };
}
</script>
@endpush
