@extends('layouts.admin')

@section('title', 'مرکز DR — داشبورد بازیابی فاجعه')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header ----------------------------------------------------------------}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">مرکز DR</h1>
            <p class="text-sm text-gray-500 mt-1">داشبورد مانیتورینگ بازیابی فاجعه — فقط خواندنی</p>
        </div>
        <div class="flex items-center gap-3">
            @if($alertLogExists)
            <a href="{{ route('admin.disaster-recovery.center.alerts') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                تاریخچه هشدارها
            </a>
            @endif
            <a href="{{ route('admin.disaster-recovery.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                ویزارد بازیابی
            </a>
        </div>
    </div>

    {{-- Alert Banners ---------------------------------------------------------}}
    @if(!empty($alerts))
    <div class="space-y-2">
        @foreach($alerts as $alert)
        <div class="flex items-start gap-3 px-4 py-3 rounded-xl border
            {{ $alert['severity'] === 'critical' ? 'bg-red-50 border-red-300 text-red-800' : 'bg-amber-50 border-amber-300 text-amber-800' }}">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <span class="font-semibold text-xs uppercase ml-2">{{ $alert['type'] }}</span>
                <span class="text-sm">{{ $alert['message'] }}</span>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Restore Lock Warning --------------------------------------------------}}
    @if($lockActive)
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-50 border border-blue-300 text-blue-800">
        <svg class="w-5 h-5 shrink-0 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <span class="text-sm font-medium">عملیات بازیابی در حال اجرا است — داشبورد فقط‌خواندنی است</span>
    </div>
    @endif

    {{-- Section 1: Platform Backup Health ------------------------------------}}
    @include('admin.disaster-recovery.center.partials.platform-health', compact(
        'lastDb', 'lastStorage', 'dbHealth', 'storageHealth', 'arvanDbFiles', 'arvanStorageFiles'
    ))

    {{-- Section 2: Restore Monitor -------------------------------------------}}
    @include('admin.disaster-recovery.center.partials.restore-monitor', compact(
        'recentRestores', 'restoreStats'
    ))

    {{-- Section 3 + 4: Chart and Integrity (two columns) --------------------}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @include('admin.disaster-recovery.center.partials.size-chart')
        @include('admin.disaster-recovery.center.partials.integrity', compact('syncGap', 'dbRunsLast7d', 'arvanDbLast7d'))
    </div>

    {{-- Section 5: Tenant Health Table (lazy via Alpine) --------------------}}
    @include('admin.disaster-recovery.center.partials.tenant-health')

    {{-- Section 6: Alert Feed (auto-refresh) ---------------------------------}}
    @if($alertLogExists)
    @include('admin.disaster-recovery.center.partials.alert-feed')
    @endif

    {{-- Backup Failure Log ---------------------------------------------------}}
    @if($backupFailures->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">خطاهای اخیر بکاپ</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($backupFailures as $fail)
            <div class="px-5 py-3 flex items-start gap-4 text-sm">
                <span class="shrink-0 px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">{{ $fail->backup_type }}</span>
                <span class="text-gray-500 shrink-0">{{ $fail->site ? $fail->site->name : 'پلتفرم' }}</span>
                <span class="text-gray-700 truncate flex-1">{{ Str::limit($fail->error_message, 120) }}</span>
                <span class="text-gray-400 shrink-0">{{ $fail->started_at?->diffForHumans() }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
// Timeline chart fetch on load
document.addEventListener('DOMContentLoaded', () => {
    fetch('{{ route('admin.disaster-recovery.center.timeline') }}')
        .then(r => r.json())
        .then(data => {
            const canvas = document.getElementById('dr-backup-chart');
            if (!canvas || !window.Chart) return;
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets.map((ds, i) => ({
                        label: ds.label,
                        data: ds.data,
                        borderColor: i === 0 ? '#3b82f6' : '#10b981',
                        backgroundColor: i === 0 ? 'rgba(59,130,246,0.08)' : 'rgba(16,185,129,0.08)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 3,
                    })),
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: false } },
                },
            });
        })
        .catch(() => {});
});
</script>
@endpush
@endsection
