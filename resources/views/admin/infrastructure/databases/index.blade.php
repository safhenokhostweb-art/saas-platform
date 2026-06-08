@extends('layouts.admin')

@section('header', 'سرورهای دیتابیس')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">مدیریت دیتابیس‌ها</h1>
        <p class="text-sm text-gray-500">موجودی سرورهای دیتابیس، نسخه، اندازه و پشتیبان‌گیری</p>
    </div>
    <a href="{{ route('admin.infrastructure.databases.create') }}" class="btn btn-primary text-sm whitespace-nowrap">
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        افزودن سرور دیتابیس
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">نام / موتور</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">محیط</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">اندازه</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">کانکشن‌ها</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">پشتیبان‌گیری</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">رپلیکاسیون</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">وضعیت</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($databases as $db)
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="py-3 px-4">
                    <div class="font-semibold text-gray-800">{{ $db->name }}</div>
                    <div class="text-xs text-gray-400">{{ strtoupper($db->engine) }}{{ $db->version ? ' ' . $db->version : '' }}</div>
                    @if($db->server)<div class="text-xs text-gray-400">سرور: {{ $db->server->name }}</div>@endif
                </td>
                <td class="py-3 px-4">
                    @php $envColors = ['production' => 'bg-green-100 text-green-700', 'staging' => 'bg-yellow-100 text-yellow-700', 'development' => 'bg-blue-100 text-blue-700']; $envLabels = ['production' => 'پروداکشن', 'staging' => 'استیجینگ', 'development' => 'دولاپمنت']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $envColors[$db->environment] ?? 'bg-gray-100 text-gray-600' }}">{{ $envLabels[$db->environment] ?? $db->environment }}</span>
                </td>
                <td class="py-3 px-4 text-xs text-gray-600">
                    {{ $db->size_gb !== null ? number_format($db->size_gb, 1) . ' GB' : '—' }}
                    @if($db->monthly_growth_mb !== null)
                    <div class="text-gray-400">رشد: {{ $db->monthly_growth_mb >= 0 ? '+' : '' }}{{ number_format($db->monthly_growth_mb) }} MB/ماه</div>
                    @endif
                </td>
                <td class="py-3 px-4 text-xs text-gray-600">
                    @if($db->active_connections !== null)
                        {{ \App\Helpers\NumberHelper::formatFa($db->active_connections) }}
                        @if($db->max_connections) / {{ \App\Helpers\NumberHelper::formatFa($db->max_connections) }} @endif
                    @else —@endif
                </td>
                <td class="py-3 px-4 text-xs text-gray-600">
                    {{ $db->last_backup_at ? \App\Helpers\DateHelper::toJalali($db->last_backup_at) : '—' }}
                    @if($db->backup_frequency)<div class="text-gray-400">{{ $db->backup_frequency }}</div>@endif
                </td>
                <td class="py-3 px-4">
                    @if($db->replication_enabled)
                        <span class="px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700">{{ $db->replication_role === 'primary' ? 'Primary' : 'Replica' }}</span>
                    @else
                        <span class="text-gray-400 text-xs">—</span>
                    @endif
                </td>
                <td class="py-3 px-4">
                    @php $stC = ['online'=>'bg-green-100 text-green-700','offline'=>'bg-red-100 text-red-700','degraded'=>'bg-yellow-100 text-yellow-700','maintenance'=>'bg-blue-100 text-blue-700']; $stL = ['online'=>'آنلاین','offline'=>'آفلاین','degraded'=>'ضعیف','maintenance'=>'تعمیرات']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $stC[$db->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $stL[$db->status] ?? $db->status }}</span>
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.infrastructure.databases.edit', $db) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">ویرایش</a>
                        <form method="POST" action="{{ route('admin.infrastructure.databases.destroy', $db) }}" onsubmit="return confirm('حذف شود؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="py-12 text-center text-gray-400 text-sm">هنوز سرور دیتابیسی ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
