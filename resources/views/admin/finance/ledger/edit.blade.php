@extends('layouts.admin')

@section('title', 'ویرایش تراکنش')

@section('content')
<div class="p-6 max-w-2xl" dir="rtl">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.finance-ledger.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900">ویرایش تراکنش #{{ $entry->id }}</h1>
        @if($entry->isAutomatic())
        <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">ثبت خودکار</span>
        @endif
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-5 text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5" x-data="{ type: '{{ old('type', $entry->type) }}' }">

        <form method="POST" action="{{ route('admin.finance-ledger.update', $entry) }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            {{-- Type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">نوع تراکنش</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="income" x-model="type" class="sr-only peer" {{ old('type', $entry->type) === 'income' ? 'checked' : '' }}>
                        <div class="border-2 rounded-xl p-4 flex items-center gap-3 transition peer-checked:border-emerald-500 peer-checked:bg-emerald-50 border-gray-200">
                            <span class="font-medium text-gray-800">درآمد</span>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="expense" x-model="type" class="sr-only peer" {{ old('type', $entry->type) === 'expense' ? 'checked' : '' }}>
                        <div class="border-2 rounded-xl p-4 flex items-center gap-3 transition peer-checked:border-red-500 peer-checked:bg-red-50 border-gray-200">
                            <span class="font-medium text-gray-800">هزینه</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Category --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">دسته‌بندی</label>
                <select name="category" class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
                    <optgroup label="درآمدها" x-show="type === 'income'">
                        @foreach(\App\Models\FinanceLedgerEntry::incomeCategories() as $cat)
                        <option value="{{ $cat }}" {{ old('category', $entry->category) === $cat ? 'selected' : '' }}>
                            {{ \App\Models\FinanceLedgerEntry::categoryLabels()[$cat] }}
                        </option>
                        @endforeach
                    </optgroup>
                    <optgroup label="هزینه‌ها" x-show="type === 'expense'">
                        @foreach(\App\Models\FinanceLedgerEntry::expenseCategories() as $cat)
                        <option value="{{ $cat }}" {{ old('category', $entry->category) === $cat ? 'selected' : '' }}>
                            {{ \App\Models\FinanceLedgerEntry::categoryLabels()[$cat] }}
                        </option>
                        @endforeach
                    </optgroup>
                </select>
            </div>

            {{-- Amount --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ (ریال)</label>
                <input type="number" name="amount" value="{{ old('amount', $entry->amount) }}" min="1"
                       class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">شرح</label>
                <input type="text" name="description" value="{{ old('description', $entry->description) }}"
                       class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>

            {{-- Date --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">تاریخ تراکنش</label>
                <input type="date" name="entry_date" value="{{ old('entry_date', $entry->entry_date?->format('Y-m-d')) }}"
                       class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">سایت</label>
                    <select name="site_id" class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
                        <option value="">— بدون سایت —</option>
                        @foreach($sites as $s)
                        <option value="{{ $s->id }}" {{ old('site_id', $entry->site_id) == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">شماره فاکتور</label>
                    <input type="text" name="invoice_number" value="{{ old('invoice_number', $entry->invoice_number) }}"
                           class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">یادداشت</label>
                <textarea name="notes" rows="3"
                          class="w-full rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">{{ old('notes', $entry->notes) }}</textarea>
            </div>

            {{-- Existing attachment --}}
            @if($entry->attachment_path)
            <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-600 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                پیوست موجود: {{ basename($entry->attachment_path) }}
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">پیوست جدید (اختیاری)</label>
                <input type="file" name="attachment"
                       class="w-full text-sm text-gray-500 file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 bg-indigo-600 text-white text-sm rounded-lg px-4 py-2.5 hover:bg-indigo-700 transition font-medium">
                    ذخیره تغییرات
                </button>
                <a href="{{ route('admin.finance-ledger.index') }}"
                   class="flex-1 bg-gray-100 text-gray-700 text-sm rounded-lg px-4 py-2.5 hover:bg-gray-200 transition font-medium text-center">
                    انصراف
                </a>
            </div>
        </form>
    </div>

    {{-- Metadata --}}
    @if($entry->isAutomatic())
    <div class="mt-4 bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-700">
        <p class="font-medium mb-1">اطلاعات منبع خودکار</p>
        <p>جدول: <code>{{ $entry->reference_type }}</code> — شناسه: <code>{{ $entry->reference_id }}</code></p>
        @if($entry->gateway_ref)
        <p class="mt-1">شماره پیگیری درگاه: <code>{{ $entry->gateway_ref }}</code></p>
        @endif
    </div>
    @endif
</div>
@endsection
