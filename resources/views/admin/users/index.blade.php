@extends('layouts.admin')

@section('header', 'مدیریت کاربران پلتفرم')

@section('content')

<div x-data="usersIndex()" x-init="init()" class="space-y-6">

    {{-- ═══════════════════════════════════════════
         آمار اصلی — ردیف اول (۴ کارت بزرگ)
    ═══════════════════════════════════════════ --}}
    @if(isset($stats) && is_array($stats))
    @php
        $total     = $stats['total'] ?? 0;
        $active    = $stats['active'] ?? 0;
        $inactive  = $stats['inactive'] ?? 0;
        $activeP   = $total > 0 ? round($active / $total * 100) : 0;
        $inactiveP = $total > 0 ? round($inactive / $total * 100) : 0;
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- کل کاربران --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-all hover:-translate-y-0.5 cursor-default">
            <div class="flex items-start justify-between mb-3">
                <div class="w-11 h-11 bg-indigo-50 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                @if(($stats['new_today'] ?? 0) > 0)
                <span class="text-xs bg-teal-50 text-teal-700 border border-teal-100 rounded-full px-2 py-0.5 font-medium">
                    +{{ \App\Helpers\NumberHelper::formatFa($stats['new_today']) }} امروز
                </span>
                @endif
            </div>
            <div class="text-3xl font-bold text-gray-800 tabular-nums">{{ \App\Helpers\NumberHelper::formatFa($total) }}</div>
            <div class="text-sm text-gray-500 mt-1">کل کاربران</div>
        </div>

        {{-- فعال --}}
        <div class="bg-white rounded-2xl shadow-sm border border-green-100 p-5 hover:shadow-md transition-all hover:-translate-y-0.5 cursor-default">
            <div class="flex items-start justify-between mb-3">
                <div class="w-11 h-11 bg-green-50 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-xs bg-green-50 text-green-700 border border-green-100 rounded-full px-2 py-0.5 font-medium">
                    {{ \App\Helpers\NumberHelper::toPersianNumbers($activeP) }}٪
                </span>
            </div>
            <div class="text-3xl font-bold text-green-700 tabular-nums">{{ \App\Helpers\NumberHelper::formatFa($active) }}</div>
            <div class="text-sm text-gray-500 mt-1">کاربران فعال</div>
        </div>

        {{-- غیرفعال --}}
        <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-5 hover:shadow-md transition-all hover:-translate-y-0.5 cursor-default">
            <div class="flex items-start justify-between mb-3">
                <div class="w-11 h-11 bg-red-50 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-xs bg-red-50 text-red-600 border border-red-100 rounded-full px-2 py-0.5 font-medium">
                    {{ \App\Helpers\NumberHelper::toPersianNumbers($inactiveP) }}٪
                </span>
            </div>
            <div class="text-3xl font-bold text-red-600 tabular-nums">{{ \App\Helpers\NumberHelper::formatFa($inactive) }}</div>
            <div class="text-sm text-gray-500 mt-1">غیرفعال</div>
        </div>

        {{-- عضو ماه جاری --}}
        <div class="bg-white rounded-2xl shadow-sm border border-blue-100 p-5 hover:shadow-md transition-all hover:-translate-y-0.5 cursor-default">
            <div class="flex items-start justify-between mb-3">
                <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-blue-700 tabular-nums">{{ \App\Helpers\NumberHelper::formatFa($stats['new_this_month'] ?? 0) }}</div>
            <div class="text-sm text-gray-500 mt-1">عضو ماه جاری</div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════
         آمار ثانویه — ردیف دوم (۴ کارت کوچک)
    ═══════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

        {{-- قفل شده --}}
        <div class="bg-white rounded-xl shadow-sm border border-amber-100 px-4 py-3 flex items-center gap-3 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 bg-amber-50 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <div class="text-xl font-bold text-amber-700">{{ \App\Helpers\NumberHelper::formatFa($stats['locked'] ?? 0) }}</div>
                <div class="text-xs text-gray-500">قفل شده</div>
            </div>
        </div>

        {{-- مدیران --}}
        <div class="bg-white rounded-xl shadow-sm border border-purple-100 px-4 py-3 flex items-center gap-3 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 bg-purple-50 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <div class="text-xl font-bold text-purple-700">{{ \App\Helpers\NumberHelper::formatFa($stats['admins'] ?? 0) }}</div>
                <div class="text-xs text-gray-500">مدیران</div>
            </div>
        </div>

        {{-- ۲FA فعال --}}
        <div class="bg-white rounded-xl shadow-sm border border-violet-100 px-4 py-3 flex items-center gap-3 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 bg-violet-50 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <div>
                <div class="text-xl font-bold text-violet-700">{{ \App\Helpers\NumberHelper::formatFa($stats['two_factor_enabled'] ?? 0) }}</div>
                <div class="text-xs text-gray-500">۲FA فعال</div>
            </div>
        </div>

        {{-- جدید امروز --}}
        <div class="bg-white rounded-xl shadow-sm border border-teal-100 px-4 py-3 flex items-center gap-3 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 bg-teal-50 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <div class="text-xl font-bold text-teal-700">{{ \App\Helpers\NumberHelper::formatFa($stats['new_today'] ?? 0) }}</div>
                <div class="text-xs text-gray-500">جدید امروز</div>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════
         نمودار روند ثبت‌نام
    ═══════════════════════════════════════════ --}}
    @if(isset($trend) && count($trend) > 0)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" x-data="{ chartOpen: true }">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-700 text-sm">روند ثبت‌نام کاربران</h3>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex bg-gray-100 rounded-lg p-0.5 gap-0.5">
                    <button id="chart-7d"
                            class="text-xs px-3 py-1 rounded-md font-medium transition-all">
                        ۷ روز
                    </button>
                    <button id="chart-30d"
                            class="text-xs px-3 py-1 rounded-md font-medium transition-all">
                        ۳۰ روز
                    </button>
                </div>
                <button @click="chartOpen = !chartOpen"
                        class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4 transition-transform" :class="chartOpen ? '' : 'rotate-180'"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                    </svg>
                </button>
            </div>
        </div>
        <div x-show="chartOpen" x-transition>
            <div class="relative h-44">
                <canvas id="registrationChart"></canvas>
            </div>
            <div class="flex justify-center gap-8 mt-3 pt-3 border-t border-gray-50">
                <div class="text-center">
                    <div class="text-xs text-gray-400">مجموع ۳۰ روز</div>
                    <div id="chart-total-label" class="text-base font-bold text-indigo-600 tabular-nums">—</div>
                </div>
                <div class="text-center">
                    <div class="text-xs text-gray-400">بیشترین روز</div>
                    <div id="chart-peak-label" class="text-base font-bold text-green-600 tabular-nums">—</div>
                </div>
                <div class="text-center">
                    <div class="text-xs text-gray-400">میانگین روزانه</div>
                    <div id="chart-avg-label" class="text-base font-bold text-gray-700 tabular-nums">—</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @include('admin.users._toast')

    @include('admin.users._filters')

    @include('admin.users._table')

    @include('admin.users._bulk_modal')

    @include('admin.users._delete_modal')

    <form id="bulk-form" method="POST" class="hidden"
          data-routes='{{ json_encode(["activate" => route("admin.users.bulk-toggle"), "deactivate" => route("admin.users.bulk-toggle"), "delete" => route("admin.users.bulk-delete"), "restore" => route("admin.users.bulk-restore")]) }}'>
        @csrf
        <input type="hidden" name="user_ids" id="bulk-ids">
        <input type="hidden" name="action" id="bulk-action-hidden">
    </form>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const trendAll = @json($trend ?? []);
    if (!trendAll.length) return;

    const toFa = n => String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);

    let chart, activeView = 30;

    function buildChart(days) {
        const data   = days === 7 ? trendAll.slice(-7) : trendAll;
        const labels = data.map(d => d.label);
        const counts = data.map(d => d.count);
        const total  = counts.reduce((a, b) => a + b, 0);
        const peak   = Math.max(...counts);
        const avg    = counts.length ? (total / counts.length).toFixed(1) : 0;

        document.getElementById('chart-total-label').textContent = toFa(total);
        document.getElementById('chart-peak-label').textContent  = toFa(peak);
        document.getElementById('chart-avg-label').textContent   = toFa(avg);

        const canvas = document.getElementById('registrationChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        const gradient = ctx.createLinearGradient(0, 0, 0, 180);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.18)');
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

        if (chart) chart.destroy();
        chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: counts,
                    borderColor: '#4f46e5',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#4f46e5',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        textDirection: 'rtl',
                        backgroundColor: '#1e1b4b',
                        padding: 10,
                        cornerRadius: 10,
                        callbacks: {
                            title: ([c]) => c.label,
                            label: (c) => ` ثبت‌نام: ${toFa(c.raw)} کاربر`,
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { color: '#9ca3af', font: { family: 'Vazirmatn, Tahoma', size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f8fafc', lineWidth: 1 },
                        border: { display: false },
                        ticks: {
                            precision: 0,
                            callback: v => toFa(v),
                            color: '#9ca3af',
                            font: { family: 'Vazirmatn, Tahoma', size: 10 }
                        }
                    }
                }
            }
        });
    }

    function setActiveBtn(days) {
        ['7', '30'].forEach(d => {
            const btn = document.getElementById(`chart-${d}d`);
            if (!btn) return;
            if (String(days) === d) {
                btn.className = 'text-xs px-3 py-1 rounded-md font-medium transition-all bg-white shadow-sm text-indigo-600';
            } else {
                btn.className = 'text-xs px-3 py-1 rounded-md font-medium transition-all text-gray-500 hover:text-gray-700';
            }
        });
    }

    document.getElementById('chart-7d')?.addEventListener('click', () => {
        activeView = 7; buildChart(7); setActiveBtn(7);
    });
    document.getElementById('chart-30d')?.addEventListener('click', () => {
        activeView = 30; buildChart(30); setActiveBtn(30);
    });

    buildChart(30);
    setActiveBtn(30);
})();

</script>
@endpush

<script>
function sortByClick(field) {
    var url = new URL(window.location.href);
    var dir = url.searchParams.get('sort') === field && url.searchParams.get('direction') === 'asc' ? 'desc' : 'asc';
    url.searchParams.set('sort', field);
    url.searchParams.set('direction', dir);
    window.location.href = url.toString();
}
</script>
@include('admin.users.guide')
@endsection
