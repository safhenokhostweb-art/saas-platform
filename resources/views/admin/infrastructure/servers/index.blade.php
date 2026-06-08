@extends('layouts.admin')

@section('header', 'سرورها')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">مدیریت سرورها</h1>
        <p class="text-sm text-gray-500">موجودی سرورها، نقش‌ها، وضعیت و منابع</p>
    </div>
    <a href="{{ route('admin.infrastructure.servers.create') }}" class="btn btn-primary text-sm whitespace-nowrap">
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        افزودن سرور
    </a>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="form-label text-xs">جستجو</label>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="نام، IP، هاست..." class="form-control text-sm" style="min-width:200px">
    </div>
    <div>
        <label class="form-label text-xs">محیط</label>
        <select name="environment" class="form-select text-sm">
            <option value="">همه</option>
            <option value="production" {{ request('environment') === 'production' ? 'selected' : '' }}>پروداکشن</option>
            <option value="staging" {{ request('environment') === 'staging' ? 'selected' : '' }}>استیجینگ</option>
            <option value="development" {{ request('environment') === 'development' ? 'selected' : '' }}>دولاپمنت</option>
        </select>
    </div>
    <div>
        <label class="form-label text-xs">وضعیت</label>
        <select name="status" class="form-select text-sm">
            <option value="">همه</option>
            <option value="online" {{ request('status') === 'online' ? 'selected' : '' }}>آنلاین</option>
            <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>آفلاین</option>
            <option value="degraded" {{ request('status') === 'degraded' ? 'selected' : '' }}>ضعیف</option>
            <option value="maintenance" {{ request('status') === 'maintenance' ? 'selected' : '' }}>تعمیرات</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary text-sm">اعمال</button>
    @if(request()->hasAny(['search','environment','status']))
        <a href="{{ route('admin.infrastructure.servers.index') }}" class="btn btn-outline-secondary text-sm">پاک‌کردن</a>
    @endif
</form>

{{-- Table --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">نام / IP</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">محیط</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">نقش‌ها</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">منابع</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">وضعیت</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">آخرین بکاپ</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($servers as $server)
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="py-3 px-4">
                    <div class="font-semibold text-gray-800">{{ $server->name }}</div>
                    <div class="text-xs text-gray-400">{{ $server->ip_address ?? '—' }} @if($server->hostname) · {{ $server->hostname }} @endif</div>
                    @if($server->os)<div class="text-xs text-gray-400">{{ $server->os }}</div>@endif
                </td>
                <td class="py-3 px-4">
                    @php
                        $envColors = ['production' => 'bg-green-100 text-green-700', 'staging' => 'bg-yellow-100 text-yellow-700', 'development' => 'bg-blue-100 text-blue-700'];
                        $envLabels = ['production' => 'پروداکشن', 'staging' => 'استیجینگ', 'development' => 'دولاپمنت'];
                    @endphp
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $envColors[$server->environment] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $envLabels[$server->environment] ?? $server->environment }}
                    </span>
                </td>
                <td class="py-3 px-4">
                    <div class="flex flex-wrap gap-1">
                        @foreach(($server->roles ?? []) as $role)
                        @php $roleColors = ['application'=>'bg-indigo-50 text-indigo-700','database'=>'bg-rose-50 text-rose-700','storage'=>'bg-emerald-50 text-emerald-700','cache'=>'bg-amber-50 text-amber-700','queue'=>'bg-sky-50 text-sky-700']; $roleLabels = ['application'=>'اپ','database'=>'DB','storage'=>'استوریج','cache'=>'کش','queue'=>'صف']; @endphp
                        <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $roleColors[$role] ?? 'bg-gray-100 text-gray-600' }}">{{ $roleLabels[$role] ?? $role }}</span>
                        @endforeach
                        @if(empty($server->roles))<span class="text-gray-400 text-xs">—</span>@endif
                    </div>
                </td>
                <td class="py-3 px-4 text-xs text-gray-500">
                    @if($server->cpu_cores){{ \App\Helpers\NumberHelper::formatFa($server->cpu_cores) }} هسته @endif
                    @if($server->ram_gb) · {{ \App\Helpers\NumberHelper::formatFa($server->ram_gb) }} GB RAM @endif
                    @if($server->disk_gb) · {{ \App\Helpers\NumberHelper::formatFa($server->disk_gb) }} GB دیسک @endif
                    @if(!$server->cpu_cores && !$server->ram_gb && !$server->disk_gb)— @endif
                </td>
                <td class="py-3 px-4">
                    @php $statusColors = ['online'=>'bg-green-100 text-green-700','offline'=>'bg-red-100 text-red-700','degraded'=>'bg-yellow-100 text-yellow-700','maintenance'=>'bg-blue-100 text-blue-700','unknown'=>'bg-gray-100 text-gray-600']; $statusLabels = ['online'=>'آنلاین','offline'=>'آفلاین','degraded'=>'ضعیف','maintenance'=>'تعمیرات','unknown'=>'نامشخص']; @endphp
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$server->status] ?? 'bg-gray-100 text-gray-600' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $server->status === 'online' ? 'bg-green-500' : ($server->status === 'offline' ? 'bg-red-500' : 'bg-yellow-500') }}"></span>
                        {{ $statusLabels[$server->status] ?? $server->status }}
                    </span>
                </td>
                <td class="py-3 px-4 text-xs text-gray-500">
                    {{ $server->last_backup_at ? \App\Helpers\Jalali::formatFa($server->last_backup_at, 'Y/m/d') : '—' }}
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.infrastructure.servers.edit', $server) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">ویرایش</a>
                        <form method="POST" action="{{ route('admin.infrastructure.servers.destroy', $server) }}" onsubmit="return confirm('حذف این سرور؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="py-12 text-center text-gray-400 text-sm">هنوز سروری ثبت نشده است.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $servers->appends(request()->query())->links() }}</div>

@endsection
