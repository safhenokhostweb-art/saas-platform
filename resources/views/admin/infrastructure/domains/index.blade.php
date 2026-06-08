@extends('layouts.admin')

@section('header', 'دامنه‌ها')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">مدیریت دامنه‌ها</h1>
        <p class="text-sm text-gray-500">ثبت‌کننده، SSL، انقضا و نسب به تِننت</p>
    </div>
    <a href="{{ route('admin.infrastructure.domains.create') }}" class="btn btn-primary text-sm whitespace-nowrap">
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        افزودن دامنه
    </a>
</div>

{{-- Quick Filters --}}
<div class="flex flex-wrap gap-2 mb-4">
    <a href="{{ route('admin.infrastructure.domains.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ !request()->hasAny(['expiring','ssl_expiring','type']) ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-indigo-300' }}">همه</a>
    <a href="{{ route('admin.infrastructure.domains.index', ['expiring' => 1]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ request('expiring') ? 'bg-amber-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-amber-300' }}">رو به انقضا</a>
    <a href="{{ route('admin.infrastructure.domains.index', ['ssl_expiring' => 1]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ request('ssl_expiring') ? 'bg-rose-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-rose-300' }}">SSL رو به انقضا</a>
    <a href="{{ route('admin.infrastructure.domains.index', ['type' => 'tenant']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ request('type') === 'tenant' ? 'bg-violet-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-violet-300' }}">تِننت</a>
    <a href="{{ route('admin.infrastructure.domains.index', ['type' => 'platform']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ request('type') === 'platform' ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-blue-300' }}">پلتفرم</a>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">دامنه</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">نوع</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">تِننت</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">انقضای دامنه</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">SSL</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">وضعیت</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($domains as $domain)
            <tr class="hover:bg-gray-50/50 transition-colors {{ $domain->days_until_expiry !== null && $domain->days_until_expiry < 0 ? 'bg-red-50/30' : ($domain->is_expiring ? 'bg-amber-50/30' : '') }}">
                <td class="py-3 px-4">
                    <div class="font-semibold text-gray-800">{{ $domain->domain }}</div>
                    @if($domain->registrar)<div class="text-xs text-gray-400">ثبت‌کننده: {{ $domain->registrar }}</div>@endif
                    @if($domain->dns_provider)<div class="text-xs text-gray-400">DNS: {{ $domain->dns_provider }}</div>@endif
                </td>
                <td class="py-3 px-4">
                    @php $typeColors = ['tenant'=>'bg-violet-50 text-violet-700','platform'=>'bg-blue-50 text-blue-700','cdn'=>'bg-sky-50 text-sky-700','api'=>'bg-indigo-50 text-indigo-700','mail'=>'bg-pink-50 text-pink-700']; $typeLabels = ['tenant'=>'تِننت','platform'=>'پلتفرم','cdn'=>'CDN','api'=>'API','mail'=>'ایمیل']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$domain->type] ?? 'bg-gray-100 text-gray-600' }}">{{ $typeLabels[$domain->type] ?? $domain->type }}</span>
                </td>
                <td class="py-3 px-4 text-xs text-gray-600">{{ $domain->site?->title ?? '—' }}</td>
                <td class="py-3 px-4 text-xs">
                    @if($domain->expires_at)
                        @if($domain->days_until_expiry < 0)
                            <span class="text-red-600 font-bold">منقضی شده</span>
                        @elseif($domain->days_until_expiry <= 7)
                            <span class="text-red-600 font-semibold">{{ \App\Helpers\NumberHelper::formatFa($domain->days_until_expiry) }} روز</span>
                        @elseif($domain->is_expiring)
                            <span class="text-amber-600">{{ \App\Helpers\NumberHelper::formatFa($domain->days_until_expiry) }} روز</span>
                        @else
                            <span class="text-gray-500">{{ \App\Helpers\Jalali::formatFa($domain->expires_at, 'Y/m/d') }}</span>
                        @endif
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="py-3 px-4">
                    @if($domain->ssl_enabled)
                        @if($domain->days_until_ssl_expiry !== null && $domain->days_until_ssl_expiry <= 0)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">منقضی</span>
                        @elseif($domain->is_ssl_expiring)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">{{ \App\Helpers\NumberHelper::formatFa($domain->days_until_ssl_expiry) }} روز</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">معتبر</span>
                        @endif
                    @else
                        <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">بدون SSL</span>
                    @endif
                </td>
                <td class="py-3 px-4">
                    @php $stColors = ['active'=>'bg-green-100 text-green-700','inactive'=>'bg-gray-100 text-gray-600','parked'=>'bg-blue-100 text-blue-700','transferred'=>'bg-purple-100 text-purple-700']; $stLabels = ['active'=>'فعال','inactive'=>'غیرفعال','parked'=>'پارک‌شده','transferred'=>'منتقل‌شده']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $stColors[$domain->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $stLabels[$domain->status] ?? $domain->status }}</span>
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.infrastructure.domains.edit', $domain) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">ویرایش</a>
                        <form method="POST" action="{{ route('admin.infrastructure.domains.destroy', $domain) }}" onsubmit="return confirm('حذف این دامنه؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="py-12 text-center text-gray-400 text-sm">هنوز دامنه‌ای ثبت نشده است.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $domains->appends(request()->query())->links() }}</div>

@endsection
