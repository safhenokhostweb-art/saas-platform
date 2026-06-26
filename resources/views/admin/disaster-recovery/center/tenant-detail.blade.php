@extends('layouts.admin')

@section('title', 'جزئیات DR — ' . $site->name)

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header ----------------------------------------------------------------}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.disaster-recovery.center.index') }}" class="hover:text-gray-700">مرکز DR</a>
                <span>/</span>
                <span>{{ $site->name }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $site->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $site->domain }}</p>
        </div>
        <a href="{{ route('admin.disaster-recovery.center.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
            بازگشت
        </a>
    </div>

    {{-- Tenant Info Card + Health Score --------------------------------------}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2 bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">اطلاعات تِنِنت</h2>
            <dl class="grid grid-cols-2 gap-y-2 text-sm">
                <dt class="text-gray-500">دامنه</dt>                  <dd class="text-gray-800">{{ $site->domain }}</dd>
                <dt class="text-gray-500">وضعیت چرخه عمر</dt>        <dd class="text-gray-800">{{ $site->lifecycle_status ?? 'active' }}</dd>
                <dt class="text-gray-500">شناسه سایت</dt>             <dd class="text-gray-800 font-mono">#{{ $site->id }}</dd>
                <dt class="text-gray-500">مسیر Arvan DB</dt>          <dd class="text-gray-800 font-mono text-xs">customer-sites/tenant-{{ $site->id }}/database/</dd>
                <dt class="text-gray-500">مسیر Arvan Storage</dt>     <dd class="text-gray-800 font-mono text-xs">customer-sites/tenant-{{ $site->id }}/storage/</dd>
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col items-center justify-center">
            <div class="text-5xl font-bold mb-2
                {{ $healthScore >= 80 ? 'text-green-600' : ($healthScore >= 50 ? 'text-amber-500' : 'text-red-600') }}">
                {{ $healthScore }}
            </div>
            <div class="text-sm text-gray-500">امتیاز سلامت DR</div>
            <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                <div class="h-2 rounded-full transition-all
                    {{ $healthScore >= 80 ? 'bg-green-500' : ($healthScore >= 50 ? 'bg-amber-500' : 'bg-red-500') }}"
                     style="width: {{ $healthScore }}%"></div>
            </div>
        </div>
    </div>

    {{-- Arvan Files -----------------------------------------------------------}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="text-sm font-semibold text-gray-700">فایل‌های Arvan — دیتابیس ({{ count($arvanDbFiles) }})</h2>
            </div>
            @if(empty($arvanDbFiles))
            <div class="py-8 text-center text-sm text-gray-400">هیچ فایلی در Arvan یافت نشد</div>
            @else
            <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                @foreach(array_slice($arvanDbFiles, 0, 10) as $file)
                <div class="px-5 py-2.5 flex items-center justify-between text-xs">
                    <span class="font-mono text-gray-700 truncate">{{ $file['filename'] }}</span>
                    <div class="flex items-center gap-3 shrink-0">
                        @if($file['size'])
                        <span class="text-gray-500">{{ number_format($file['size'] / 1048576, 1) }} MB</span>
                        @endif
                        @if($file['last_modified'])
                        <span class="text-gray-400">{{ \Carbon\Carbon::createFromTimestamp($file['last_modified'])->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="text-sm font-semibold text-gray-700">فایل‌های Arvan — Storage ({{ count($arvanStorageFiles) }})</h2>
            </div>
            @if(empty($arvanStorageFiles))
            <div class="py-8 text-center text-sm text-gray-400">هیچ فایل Storage یافت نشد</div>
            @else
            <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                @foreach(array_slice($arvanStorageFiles, 0, 10) as $file)
                <div class="px-5 py-2.5 flex items-center justify-between text-xs">
                    <span class="font-mono text-gray-700 truncate">{{ $file['filename'] }}</span>
                    @if($file['size'])
                    <span class="text-gray-500 shrink-0">{{ number_format($file['size'] / 1048576, 1) }} MB</span>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Backup Run History ---------------------------------------------------}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-700">تاریخچه بکاپ</h2>
        </div>
        @if($backupRuns->isEmpty())
        <div class="py-10 text-center text-sm text-gray-400">هیچ بکاپی برای این تِنِنت ثبت نشده (انتظار می‌رود — بکاپ تِنِنت هنوز پیاده‌سازی نشده)</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-right px-4 py-3 font-medium">نوع</th>
                        <th class="text-right px-4 py-3 font-medium">وضعیت</th>
                        <th class="text-right px-4 py-3 font-medium">اندازه</th>
                        <th class="text-right px-4 py-3 font-medium">مدت</th>
                        <th class="text-right px-4 py-3 font-medium">زمان</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($backupRuns as $run)
                    <tr>
                        <td class="px-4 py-3 text-gray-700">{{ $run->backup_type }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                {{ $run->isSuccess() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $run->isSuccess() ? 'موفق' : 'ناموفق' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $run->file_size_mb ? number_format($run->file_size_mb, 1) . ' MB' : '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $run->duration_seconds ? $run->duration_seconds . 's' : '—' }}</td>
                        <td class="px-4 py-3 text-gray-400">{{ $run->started_at?->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $backupRuns->links() }}
        @endif
    </div>

    {{-- Restore History ------------------------------------------------------}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-700">تاریخچه بازیابی</h2>
        </div>
        @if($restoreLogs->isEmpty())
        <div class="py-10 text-center text-sm text-gray-400">هیچ بازیابی‌ای برای این تِنِنت انجام نشده</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-right px-4 py-3 font-medium">نوع</th>
                        <th class="text-right px-4 py-3 font-medium">وضعیت</th>
                        <th class="text-right px-4 py-3 font-medium">اجراکننده</th>
                        <th class="text-right px-4 py-3 font-medium">زمان</th>
                        <th class="text-right px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($restoreLogs as $log)
                    <tr>
                        <td class="px-4 py-3 text-gray-700">{{ $log->restore_type }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                {{ $log->status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $log->status === 'success' ? 'موفق' : 'ناموفق' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $log->executor?->name ?? 'سیستم' }}</td>
                        <td class="px-4 py-3 text-gray-400">{{ $log->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.disaster-recovery.logs.show', $log) }}" class="text-blue-500 hover:text-blue-700 text-xs">جزئیات</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $restoreLogs->links() }}
        @endif
    </div>

</div>
@endsection
