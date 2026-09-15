@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'داشبورد پشتیبانی')

{{-- Phase 10.1 — Module styles migrated to resources/css/app.css (canonical bundle) --}}

@push('head')
<style>
/* ── Section F: Multi-color ticket attention system ────────────────────── */

/* Color A — پیام از SaaS Admin (کارمند پشتیبان) → برای site_admin/مشتری */
@keyframes blinkStaff{0%,100%{background-color:transparent}50%{background-color:rgba(99,102,241,.09)}}
.ticket-unread-staff{animation:blinkStaff 2.2s ease-in-out infinite;border-right:3px solid #6366f1!important}
.ticket-unread-staff:hover{animation:none}

/* Color B — پیام از Customer Admin (site_admin) → برای saas admin */
@keyframes blinkSiteAdmin{0%,100%{background-color:transparent}50%{background-color:rgba(16,185,129,.09)}}
.ticket-unread-site-admin{animation:blinkSiteAdmin 2.2s ease-in-out infinite;border-right:3px solid #10b981!important}
.ticket-unread-site-admin:hover{animation:none}

/* Color C — پیام از End User (customer) → برای همه */
@keyframes blinkCustomer{0%,100%{background-color:transparent}50%{background-color:rgba(245,158,11,.09)}}
.ticket-unread-customer{animation:blinkCustomer 2.0s ease-in-out infinite;border-right:3px solid #f59e0b!important}
.ticket-unread-customer:hover{animation:none}

@keyframes badgeFade{0%,100%{opacity:1}50%{opacity:.45}}
.awaits-reply-badge{animation:badgeFade 1.6s ease-in-out infinite}

/* Last message preview row */
.ticket-last-msg{font-size:.67rem;color:#6b7280;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}

/* ── Feature Request row identity ── */
.ticket-row-fr{border-right:3px solid #8b5cf6!important;background:linear-gradient(to left,rgba(139,92,246,.03),transparent)}
.ticket-row-fr:hover{background:rgba(139,92,246,.07)!important}
.fr-type-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.18rem .6rem;border-radius:9999px;font-size:.62rem;font-weight:700;background:#f3e8ff;color:#7c3aed;border:1px solid #ddd6fe;white-space:nowrap;vertical-align:middle}

/* ── Type quick-tabs ── */
.type-tab{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1.1rem;border-radius:.75rem;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;transition:all .18s;white-space:nowrap;text-decoration:none}
.type-tab.tab-all{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
.type-tab.tab-all.tab-active,.type-tab.tab-all:hover{background:#eef2ff;color:#4338ca;border-color:#c7d2fe}
.type-tab.tab-support{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
.type-tab.tab-support.tab-active,.type-tab.tab-support:hover{background:#e0f2fe;color:#0369a1;border-color:#bae6fd}
.type-tab.tab-fr{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
.type-tab.tab-fr.tab-active,.type-tab.tab-fr:hover{background:#f3e8ff;color:#6d28d9;border-color:#ddd6fe}
</style>
@endpush
@section('content')
{{-- Phase 10.0: Inject canonical design tokens once per page --}}
<x-core-ui::design-tokens />

<div class="admin-page" style="gap:0" role="main" aria-label="داشبورد پشتیبانی" x-data="{
    search: '{{ request('q', '') }}',
    status: '{{ request('status', '') }}',
    priority: '{{ request('priority', '') }}',
    ticketType: '{{ request('type', '') }}',
    bulkSelected: [],
    get allIds() { return Array.from(document.querySelectorAll('.ticket-check')).map(el=>el.value); },
    toggleAll(checked) { this.bulkSelected = checked ? this.allIds : []; },
    get bulkCount() { return this.bulkSelected.length; }
}">

    {{-- ─── Flash Messages ────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl px-5 py-3.5 mb-5"
         x-data="{ show: true }" x-show="show" x-transition.duration.300ms>
        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm text-green-800 font-medium flex-1">{{ session('success') }}</span>
        <button @click="show=false" class="text-green-400 hover:text-green-600 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    {{-- ─── Escalation Alert ──────────────────────────────────────────── --}}
    @php $openUrgent = $tickets->where('status','open')->where('priority','high')->count() ?? 0; @endphp
    @if($openUrgent > 0)
    <div class="escalation-alert flex items-center gap-4 px-5 py-3.5 mb-5">
        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-100 shrink-0">
            <svg class="w-5 h-5 text-red-600 animate-bounce" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        </div>
        <div class="flex-1">
            <p class="text-sm font-bold text-red-700">{{ $openUrgent }} تیکت اورژانسی باز — نیاز به توجه فوری</p>
            <p class="text-xs text-red-600 mt-0.5">این تیکت‌ها اولویت بالا دارند و هنوز پاسخ نگرفته‌اند.</p>
        </div>
        <a href="?status=open&priority=high" class="text-xs font-semibold text-red-600 hover:text-red-700 underline underline-offset-2 shrink-0">مشاهده همه</a>
    </div>
    @endif

    {{-- ─── Stat Cards ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-{{ $slaEnabled ? '6' : '5' }} gap-4 mb-6">

        {{-- Total --}}
        <div class="tkt-stat col-span-2 sm:col-span-1">
            <div class="tkt-stat__accent bg-gradient-to-b from-indigo-500 to-purple-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-gradient-to-br from-indigo-50 to-purple-50 text-indigo-600">📫</div>
                <div>
                    <div class="tkt-stat__val text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($stats['total'] ?? 0) }}</div>
                    <div class="tkt-stat__label text-gray-500">کل تیکت‌ها</div>
                </div>
            </div>
        </div>

        {{-- Open --}}
        <div class="tkt-stat" x-data="{}" @click="document.querySelector('[x-model=\'status\']').dispatchEvent(new Event('change')); search=''; status='open'">
            <div class="tkt-stat__accent bg-gradient-to-b from-yellow-400 to-orange-400"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-yellow-50 text-yellow-600">
                    <span class="relative flex h-6 w-6 items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-30"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-yellow-500"></span>
                    </span>
                </div>
                <div>
                    <div class="tkt-stat__val text-yellow-700">{{ \App\Helpers\NumberHelper::formatFa($stats['open'] ?? 0) }}</div>
                    <div class="tkt-stat__label text-yellow-600">باز</div>
                    @if(($stats['open'] ?? 0) > 0)
                    <span class="tkt-stat__trend bg-yellow-100 text-yellow-700">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        نیاز به پاسخ
                    </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- In Progress --}}
        <div class="tkt-stat cursor-pointer" @click="status='in_progress'">
            <div class="tkt-stat__accent bg-gradient-to-b from-blue-400 to-indigo-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-blue-50 text-blue-600">
                    <svg class="w-6 h-6 animate-spin" style="animation-duration:3s" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-blue-700">{{ \App\Helpers\NumberHelper::formatFa($stats['in_progress'] ?? 0) }}</div>
                    <div class="tkt-stat__label text-blue-600">در حال بررسی</div>
                </div>
            </div>
        </div>

        {{-- Closed --}}
        <div class="tkt-stat cursor-pointer" @click="status='closed'">
            <div class="tkt-stat__accent bg-gradient-to-b from-green-400 to-teal-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-green-50 text-green-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-green-700">{{ \App\Helpers\NumberHelper::formatFa($stats['closed'] ?? 0) }}</div>
                    <div class="tkt-stat__label text-green-600">بسته شده</div>
                </div>
            </div>
        </div>

        {{-- Response Time (synthetic) --}}
        <div class="tkt-stat">
            <div class="tkt-stat__accent bg-gradient-to-b from-teal-400 to-cyan-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-teal-50 text-teal-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-teal-700 text-2xl">{{ \App\Helpers\NumberHelper::formatFa($stats['avg_response_hours'] ?? 4) }}h</div>
                    <div class="tkt-stat__label text-teal-600">میانگین پاسخ</div>
                    <span class="tkt-stat__trend bg-teal-50 text-teal-700">SLA سالم</span>
                </div>
            </div>
        </div>

        {{-- SLA Breaches --}}
        @if($slaEnabled)
        @php $breachCount = $stats['sla_breaches'] ?? 0; @endphp
        <div class="tkt-stat {{ $breachCount > 0 ? 'ring-2 ring-red-300' : '' }}">
            <div class="tkt-stat__accent {{ $breachCount > 0 ? 'bg-gradient-to-b from-red-400 to-red-600' : 'bg-gradient-to-b from-gray-300 to-gray-400' }}"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon {{ $breachCount > 0 ? 'bg-red-50 text-red-600' : 'bg-gray-50 text-gray-500' }}">
                    <svg class="w-6 h-6 {{ $breachCount > 0 ? 'animate-pulse' : '' }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val {{ $breachCount > 0 ? 'text-red-700' : 'text-gray-600' }}">{{ \App\Helpers\NumberHelper::formatFa($breachCount) }}</div>
                    <div class="tkt-stat__label {{ $breachCount > 0 ? 'text-red-600' : 'text-gray-500' }}">نقض SLA</div>
                </div>
            </div>
        </div>
        @endif

    </div>

    {{-- ─── Queue Health + Priority Heatmap Row ───────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

        {{-- Queue Health --}}
        <div class="glass-card-strong p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                سلامت صف
            </h3>
            @php
            $total = max(1, $stats['total'] ?? 1);
            $openPct = round(($stats['open'] ?? 0) / $total * 100);
            $inPct   = round(($stats['in_progress'] ?? 0) / $total * 100);
            $closPct = round(($stats['closed'] ?? 0) / $total * 100);
            $queueScore = $openPct < 30 ? 'سالم' : ($openPct < 60 ? 'متوسط' : 'بحرانی');
            $queueColor = $openPct < 30 ? 'text-green-600' : ($openPct < 60 ? 'text-amber-600' : 'text-red-600');
            @endphp
            <div class="space-y-3 text-sm">
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-gray-500 text-xs">باز</span>
                        <span class="font-bold text-yellow-600 text-xs">{{ $openPct }}%</span>
                    </div>
                    <div class="queue-health-bar">
                        <div class="queue-health-fill bg-gradient-to-r from-yellow-400 to-orange-400" style="width:{{ $openPct }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-gray-500 text-xs">در حال بررسی</span>
                        <span class="font-bold text-blue-600 text-xs">{{ $inPct }}%</span>
                    </div>
                    <div class="queue-health-bar">
                        <div class="queue-health-fill bg-gradient-to-r from-blue-400 to-indigo-500" style="width:{{ $inPct }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-gray-500 text-xs">بسته</span>
                        <span class="font-bold text-green-600 text-xs">{{ $closPct }}%</span>
                    </div>
                    <div class="queue-health-bar">
                        <div class="queue-health-fill bg-gradient-to-r from-green-400 to-teal-500" style="width:{{ $closPct }}%"></div>
                    </div>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between">
                <span class="text-xs text-gray-500">وضعیت کلی صف</span>
                <span class="text-xs font-bold {{ $queueColor }}">{{ $queueScore }}</span>
            </div>
        </div>

        {{-- Priority Heatmap --}}
        <div class="glass-card-strong p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                نقشه حرارتی اولویت
            </h3>
            @php
            $highOpen   = $tickets->where('priority','high')->where('status','open')->count();
            $highInProg = $tickets->where('priority','high')->where('status','in_progress')->count();
            $normOpen   = $tickets->where('priority','normal')->where('status','open')->count();
            $normInProg = $tickets->where('priority','normal')->where('status','in_progress')->count();
            $lowOpen    = $tickets->where('priority','low')->where('status','open')->count();
            $lowInProg  = $tickets->where('priority','low')->where('status','in_progress')->count();
            $heatMax    = max(1, $highOpen, $highInProg, $normOpen, $normInProg, $lowOpen, $lowInProg);
            $heatColor = fn($v, $max, $base) => match(true) {
                $v / $max === 0.0   => 'bg-gray-100 text-gray-400',
                $v / $max < .33     => "bg-{$base}-100 text-{$base}-700",
                $v / $max < .66     => "bg-{$base}-200 text-{$base}-800",
                default             => "bg-{$base}-400 text-white",
            };
            @endphp
            <div class="overflow-x-auto">
            <table class="w-full text-xs text-center">
                <thead>
                    <tr class="text-gray-400 font-semibold">
                        <th class="py-1 text-right pr-1">اولویت</th>
                        <th class="py-1 px-2">باز</th>
                        <th class="py-1 px-2">در جریان</th>
                    </tr>
                </thead>
                <tbody class="space-y-2">
                    <tr>
                        <td class="text-right text-red-600 font-bold py-1.5 pr-1">بالا</td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ $heatColor($highOpen, $heatMax, 'red') }}">{{ $highOpen }}</div></td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ $heatColor($highInProg, $heatMax, 'red') }}">{{ $highInProg }}</div></td>
                    </tr>
                    <tr>
                        <td class="text-right text-blue-600 font-bold py-1.5 pr-1">عادی</td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ $heatColor($normOpen, $heatMax, 'blue') }}">{{ $normOpen }}</div></td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ $heatColor($normInProg, $heatMax, 'blue') }}">{{ $normInProg }}</div></td>
                    </tr>
                    <tr>
                        <td class="text-right text-gray-500 font-bold py-1.5 pr-1">کم</td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ $heatColor($lowOpen, $heatMax, 'gray') }}">{{ $lowOpen }}</div></td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ $heatColor($lowInProg, $heatMax, 'gray') }}">{{ $lowInProg }}</div></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        {{-- Agent Workload --}}
        <div class="glass-card-strong p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                بار کاری کارشناسان
            </h3>
            @php
            $agentWorkloads = $tickets->whereNotNull('assigned_to')->groupBy('assigned_to');
            $maxAgentTickets = max(1, $agentWorkloads->map->count()->max() ?? 1);
            @endphp
            @if($agentWorkloads->isEmpty())
            <div class="text-center py-4 text-gray-400">
                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                <p class="text-xs">تیکتی تخصیص نیافته</p>
            </div>
            @else
            <div class="space-y-3">
                @foreach($agentWorkloads->take(4) as $agentId => $agentTickets)
                @php
                $agentName = $agentTickets->first()?->assignee?->name ?? ('کارشناس ' . $agentId);
                $agentCount = $agentTickets->count();
                $agentPct = round($agentCount / $maxAgentTickets * 100);
                $barColor = $agentPct > 75 ? 'bg-red-500' : ($agentPct > 50 ? 'bg-amber-400' : 'bg-indigo-500');
                @endphp
                <div class="agent-card">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                {{ mb_substr($agentName, 0, 1) }}
                            </div>
                            <span class="text-xs font-semibold text-gray-700 truncate max-w-[100px]">{{ $agentName }}</span>
                        </div>
                        <span class="text-xs font-bold {{ $agentPct > 75 ? 'text-red-600' : 'text-gray-600' }}">{{ $agentCount }}</span>
                    </div>
                    <div class="agent-workload mt-2">
                        <div class="{{ $barColor }} agent-workload-bar" style="width:{{ $agentPct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>

    {{-- ─── Bulk Toolbar (core-ui::bulk-toolbar) ──────────────────────── --}}
    <x-core-ui::bulk-toolbar
        form-action="{{ route('tickets.admin.bulk') }}"
        form-method="POST"
        count-var="bulkCount"
        :actions="[
            ['label' => 'بستن گروهی',   'value' => 'close',  'color' => 'gray'],
            ['label' => 'بازگشایی',      'value' => 'reopen', 'color' => 'gray'],
            ['label' => 'حذف انتخاب‌شده','value' => 'delete', 'color' => 'red'],
        ]"
    />

    {{-- ─── Actions Bar ────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('tickets.admin.create') }}" class="btn-gradient">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                تیکت جدید
            </a>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($slaEnabled)
            <a href="{{ route('tickets.admin.sla.index') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition-all">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                داشبورد SLA
            </a>
            @endif
            <a href="{{ route('tickets.admin.settings') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 transition-all shadow-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                تنظیمات
            </a>
        </div>
    </div>

    {{-- ─── Filter + Table ────────────────────────────────────────────── --}}

    {{-- Type quick-tabs — همه / پشتیبانی / درخواست ویژگی ─────────────── --}}
    @php
        $currentType = request('type', '');
        $frCount     = $frOpenCount ?? 0;
    @endphp
    <div class="flex items-center gap-2 mb-4 flex-wrap">
        <a href="{{ request()->fullUrlWithQuery(['type' => '']) }}"
           class="type-tab tab-all {{ $currentType === '' ? 'tab-active' : '' }}">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
            همه تیکت‌ها
        </a>
        <a href="{{ request()->fullUrlWithQuery(['type' => 'support']) }}"
           class="type-tab tab-support {{ $currentType === 'support' ? 'tab-active' : '' }}">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            پشتیبانی
        </a>
        <a href="{{ request()->fullUrlWithQuery(['type' => 'feature_request']) }}"
           class="type-tab tab-fr {{ $currentType === 'feature_request' ? 'tab-active' : '' }}">
            💡
            درخواست ویژگی
            @if($frCount > 0)
            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[9px] font-bold
                {{ $currentType === 'feature_request' ? 'bg-purple-600 text-white' : 'bg-purple-100 text-purple-700' }}">
                {{ $frCount }}
            </span>
            @endif
        </a>

        {{-- جستجوی اختصاصی درخواست ویژگی --}}
        @if($currentType === 'feature_request')
        <div class="flex items-center gap-1.5 mr-2 text-xs text-purple-600 font-semibold bg-purple-50 border border-purple-200 px-3 py-2 rounded-xl">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            نمایش درخواست‌های ویژگی
            <a href="{{ route('tickets.admin.fr.index') }}" class="mr-1 text-purple-700 hover:text-purple-900 underline underline-offset-2">
                داشبورد کامل
            </a>
        </div>
        @endif
    </div>

    {{-- Filter Bar --}}
    <div class="glass-filter-bar mb-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <div class="relative flex-1 w-full sm:w-auto">
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" placeholder="جستجوی موضوع یا شماره تیکت..."
                       class="w-full pr-9 pl-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
            </div>
            <select x-model="status" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه وضعیت‌ها</option>
                <option value="open">باز</option>
                <option value="in_progress">در حال بررسی</option>
                <option value="closed">بسته</option>
            </select>
            <select x-model="priority" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه اولویت‌ها</option>
                <option value="high">🔴 بالا</option>
                <option value="normal">🔵 عادی</option>
                <option value="low">⚪ کم</option>
            </select>
            {{-- فیلتر نوع تیکت (کلاینت‌ساید) --}}
            <select x-model="ticketType" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all w-full sm:w-auto">
                <option value="">💬 همه انواع</option>
                <option value="support">🔧 پشتیبانی</option>
                <option value="feature_request">💡 درخواست ویژگی</option>
            </select>
            <button @click="search=''; status=''; priority=''; ticketType=''"
                    class="flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm text-gray-500 hover:bg-gray-50 transition-all w-full sm:w-auto justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                پاک کردن
            </button>
        </div>
    </div>

    {{-- Tickets Table --}}
    <div class="glass-card-strong overflow-hidden">
    <div class="overflow-x-auto scrollbar-thin">
    <table class="w-full text-right border-collapse" style="min-width:700px">
    <thead>
        <tr class="bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
            <th class="p-3 pr-5 w-8">
                <input type="checkbox" class="rounded text-indigo-600"
                       @change="toggleAll($event.target.checked)">
            </th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">شماره</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">سایت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">موضوع</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">وضعیت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">اولویت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">مسئول</th>
            @if($slaEnabled)<th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">SLA</th>@endif
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">تاریخ</th>
            <th class="p-3 pl-5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">عملیات</th>
        </tr>
    </thead>
    <tbody>
    @forelse($tickets as $ticket)
    @php
        $p     = $ticket->priority ?? 'normal';
        $pBg   = $p === 'high' ? 'bg-red-50 text-red-700 border-red-200' : ($p === 'low' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-600 border-blue-200');
        $pDot  = $p === 'high' ? 'bg-red-500' : ($p === 'low' ? 'bg-gray-400' : 'bg-blue-500');
        $pLbl  = $p === 'high' ? 'بالا' : ($p === 'low' ? 'کم' : 'عادی');
        $lastReply  = ($lastReplies ?? collect())->get($ticket->id);
        $lastAuthor = $lastReply?->author_type;
        $lastMsgPreview = $lastReply ? Str::limit($lastReply->preview ?? '', 55) : null;
        $lastMsgTime    = $lastReply?->created_at;

        // Section F: three-color attention classes
        $isOpen   = $ticket->status !== 'closed';
        $isFR     = ($ticket->type ?? 'support') === 'feature_request';
        $attnClass      = '';
        $attnBadgeLabel = '';
        $attnBadgeColor = '';
        if ($isOpen && $lastAuthor === 'customer') {
            $attnClass      = 'ticket-unread-customer';
            $attnBadgeLabel = 'پیام مشتری';
            $attnBadgeColor = 'bg-amber-100 text-amber-700 border-amber-200';
        } elseif ($isOpen && $lastAuthor === 'site_admin') {
            $attnClass      = 'ticket-unread-site-admin';
            $attnBadgeLabel = 'پیام ادمین سایت';
            $attnBadgeColor = 'bg-emerald-100 text-emerald-700 border-emerald-200';
        } elseif ($isOpen && $lastAuthor === 'staff') {
            $attnClass      = 'ticket-unread-staff';
            $attnBadgeLabel = 'پیام پشتیبان';
            $attnBadgeColor = 'bg-indigo-100 text-indigo-700 border-indigo-200';
        }
        // FR rows always carry their own purple border identity class
        $rowBaseClass = $isFR ? 'ticket-row-fr' : '';
    @endphp
    <tr class="ticket-row border-b border-gray-50 {{ $rowBaseClass }} {{ $attnClass }}"
        data-subject="{{ $ticket->subject }}"
        data-ticket-no="{{ $ticket->ticket_number }}"
        data-status="{{ $ticket->status }}"
        data-priority="{{ $p }}"
        data-type="{{ $ticket->type ?? 'support' }}"
        x-show="
            (!search || $el.dataset.subject.toLowerCase().includes(search.toLowerCase()) || $el.dataset.ticketNo.includes(search)) &&
            (!status   || $el.dataset.status   === status)   &&
            (!priority || $el.dataset.priority === priority) &&
            (!ticketType || $el.dataset.type === ticketType)
        "
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0">

        {{-- Bulk select --}}
        <td class="p-3 pr-5">
            <input type="checkbox" class="ticket-check rounded text-indigo-600"
                   value="{{ $ticket->id }}"
                   x-model="bulkSelected">
        </td>

        {{-- شماره --}}
        <td class="p-3">
            <span class="font-mono font-bold text-indigo-700 text-xs bg-indigo-50 px-2 py-1 rounded-lg border border-indigo-100">{{ $ticket->ticket_number }}</span>
        </td>

        {{-- سایت --}}
        <td class="p-3">
            @if($ticket->site)
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-sky-500 shrink-0"></span>
                <span class="text-xs text-gray-700 font-medium">{{ $ticket->site->name }}</span>
            </div>
            @else
            <span class="text-xs text-gray-400">—</span>
            @endif
        </td>

        {{-- موضوع + last message preview ──────────────────────────── --}}
        <td class="p-3">
            <a href="{{ route('tickets.admin.show', $ticket->id) }}" class="group block">
                {{-- سطر اول: بج FR --}}
                @if($isFR)
                <div class="mb-1">
                    <span class="fr-type-badge">
                        💡 درخواست ویژگی
                        @if($ticket->fr_status)
                        <span class="opacity-70">· {{ $ticket->frStatusLabel() }}</span>
                        @endif
                    </span>
                </div>
                @endif
                {{-- سطر دوم: عنوان + بج اعلان --}}
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-sm font-semibold {{ $isFR ? 'text-purple-900 group-hover:text-purple-700' : 'text-gray-800 group-hover:text-indigo-700' }} transition-colors">{{ Str::limit($ticket->subject, 45) }}</span>
                    @if($attnBadgeLabel)
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9px] font-bold border awaits-reply-badge shrink-0 {{ $attnBadgeColor }}">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        {{ $attnBadgeLabel }}
                    </span>
                    @endif
                </div>
                {{-- Last message preview + time ──────────────────────────── --}}
                @if($lastMsgPreview)
                <div class="ticket-last-msg flex items-center gap-1 mt-1">
                    <span class="truncate">{{ $lastMsgPreview }}</span>
                    @if($lastMsgTime)
                    <span class="shrink-0 text-[10px] text-gray-400 mr-auto pr-1">· {{ \App\Helpers\Jalali::formatFa($lastMsgTime, 'H:i') }}</span>
                    @endif
                </div>
                @endif
                @if($ticket->category)
                <span class="inline-block mt-0.5 text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">{{ $ticket->category }}</span>
                @endif
            </a>
        </td>

        {{-- وضعیت --}}
        <td class="p-3">
            @if($ticket->status === 'open')
                <span class="tkt-badge bg-yellow-50 text-yellow-700 border-yellow-200">
                    <span class="pdot bg-yellow-500"></span>باز
                </span>
            @elseif($ticket->status === 'in_progress')
                <span class="tkt-badge bg-blue-50 text-blue-700 border-blue-200">
                    <span class="pdot bg-blue-500"></span>در حال بررسی
                </span>
            @else
                <span class="tkt-badge bg-green-50 text-green-700 border-green-200">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>بسته
                </span>
            @endif
        </td>

        {{-- اولویت --}}
        <td class="p-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $pBg }}">
                <span class="pdot {{ $pDot }}"></span>{{ $pLbl }}
            </span>
        </td>

        {{-- مسئول --}}
        <td class="p-3">
            @if($ticket->assignee)
            <div class="flex items-center gap-1.5">
                <div class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-[10px] font-bold shrink-0">
                    {{ mb_substr($ticket->assignee->name, 0, 1) }}
                </div>
                <span class="text-xs text-gray-700 truncate max-w-[80px]">{{ $ticket->assignee->name }}</span>
            </div>
            @else
            <span class="text-xs text-gray-300 italic">تخصیص نیافته</span>
            @endif
        </td>

        {{-- SLA --}}
        @if($slaEnabled)
        <td class="p-3">
            <span class="text-xs text-gray-400">—</span>
        </td>
        @endif

        {{-- تاریخ --}}
        <td class="p-3 text-xs text-gray-500 whitespace-nowrap">
            {{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d') }}
            <div class="text-[10px] text-gray-400">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'H:i') }}</div>
        </td>

        {{-- عملیات --}}
        <td class="p-3 pl-5">
            <div class="flex items-center gap-2">
                <a href="{{ route('tickets.admin.show', $ticket->id) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-all hover:shadow-sm whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    مشاهده
                </a>
                @can('delete', $ticket)
                <form method="POST" action="{{ route('tickets.admin.destroy', $ticket->id) }}"
                      x-data
                      @submit.prevent="if(confirm('آیا از حذف تیکت {{ $ticket->ticket_number }} اطمینان دارید؟ این عمل قابل بازگشت نیست.')) $el.submit()">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-1 px-2.5 py-2 rounded-xl text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 transition-all hover:shadow-sm whitespace-nowrap">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                        حذف
                    </button>
                </form>
                @endcan
            </div>
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="{{ $slaEnabled ? 10 : 9 }}" class="text-center py-16">
            <div class="flex flex-col items-center gap-3">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center text-3xl shadow-sm">📭</div>
                <p class="text-gray-600 font-semibold">هیچ تیکتی یافت نشد</p>
                <p class="text-gray-400 text-sm">فیلترها را تغییر دهید یا تیکت جدید ثبت کنید.</p>
                <a href="{{ route('tickets.admin.create') }}" class="btn-gradient !text-sm !py-2 !px-5 mt-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    ثبت تیکت جدید
                </a>
            </div>
        </td>
    </tr>
    @endforelse
    </tbody>
    </table>
    </div>
    </div>

    {{-- Pagination --}}
    @if(method_exists($tickets, 'links'))
    <div class="mt-6 flex justify-center">
        {{ $tickets->withQueryString()->links() }}
    </div>
    @endif

    {{-- ─── Module Features ────────────────────────────────────────────── --}}
    <div class="section-divider">
        <hr><span>قابلیت‌های ماژول پشتیبانی</span><hr>
    </div>
    @php
    $features = [
        ['icon' => '💬', 'label' => 'چت آنی', 'desc' => 'رابط چت با حباب‌های رنگی و اسکرول خودکار', 'color' => 'indigo'],
        ['icon' => '📱', 'label' => 'اعلان پیامکی', 'desc' => 'SMS خودکار برای رویدادهای تیکت', 'color' => 'purple'],
        ['icon' => '⚡', 'label' => 'SLA هوشمند', 'desc' => 'ردیابی و هشدار نقض توافق سطح خدمت', 'color' => 'amber'],
        ['icon' => '👤', 'label' => 'تخصیص کارشناس', 'desc' => 'مدیریت کامل تخصیص و سابقه تغییرات', 'color' => 'blue'],
        ['icon' => '🔗', 'label' => 'چند سایته', 'desc' => 'تخصیص تیکت به سایت دلخواه', 'color' => 'teal'],
        ['icon' => '📝', 'label' => 'یادداشت داخلی', 'desc' => 'یادداشت‌های محرمانه برای کارشناسان', 'color' => 'green'],
        ['icon' => '🤖', 'label' => 'API پروکسی', 'desc' => 'اتصال سایت مشتری از طریق API', 'color' => 'sky'],
        ['icon' => '🔔', 'label' => 'ناظران تیکت', 'desc' => 'سیستم CC و ناظران خودکار', 'color' => 'rose'],
        ['icon' => '📊', 'label' => 'داشبورد تحلیلی', 'desc' => 'آمار، نقشه حرارتی و بار کاری', 'color' => 'violet'],
        ['icon' => '♾️', 'label' => 'حاکمیت یکپارچه', 'desc' => 'یک منبع معتبر برای تمام پروژه‌ها', 'color' => 'slate'],
    ];
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mt-4">
        @foreach($features as $feat)
        <div class="feature-pill">
            <div class="text-2xl mb-2">{{ $feat['icon'] }}</div>
            <p class="text-xs font-bold text-gray-800">{{ $feat['label'] }}</p>
            <p class="text-[10px] text-gray-400 mt-0.5 leading-relaxed">{{ $feat['desc'] }}</p>
        </div>
        @endforeach
    </div>

</div>{{-- end x-data --}}
@include('tickets::admin.tickets.guide')
@endsection
