@extends('layouts.admin')

@section('title', 'تاریخچه هشدارهای DR')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">تاریخچه هشدارهای DR</h1>
            <p class="text-sm text-gray-500 mt-1">هشدارهای ثبت‌شده توسط اسکنر سلامت DR</p>
        </div>
        <a href="{{ route('admin.disaster-recovery.center.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
            بازگشت به مرکز DR
        </a>
    </div>

    @if(!$alertLogExists)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-amber-800 text-sm space-y-2">
        <p class="font-semibold">جدول هشدارها ایجاد نشده</p>
        <p>برای فعال‌سازی تاریخچه هشدارهای in-app دستور زیر را اجرا کنید:</p>
        <code class="block bg-white border border-amber-200 rounded px-3 py-2 font-mono text-xs">php artisan migrate</code>
    </div>
    @else

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <form method="GET" class="flex items-center gap-3 flex-wrap">
            <select name="severity" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">همه شدت‌ها</option>
                <option value="critical" {{ request('severity') === 'critical' ? 'selected' : '' }}>بحرانی</option>
                <option value="high"     {{ request('severity') === 'high'     ? 'selected' : '' }}>بالا</option>
                <option value="medium"   {{ request('severity') === 'medium'   ? 'selected' : '' }}>متوسط</option>
            </select>
            <select name="state" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">همه وضعیت‌ها</option>
                <option value="open"     {{ request('state') === 'open'     ? 'selected' : '' }}>باز</option>
                <option value="resolved" {{ request('state') === 'resolved' ? 'selected' : '' }}>حل‌شده</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-700 transition">فیلتر</button>
            <a href="{{ route('admin.disaster-recovery.center.alerts') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">پاک کردن</a>
        </form>
    </div>

    {{-- Alert Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($alerts->isEmpty())
        <div class="py-16 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-gray-500">هیچ هشداری یافت نشد</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-right px-4 py-3 font-medium">نوع</th>
                        <th class="text-right px-4 py-3 font-medium">شدت</th>
                        <th class="text-right px-4 py-3 font-medium">پیام</th>
                        <th class="text-right px-4 py-3 font-medium">وضعیت</th>
                        <th class="text-right px-4 py-3 font-medium">زمان</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($alerts as $alert)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $alert->alert_type }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold
                                {{ match($alert->severity) {
                                    'critical' => 'bg-red-100 text-red-700',
                                    'high'     => 'bg-orange-100 text-orange-700',
                                    'medium'   => 'bg-amber-100 text-amber-700',
                                    default    => 'bg-gray-100 text-gray-600',
                                } }}">
                                {{ match($alert->severity) {
                                    'critical' => 'بحرانی',
                                    'high'     => 'بالا',
                                    'medium'   => 'متوسط',
                                    default    => $alert->severity,
                                } }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-700 max-w-xs truncate">{{ $alert->message }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs
                                {{ $alert->resolved ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $alert->resolved ? 'حل‌شده' : 'باز' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ \Carbon\Carbon::parse($alert->created_at)->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $alerts->withQueryString()->links() }}
        </div>
        @endif
    </div>

    @endif

</div>
@endsection
