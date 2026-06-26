@extends('layouts.admin')

@section('title', 'ثبت تراکنش جدید')

@section('content')
<div class="p-6 max-w-2xl" dir="rtl">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.finance-ledger.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900">ثبت تراکنش جدید</h1>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-5 text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5" x-data="ledgerForm()">

        <form method="POST" action="{{ route('admin.finance-ledger.store') }}" enctype="multipart/form-data">
            @csrf

            {{-- Type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">نوع تراکنش <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="income" x-model="type" class="sr-only peer" {{ old('type', 'income') === 'income' ? 'checked' : '' }}>
                        <div class="border-2 rounded-xl p-4 flex items-center gap-3 transition
                            peer-checked:border-emerald-500 peer-checked:bg-emerald-50 border-gray-200 hover:border-emerald-300">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                                </svg>
                            </div>
                            <span class="font-medium text-gray-800">درآمد</span>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="expense" x-model="type" class="sr-only peer" {{ old('type') === 'expense' ? 'checked' : '' }}>
                        <div class="border-2 rounded-xl p-4 flex items-center gap-3 transition
                            peer-checked:border-red-500 peer-checked:bg-red-50 border-gray-200 hover:border-red-300">
                            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                                </svg>
                            </div>
                            <span class="font-medium text-gray-800">هزینه</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Category --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">دسته‌بندی <span class="text-red-500">*</span></label>
                <select name="category" x-model="category"
                        class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
                    <option value="">— انتخاب کنید —</option>
                    <optgroup label="درآمدها" x-show="type === 'income'">
                        <option value="theme_sale">فروش قالب</option>
                        <option value="bundle_sale">فروش باندل قالب</option>
                        <option value="module_purchase">خرید ماژول</option>
                        <option value="hosting_renewal">تمدید هاستینگ</option>
                        <option value="subscription">اشتراک</option>
                        <option value="installment">اقساط</option>
                        <option value="support">پشتیبانی</option>
                        <option value="other_income">سایر درآمدها</option>
                    </optgroup>
                    <optgroup label="هزینه‌ها" x-show="type === 'expense'">
                        <option value="ai_cost">هزینه هوش مصنوعی (Claude / OpenAI)</option>
                        <option value="server_cost">هزینه سرور (VPS)</option>
                        <option value="arvancloud">ابر آروان (CDN / ذخیره‌سازی)</option>
                        <option value="domain">دامنه</option>
                        <option value="advertising">تبلیغات</option>
                        <option value="salary">حقوق</option>
                        <option value="software">نرم‌افزار / ابزار</option>
                        <option value="other_expense">سایر هزینه‌ها</option>
                    </optgroup>
                </select>
            </div>

            {{-- Amount --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ (ریال) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" value="{{ old('amount') }}" min="1"
                       class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400"
                       placeholder="مثال: 5000000">
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">شرح تراکنش</label>
                <input type="text" name="description" value="{{ old('description') }}"
                       class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400"
                       placeholder="مثال: پرداخت هزینه سرور — ماه خرداد">
            </div>

            {{-- Date --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">تاریخ تراکنش <span class="text-red-500">*</span></label>
                <x-jalali-datepicker name="entry_date" :value="old('entry_date', \App\Services\PersianFormatter::date(today(), 'Y/m/d'))" placeholder="۱۴۰۵/۰۱/۰۱" :required="true" extraClass="w-full rounded-lg border-gray-200 text-sm focus:ring-indigo-300 focus:border-indigo-400"/>
            </div>

            {{-- Optional fields --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">سایت (اختیاری)</label>
                    <select name="site_id" class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
                        <option value="">— بدون سایت —</option>
                        @foreach($sites as $s)
                        <option value="{{ $s->id }}" {{ old('site_id') == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">شماره فاکتور (اختیاری)</label>
                    <input type="text" name="invoice_number" value="{{ old('invoice_number') }}"
                           class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400"
                           placeholder="مثال: INV-2026-001">
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">یادداشت (اختیاری)</label>
                <textarea name="notes" rows="3"
                          class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400"
                          placeholder="توضیحات بیشتر...">{{ old('notes') }}</textarea>
            </div>

            {{-- Attachment --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">پیوست (فاکتور / رسید)</label>
                <input type="file" name="attachment"
                       class="w-full text-sm text-gray-500 file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            {{-- Submit --}}
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 bg-emerald-600 text-white text-sm rounded-lg px-4 py-2.5 hover:bg-emerald-700 transition font-medium">
                    ثبت تراکنش
                </button>
                <a href="{{ route('admin.finance-ledger.index') }}"
                   class="flex-1 bg-gray-100 text-gray-700 text-sm rounded-lg px-4 py-2.5 hover:bg-gray-200 transition font-medium text-center">
                    انصراف
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function ledgerForm() {
    return {
        type: '{{ old('type', 'income') }}',
        category: '{{ old('category') }}',
    };
}
</script>
@endsection
