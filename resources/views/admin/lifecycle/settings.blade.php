@extends('layouts.admin')

@section('title', 'تنظیمات چرخه عمر سایت')

@section('content')
<div class="p-6 max-w-2xl" dir="rtl">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.lifecycle.overview') }}"
           class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 hover:text-gray-700 transition">
            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">تنظیمات چرخه عمر</h1>
            <p class="text-sm text-gray-500 mt-0.5">مدیریت مدت‌های زمانی و هزینه‌های چرخه عمر سایت</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.lifecycle.settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Time Periods --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm space-y-5">
            <h2 class="font-bold text-gray-800 border-b border-gray-100 pb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                مدت‌های زمانی
            </h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    دوره اعتبار (روز) — پس از انقضا
                </label>
                <p class="text-xs text-gray-500 mb-2">سایت در این مدت آنلاین می‌ماند و مشتری پیام تمدید دریافت می‌کند</p>
                <input type="number" name="grace_period_days"
                       value="{{ old('grace_period_days', $settings['grace_period_days']) }}"
                       min="1" max="365" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                @error('grace_period_days') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    آرشیو پس از تعلیق (روز)
                </label>
                <p class="text-xs text-gray-500 mb-2">پس از این مدت از تعلیق، سایت آرشیو و منابع آزاد می‌شود</p>
                <input type="number" name="archive_after_days"
                       value="{{ old('archive_after_days', $settings['archive_after_days']) }}"
                       min="1" max="730" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                @error('archive_after_days') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    حذف دائمی پس از آرشیو (روز)
                </label>
                <p class="text-xs text-gray-500 mb-2">پس از این مدت از آرشیو (بدون درخواست بازگردانی)، سایت برای همیشه حذف می‌شود</p>
                <input type="number" name="delete_after_days"
                       value="{{ old('delete_after_days', $settings['delete_after_days']) }}"
                       min="1" max="3650" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                @error('delete_after_days') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Fees --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm space-y-5">
            <h2 class="font-bold text-gray-800 border-b border-gray-100 pb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                هزینه‌های بازگردانی (ریال)
            </h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">هزینه فعال‌سازی مجدد</label>
                <input type="number" name="reactivation_fee"
                       value="{{ old('reactivation_fee', $settings['reactivation_fee']) }}"
                       min="0" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">هزینه بازگردانی آرشیو</label>
                <input type="number" name="archive_restore_fee"
                       value="{{ old('archive_restore_fee', $settings['archive_restore_fee']) }}"
                       min="0" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">هزینه نگهداری</label>
                <input type="number" name="storage_retention_fee"
                       value="{{ old('storage_retention_fee', $settings['storage_retention_fee']) }}"
                       min="0" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
            </div>

            <div class="text-xs text-gray-500 bg-gray-50 rounded-lg p-3">
                هزینه‌های صفر (۰) به این معناست که کاربر بدون پرداخت می‌تواند درخواست بازگردانی دهد.
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                ذخیره تنظیمات
            </button>
            <a href="{{ route('admin.lifecycle.overview') }}"
               class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition">
                انصراف
            </a>
        </div>
    </form>
</div>
@endsection
