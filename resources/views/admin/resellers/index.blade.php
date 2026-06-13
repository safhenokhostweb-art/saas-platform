@extends('layouts.admin')

@section('header', 'مدیریت نمایندگان')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">نمایندگان فروش</h1>
    <a href="{{ route('admin.resellers.create') }}" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        افزودن نماینده
    </a>
</div>

@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-3 text-right text-gray-600 font-medium">نماینده / شرکت</th>
                <th class="p-3 text-right text-gray-600 font-medium">موبایل</th>
                <th class="p-3 text-right text-gray-600 font-medium">نرخ کمیسیون</th>
                <th class="p-3 text-right text-gray-600 font-medium">تخفیف عمده</th>
                <th class="p-3 text-right text-gray-600 font-medium">مشتریان</th>
                <th class="p-3 text-right text-gray-600 font-medium">وایت‌لیبل</th>
                <th class="p-3 text-right text-gray-600 font-medium">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($resellers as $reseller)
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-3">
                        <div class="font-medium text-gray-800">{{ $reseller->company_name ?: '—' }}</div>
                        @if($reseller->brand_name)
                            <div class="text-xs text-gray-400">{{ $reseller->brand_name }}</div>
                        @endif
                    </td>
                    <td class="p-3 text-gray-600">{{ \App\Helpers\NumberHelper::toPersianNumbers($reseller->user->mobile ?? '—') }}</td>
                    <td class="p-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                            {{ \App\Helpers\NumberHelper::toPersianNumbers($reseller->commission_rate) }}٪
                        </span>
                    </td>
                    <td class="p-3 text-gray-600">
                        {{ $reseller->wholesale_discount_percent > 0 ? \App\Helpers\NumberHelper::toPersianNumbers($reseller->wholesale_discount_percent) . '٪' : '—' }}
                    </td>
                    <td class="p-3 text-gray-600">{{ \App\Helpers\NumberHelper::toPersianNumbers($reseller->customers->count()) }}</td>
                    <td class="p-3">
                        @if($reseller->is_white_label)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                بله
                            </span>
                        @else
                            <span class="text-gray-400 text-xs">خیر</span>
                        @endif
                    </td>
                    <td class="p-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.resellers.edit', $reseller) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">ویرایش</a>
                            <form method="post" action="{{ route('admin.resellers.destroy', $reseller) }}" onsubmit="return confirm('حذف این نماینده؟');" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-8 text-center text-gray-400">
                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        هیچ نماینده‌ای ثبت نشده است.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($resellers->hasPages())
    <div class="mt-4">{{ $resellers->links() }}</div>
@endif
@endsection
