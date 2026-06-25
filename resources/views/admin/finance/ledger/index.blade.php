@extends('layouts.admin')

@section('title', 'دفتر کل مالی')

@section('content')
<div class="p-6 space-y-5" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">دفتر کل مالی</h1>
            <p class="text-sm text-gray-500">همه تراکنش‌های درآمد و هزینه</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.finance-ledger.export', request()->query()) }}"
               class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                خروجی CSV
            </a>
            <a href="{{ route('admin.finance-ledger.dashboard') }}"
               class="inline-flex items-center gap-2 px-3 py-2 bg-indigo-100 text-indigo-700 rounded-lg text-sm font-medium hover:bg-indigo-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                داشبورد
            </a>
            <a href="{{ route('admin.finance-ledger.create') }}"
               class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                ثبت تراکنش
            </a>
        </div>
    </div>

    {{-- Summary bar --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-emerald-50 border border-emerald-100 rounded-lg px-4 py-3 text-center">
            <p class="text-xs text-emerald-600 font-medium">مجموع درآمد (فیلترشده)</p>
            <p class="text-lg font-bold text-emerald-800 mt-1">{{ number_format($totalIncome) }} ریال</p>
        </div>
        <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-center">
            <p class="text-xs text-red-600 font-medium">مجموع هزینه (فیلترشده)</p>
            <p class="text-lg font-bold text-red-800 mt-1">{{ number_format($totalExpense) }} ریال</p>
        </div>
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-3 text-center">
            <p class="text-xs text-indigo-600 font-medium">سود خالص (فیلترشده)</p>
            @php $profit = $totalIncome - $totalExpense; @endphp
            <p class="text-lg font-bold {{ $profit >= 0 ? 'text-indigo-800' : 'text-orange-700' }} mt-1">{{ number_format($profit) }} ریال</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <form method="GET" action="{{ route('admin.finance-ledger.index') }}" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <select name="type" class="rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
                <option value="">همه انواع</option>
                <option value="income" {{ request('type') === 'income' ? 'selected' : '' }}>درآمد</option>
                <option value="expense" {{ request('type') === 'expense' ? 'selected' : '' }}>هزینه</option>
            </select>
            <select name="category" class="rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
                <option value="">همه دسته‌ها</option>
                @foreach($labels as $key => $label)
                <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="source" class="rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
                <option value="">همه منابع</option>
                <option value="automatic" {{ request('source') === 'automatic' ? 'selected' : '' }}>خودکار</option>
                <option value="manual" {{ request('source') === 'manual' ? 'selected' : '' }}>دستی</option>
            </select>
            <select name="site_id" class="rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400">
                <option value="">همه سایت‌ها</option>
                @foreach($sites as $site)
                <option value="{{ $site->id }}" {{ request('site_id') == $site->id ? 'selected' : '' }}>{{ $site->title }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400" placeholder="از تاریخ">
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400" placeholder="تا تاریخ">
            <input type="number" name="amount_min" value="{{ request('amount_min') }}"
                   class="rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400" placeholder="حداقل مبلغ">
            <input type="number" name="amount_max" value="{{ request('amount_max') }}"
                   class="rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400" placeholder="حداکثر مبلغ">
            <input type="text" name="search" value="{{ request('search') }}"
                   class="rounded-lg border-gray-200 text-sm px-3 py-2 focus:ring-indigo-300 focus:border-indigo-400 col-span-2" placeholder="جستجو در شرح، شماره فاکتور...">
            <div class="flex gap-2 col-span-2">
                <button type="submit" class="flex-1 bg-indigo-600 text-white text-sm rounded-lg px-3 py-2 hover:bg-indigo-700 transition font-medium">اعمال فیلتر</button>
                <a href="{{ route('admin.finance-ledger.index') }}" class="flex-1 bg-gray-100 text-gray-700 text-sm rounded-lg px-3 py-2 hover:bg-gray-200 transition font-medium text-center">پاک‌کردن</a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-right font-medium">تاریخ</th>
                    <th class="px-4 py-3 text-right font-medium">نوع</th>
                    <th class="px-4 py-3 text-right font-medium">دسته‌بندی</th>
                    <th class="px-4 py-3 text-right font-medium">شرح</th>
                    <th class="px-4 py-3 text-right font-medium">مبلغ (ریال)</th>
                    <th class="px-4 py-3 text-right font-medium">سایت</th>
                    <th class="px-4 py-3 text-right font-medium">درگاه</th>
                    <th class="px-4 py-3 text-right font-medium">منبع</th>
                    <th class="px-4 py-3 text-right font-medium">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($entries as $e)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">{{ $e->entry_date?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3">
                        @if($e->isIncome())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-700">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5"/></svg>
                                درآمد
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5"/></svg>
                                هزینه
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700 text-xs">{{ $e->categoryLabel() }}</td>
                    <td class="px-4 py-3 text-gray-600 max-w-xs">
                        <div class="truncate">{{ $e->description }}</div>
                        @if($e->invoice_number)
                        <div class="text-xs text-gray-400">فاکتور: {{ $e->invoice_number }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 font-bold {{ $e->isIncome() ? 'text-emerald-700' : 'text-red-600' }} whitespace-nowrap">{{ number_format($e->amount) }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $e->site?->title ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $e->gateway ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $e->isAutomatic() ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $e->isAutomatic() ? 'خودکار' : 'دستی' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.finance-ledger.edit', $e) }}"
                               class="text-indigo-500 hover:text-indigo-700" title="ویرایش">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.finance-ledger.destroy', $e) }}"
                                  onsubmit="return confirm('حذف این تراکنش؟')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600" title="حذف">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-10 text-center text-gray-400">
                        <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p>هیچ تراکنشی یافت نشد</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($entries->hasPages())
    <div class="flex justify-center">
        {{ $entries->links() }}
    </div>
    @endif

</div>
@endsection
