@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', 'مدیریت تیکت‌های پشتیبانی')

@section('content')
<x-core-ui::design-tokens />

@php
    $total      = $stats['total']       ?? 0;
    $open       = $stats['open']        ?? 0;
    $inProgress = $stats['in_progress'] ?? 0;
    $closed     = $stats['closed']      ?? 0;
    $slaOk      = ($stats['sla_breaches'] ?? 0) === 0;

    $totalForPct = max(1, $total);
    $openPct     = round($open       / $totalForPct * 100);
    $inPct       = round($inProgress / $totalForPct * 100);
    $closPct     = round($closed     / $totalForPct * 100);
    $queueScore  = $openPct < 30 ? 'سالم' : ($openPct < 60 ? 'متوسط' : 'بحرانی');
    $queueColor  = $openPct < 30 ? 'text-green-600' : ($openPct < 60 ? 'text-amber-600' : 'text-red-600');

    $highRow    = collect($heatmap)->firstWhere('key', 'high')   ?? ['open' => 0, 'in_progress' => 0];
    $normRow    = collect($heatmap)->firstWhere('key', 'normal') ?? ['open' => 0, 'in_progress' => 0];
    $lowRow     = collect($heatmap)->firstWhere('key', 'low')    ?? ['open' => 0, 'in_progress' => 0];
    $highOpen   = $highRow['open'];   $highInProg = $highRow['in_progress'];
    $normOpen   = $normRow['open'];   $normInProg = $normRow['in_progress'];
    $lowOpen    = $lowRow['open'];    $lowInProg  = $lowRow['in_progress'];
    $heatMax    = max(1, $highOpen, $highInProg, $normOpen, $normInProg, $lowOpen, $lowInProg);

    if (!function_exists('siteHeatColor')) {
        function siteHeatColor($v, $max, $base) {
            $pct = $v / $max;
            if ($pct === 0)   return 'bg-gray-100 text-gray-400';
            if ($pct < .33)   return "bg-{$base}-100 text-{$base}-700";
            if ($pct < .66)   return "bg-{$base}-200 text-{$base}-800";
            return "bg-{$base}-400 text-white";
        }
    }
@endphp

<div class="{{ config('tickets.site_container', '') }} px-4 py-5"
     x-data="{ search: '', status: '', priority: '' }">

    {{-- ─── Flash Messages ──────────────────────────────────────────────── --}}
    @if(!empty($error))
    <div class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-2xl px-5 py-3.5 mb-5">
        <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        <span class="text-sm text-red-700">{{ $error }}</span>
    </div>
    @endif

    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl px-5 py-3.5 mb-5"
         x-data="{ show: true }" x-show="show" x-transition.duration.300ms>
        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm text-green-800 font-medium flex-1">{{ session('success') }}</span>
        <button @click="show=false" class="text-green-400 hover:text-green-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    {{-- ─── Stat Cards ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">

        {{-- کل --}}
        <div class="tkt-stat col-span-2 sm:col-span-1">
            <div class="tkt-stat__accent bg-gradient-to-b from-indigo-500 to-purple-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-gradient-to-br from-indigo-50 to-purple-50 text-indigo-600">📫</div>
                <div>
                    <div class="tkt-stat__val text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($total) }}</div>
                    <div class="tkt-stat__label text-gray-500">کل تیکت‌ها</div>
                </div>
            </div>
        </div>

        {{-- باز --}}
        <div class="tkt-stat">
            <div class="tkt-stat__accent bg-gradient-to-b from-yellow-400 to-orange-400"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-yellow-50 text-yellow-600">
                    <span class="relative flex h-6 w-6 items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-30"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-yellow-500"></span>
                    </span>
                </div>
                <div>
                    <div class="tkt-stat__val text-yellow-700">{{ \App\Helpers\NumberHelper::formatFa($open) }}</div>
                    <div class="tkt-stat__label text-yellow-600">باز</div>
                </div>
            </div>
        </div>

        {{-- در حال بررسی --}}
        <div class="tkt-stat">
            <div class="tkt-stat__accent bg-gradient-to-b from-blue-400 to-indigo-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-blue-50 text-blue-600">
                    <svg class="w-6 h-6 animate-spin" style="animation-duration:3s" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-blue-700">{{ \App\Helpers\NumberHelper::formatFa($inProgress) }}</div>
                    <div class="tkt-stat__label text-blue-600">در حال بررسی</div>
                </div>
            </div>
        </div>

        {{-- بسته --}}
        <div class="tkt-stat">
            <div class="tkt-stat__accent bg-gradient-to-b from-green-400 to-teal-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-green-50 text-green-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-green-700">{{ \App\Helpers\NumberHelper::formatFa($closed) }}</div>
                    <div class="tkt-stat__label text-green-600">بسته شده</div>
                </div>
            </div>
        </div>

        {{-- میانگین پاسخ --}}
        <div class="tkt-stat">
            <div class="tkt-stat__accent bg-gradient-to-b from-teal-400 to-cyan-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-teal-50 text-teal-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-teal-700">—</div>
                    <div class="tkt-stat__label text-teal-600">میانگین پاسخ</div>
                    <span class="tkt-stat__trend bg-teal-50 {{ $slaOk ? 'text-teal-700' : 'text-red-600' }}">
                        {{ $slaOk ? 'SLA سالم' : 'نقض SLA' }}
                    </span>
                </div>
            </div>
        </div>

    </div>

    {{-- ─── Widgets Row ─────────────────────────────────────────────────── --}}
    {{-- RTL DOM order: Queue Health (→RIGHT) | Heatmap (→MIDDLE) | Agent Workload (→LEFT) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

        {{-- 1. سلامت صف — appears RIGHT in RTL --}}
        <div class="glass-card-strong p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                سلامت صف
            </h3>
            <div class="space-y-3 text-sm">
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-gray-500 text-xs">باز</span>
                        <span class="font-bold text-yellow-600 text-xs">{{ \App\Helpers\NumberHelper::formatFa($openPct) }}%</span>
                    </div>
                    <div class="queue-health-bar">
                        <div class="queue-health-fill bg-gradient-to-r from-yellow-400 to-orange-400" style="width:{{ $openPct }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-gray-500 text-xs">در حال بررسی</span>
                        <span class="font-bold text-blue-600 text-xs">{{ \App\Helpers\NumberHelper::formatFa($inPct) }}%</span>
                    </div>
                    <div class="queue-health-bar">
                        <div class="queue-health-fill bg-gradient-to-r from-blue-400 to-indigo-500" style="width:{{ $inPct }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-gray-500 text-xs">بسته</span>
                        <span class="font-bold text-green-600 text-xs">{{ \App\Helpers\NumberHelper::formatFa($closPct) }}%</span>
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

        {{-- 2. نقشه حرارتی — appears MIDDLE --}}
        <div class="glass-card-strong p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                نقشه حرارتی اولویت
            </h3>
            <div class="overflow-x-auto">
            <table class="w-full text-xs text-center">
                <thead>
                    <tr class="text-gray-400 font-semibold">
                        <th class="py-1 text-right pr-1">اولویت</th>
                        <th class="py-1 px-2">باز</th>
                        <th class="py-1 px-2">در جریان</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-right text-red-600 font-bold py-1.5 pr-1">بالا</td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ siteHeatColor($highOpen, $heatMax, 'red') }}">{{ \App\Helpers\NumberHelper::formatFa($highOpen) }}</div></td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ siteHeatColor($highInProg, $heatMax, 'red') }}">{{ \App\Helpers\NumberHelper::formatFa($highInProg) }}</div></td>
                    </tr>
                    <tr>
                        <td class="text-right text-blue-600 font-bold py-1.5 pr-1">عادی</td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ siteHeatColor($normOpen, $heatMax, 'blue') }}">{{ \App\Helpers\NumberHelper::formatFa($normOpen) }}</div></td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ siteHeatColor($normInProg, $heatMax, 'blue') }}">{{ \App\Helpers\NumberHelper::formatFa($normInProg) }}</div></td>
                    </tr>
                    <tr>
                        <td class="text-right text-gray-500 font-bold py-1.5 pr-1">کم</td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ siteHeatColor($lowOpen, $heatMax, 'gray') }}">{{ \App\Helpers\NumberHelper::formatFa($lowOpen) }}</div></td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold {{ siteHeatColor($lowInProg, $heatMax, 'gray') }}">{{ \App\Helpers\NumberHelper::formatFa($lowInProg) }}</div></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        {{-- 3. بار کاری کارشناسان — appears LEFT in RTL --}}
        <div class="glass-card-strong p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                بار کاری کارشناسان
            </h3>
            @php $maxAgentTickets = max(1, $agentWorkload->max('count') ?? 1); @endphp
            @if($agentWorkload->isEmpty())
            <div class="text-center py-4 text-gray-400">
                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                <p class="text-xs">تیکتی تخصیص نیافته</p>
            </div>
            @else
            <div class="space-y-3">
                @foreach($agentWorkload->take(4) as $agent)
                @php
                    $agentPct  = round($agent['count'] / $maxAgentTickets * 100);
                    $barColor  = $agentPct > 75 ? 'bg-red-500' : ($agentPct > 50 ? 'bg-amber-400' : 'bg-indigo-500');
                @endphp
                <div class="agent-card">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                {{ mb_substr($agent['name'], 0, 1) }}
                            </div>
                            <span class="text-xs font-semibold text-gray-700 truncate max-w-[100px]">{{ $agent['name'] }}</span>
                        </div>
                        <span class="text-xs font-bold {{ $agentPct > 75 ? 'text-red-600' : 'text-gray-600' }}">{{ \App\Helpers\NumberHelper::formatFa($agent['count']) }}</span>
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

    {{-- ─── Actions Bar ─────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div class="flex items-center gap-2">
            <a href="{{ route('tickets.site.create') }}" class="btn-gradient">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                + تیکت جدید
            </a>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('tickets.site.settings') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 transition-all shadow-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                تنظیمات چت
            </a>
        </div>
    </div>

    {{-- ─── Filter Bar ──────────────────────────────────────────────────── --}}
    <div class="glass-filter-bar mb-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            {{-- Search takes all remaining flex space; min-w-0 allows it to shrink in RTL --}}
            <div class="relative flex-1 min-w-0 w-full sm:w-auto">
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" placeholder="جستجوی موضوع یا شماره تیکت..."
                       class="w-full pr-9 pl-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
            </div>
            {{-- Selects: max-width via inline style so width is capped regardless of Tailwind build --}}
            <select x-model="status" style="max-width:10rem"
                    class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه وضعیت‌ها</option>
                <option value="open">باز</option>
                <option value="in_progress">در حال بررسی</option>
                <option value="closed">بسته</option>
            </select>
            <select x-model="priority" style="max-width:10rem"
                    class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه اولویت‌ها</option>
                <option value="high">🔴 بالا</option>
                <option value="normal">🔵 عادی</option>
                <option value="low">⚪ کم</option>
            </select>
            <button @click="search=''; status=''; priority=''"
                    class="flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm text-gray-500 hover:bg-gray-50 transition-all w-full sm:w-auto justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                پاک کردن
            </button>
        </div>
    </div>

    {{-- ─── Tickets Table ───────────────────────────────────────────────── --}}
    <div class="glass-card-strong overflow-hidden">
    <div class="overflow-x-auto scrollbar-thin">
    @if($tickets->isEmpty())
        <div class="text-center py-16 flex flex-col items-center gap-3">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center text-3xl shadow-sm">📭</div>
            <p class="text-gray-600 font-semibold">هیچ تیکتی یافت نشد</p>
            <p class="text-gray-400 text-sm">اولین تیکت پشتیبانی خود را ثبت کنید.</p>
            <a href="{{ route('tickets.site.create') }}" class="btn-gradient !text-sm !py-2 !px-5 mt-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                ثبت تیکت جدید
            </a>
        </div>
    @else
    <table class="w-full text-right border-collapse" style="min-width:680px">
    <thead>
        <tr class="bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">شماره</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">موضوع</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">وضعیت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">اولویت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">مسئول</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">تاریخ</th>
            <th class="p-3 pl-5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">عملیات</th>
        </tr>
    </thead>
    <tbody>
    @foreach($tickets as $ticket)
    @php
        $p    = $ticket->priority ?? 'normal';
        $pBg  = $p === 'high' ? 'bg-red-50 text-red-700 border-red-200' : ($p === 'low' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-600 border-blue-200');
        $pDot = $p === 'high' ? 'bg-red-500' : ($p === 'low' ? 'bg-gray-400' : 'bg-blue-500');
        $pLbl = $p === 'high' ? 'بالا' : ($p === 'low' ? 'کم' : 'عادی');
    @endphp
    <tr class="border-b border-gray-50"
        data-subject="{{ $ticket->subject }}"
        data-ticket-no="{{ $ticket->ticket_number }}"
        data-status="{{ $ticket->status }}"
        data-priority="{{ $p }}"
        x-show="
            (!search || $el.dataset.subject.toLowerCase().includes(search.toLowerCase()) || $el.dataset.ticketNo.includes(search)) &&
            (!status || $el.dataset.status === status) &&
            (!priority || $el.dataset.priority === priority)
        "
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0">

        {{-- شماره --}}
        <td class="p-3 pr-5">
            <span class="font-mono font-bold text-indigo-700 text-xs bg-indigo-50 px-2 py-1 rounded-lg border border-indigo-100">{{ $ticket->ticket_number }}</span>
        </td>

        {{-- موضوع --}}
        <td class="p-3">
            <a href="{{ route('tickets.site.show', $ticket->id) }}" class="group">
                <span class="text-sm font-semibold text-gray-800 group-hover:text-indigo-700 transition-colors">{{ Str::limit($ticket->subject, 50) }}</span>
                @if($ticket->category)
                <span class="ml-2 text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">{{ $ticket->category }}</span>
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
            @if($ticket->assignee?->name)
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

        {{-- تاریخ --}}
        <td class="p-3 text-xs text-gray-500 whitespace-nowrap">
            {{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d') }}
            <div class="text-[10px] text-gray-400">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'H:i') }}</div>
        </td>

        {{-- عملیات --}}
        <td class="p-3 pl-5">
            <a href="{{ route('tickets.site.show', $ticket->id) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-all hover:shadow-sm whitespace-nowrap">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                مشاهده
            </a>
        </td>
    </tr>
    @endforeach
    </tbody>
    </table>
    @endif
    </div>
    </div>

    {{-- ─── Module Features ─────────────────────────────────────────────── --}}
    <div class="section-divider">
        <hr><span>قابلیت‌های ماژول پشتیبانی</span><hr>
    </div>
    @php
    $features = [
        ['icon' => '💬', 'label' => 'چت آنی',          'desc' => 'رابط چت با حباب‌های رنگی و اسکرول خودکار',     'color' => 'indigo'],
        ['icon' => '📱', 'label' => 'اعلان پیامکی',     'desc' => 'SMS خودکار برای رویدادهای تیکت',               'color' => 'purple'],
        ['icon' => '⚡', 'label' => 'SLA هوشمند',       'desc' => 'ردیابی و هشدار نقض توافق سطح خدمت',            'color' => 'amber'],
        ['icon' => '👤', 'label' => 'تخصیص کارشناس',   'desc' => 'مدیریت کامل تخصیص و سابقه تغییرات',            'color' => 'blue'],
        ['icon' => '🔗', 'label' => 'چند سایته',        'desc' => 'تخصیص تیکت به سایت دلخواه',                    'color' => 'teal'],
        ['icon' => '📝', 'label' => 'یادداشت داخلی',   'desc' => 'یادداشت‌های محرمانه برای کارشناسان',           'color' => 'green'],
        ['icon' => '🤖', 'label' => 'API پروکسی',       'desc' => 'اتصال سایت مشتری از طریق API',                 'color' => 'sky'],
        ['icon' => '🔔', 'label' => 'ناظران تیکت',      'desc' => 'سیستم CC و ناظران خودکار',                     'color' => 'rose'],
        ['icon' => '📊', 'label' => 'داشبورد تحلیلی',  'desc' => 'آمار، نقشه حرارتی و بار کاری',                 'color' => 'violet'],
        ['icon' => '♾️', 'label' => 'حاکمیت یکپارچه',  'desc' => 'یک منبع معتبر برای تمام پروژه‌ها',             'color' => 'slate'],
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
@endsection
