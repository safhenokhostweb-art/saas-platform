@extends('layouts.admin')

@section('title', 'تاریخچه بازیابی')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">تاریخچه بازیابی</h1>
            <p class="text-sm text-gray-500 mt-1">تمام عملیات بازیابی انجام‌شده (واقعی و شبیه‌سازی)</p>
        </div>
        <a href="{{ route('admin.disaster-recovery.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            ویزارد بازیابی
        </a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <form method="GET" class="flex items-center gap-3 flex-wrap">
            <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">همه انواع</option>
                <option value="platform_database" {{ request('type') === 'platform_database' ? 'selected' : '' }}>دیتابیس پلتفرم</option>
                <option value="platform_storage"  {{ request('type') === 'platform_storage'  ? 'selected' : '' }}>فایل‌های پلتفرم</option>
                <option value="tenant_database"   {{ request('type') === 'tenant_database'   ? 'selected' : '' }}>دیتابیس تِنِنت</option>
                <option value="tenant_storage"    {{ request('type') === 'tenant_storage'    ? 'selected' : '' }}>فایل‌های تِنِنت</option>
            </select>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">همه وضعیت‌ها</option>
                <option value="success"          {{ request('status') === 'success'          ? 'selected' : '' }}>موفق</option>
                <option value="failed"           {{ request('status') === 'failed'           ? 'selected' : '' }}>ناموفق</option>
                <option value="running"          {{ request('status') === 'running'          ? 'selected' : '' }}>در حال اجرا</option>
                <option value="dry_run_complete" {{ request('status') === 'dry_run_complete' ? 'selected' : '' }}>شبیه‌سازی</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-700 transition">فیلتر</button>
            <a href="{{ route('admin.disaster-recovery.logs') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">پاک کردن</a>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($logs->isEmpty())
        <div class="text-center py-16">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-gray-500">هیچ بازیابی‌ای ثبت نشده است</p>
        </div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-right px-4 py-3 font-medium text-gray-700">نوع</th>
                    <th class="text-right px-4 py-3 font-medium text-gray-700">وضعیت</th>
                    <th class="text-right px-4 py-3 font-medium text-gray-700">فایل بکاپ</th>
                    <th class="text-right px-4 py-3 font-medium text-gray-700">هدف</th>
                    <th class="text-right px-4 py-3 font-medium text-gray-700">اجراکننده</th>
                    <th class="text-right px-4 py-3 font-medium text-gray-700">زمان</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($logs as $log)
                @php
                $statusColor = match($log->status) {
                    'success'          => 'bg-green-100 text-green-700',
                    'failed'           => 'bg-red-100 text-red-700',
                    'running'          => 'bg-blue-100 text-blue-700',
                    'dry_run_complete' => 'bg-gray-100 text-gray-600',
                    default            => 'bg-gray-100 text-gray-600',
                };
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <span class="text-gray-800 font-medium">{{ $log->typeLabelFa() }}</span>
                        @if($log->dry_run)
                        <span class="mr-1 text-xs bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded">Dry Run</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">{{ $log->statusLabelFa() }}</span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600 max-w-xs truncate">
                        {{ basename($log->backup_file) }}
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ $log->site ? $log->site->name . ' (ID: ' . $log->site_id . ')' : 'پلتفرم' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ $log->executor ? $log->executor->email : '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ $log->created_at->diffForHumans() }}
                    </td>
                    <td class="px-4 py-3 text-left">
                        <a href="{{ route('admin.disaster-recovery.logs.show', $log) }}"
                           class="text-blue-600 hover:underline text-xs">جزئیات</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $logs->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
