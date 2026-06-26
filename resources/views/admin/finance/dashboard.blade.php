@extends('layouts.admin')

@section('title', 'داشبورد مالی')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">داشبورد مالی</h1>
            <p class="text-sm text-gray-500 mt-1">گزارش درآمد، هزینه و سود خالص پلتفرم</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.finance-ledger.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                دفتر کل
            </a>
            <a href="{{ route('admin.finance-ledger.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                ثبت هزینه
            </a>
        </div>
    </div>

    {{-- KPI Cards — Today --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @php
            $todayProfit = $kpis['today_income'] - $kpis['today_expense'];
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">درآمد امروز</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-emerald-700">@numFa($kpis['today_income'])</p>
            <p class="text-xs text-gray-400 mt-1">ریال</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">هزینه امروز</span>
                <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-red-600">@numFa($kpis['today_expense'])</p>
            <p class="text-xs text-gray-400 mt-1">ریال</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">سود خالص امروز</span>
                <div class="w-8 h-8 rounded-lg {{ $todayProfit >= 0 ? 'bg-blue-50' : 'bg-orange-50' }} flex items-center justify-center">
                    <svg class="w-4 h-4 {{ $todayProfit >= 0 ? 'text-blue-600' : 'text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold {{ $todayProfit >= 0 ? 'text-blue-700' : 'text-orange-600' }}">@numFa($todayProfit)</p>
            <p class="text-xs text-gray-400 mt-1">ریال</p>
        </div>
    </div>

    {{-- KPI Cards — Month --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-sm p-5 text-white">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-emerald-100 uppercase tracking-wide">درآمد این ماه</span>
                <svg class="w-5 h-5 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <p class="text-3xl font-bold">@numFa($kpis['month_income'])</p>
            <p class="text-xs text-emerald-200 mt-1">ریال</p>
        </div>
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-sm p-5 text-white">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-red-100 uppercase tracking-wide">هزینه این ماه</span>
                <svg class="w-5 h-5 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                </svg>
            </div>
            <p class="text-3xl font-bold">@numFa($kpis['month_expense'])</p>
            <p class="text-xs text-red-200 mt-1">ریال</p>
        </div>
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-xl shadow-sm p-5 text-white">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-indigo-100 uppercase tracking-wide">سود خالص این ماه</span>
                <svg class="w-5 h-5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <p class="text-3xl font-bold">@numFa($kpis['month_profit'])</p>
            <p class="text-xs text-indigo-200 mt-1">ریال</p>
        </div>
    </div>

    {{-- Year KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-500 mb-1">درآمد امسال</p>
            <p class="text-xl font-bold text-emerald-700">@numFa($kpis['year_income']) <span class="text-xs text-gray-400">ریال</span></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-500 mb-1">هزینه امسال</p>
            <p class="text-xl font-bold text-red-600">@numFa($kpis['year_expense']) <span class="text-xs text-gray-400">ریال</span></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-500 mb-1">سود خالص امسال</p>
            <p class="text-xl font-bold {{ $kpis['year_profit'] >= 0 ? 'text-indigo-700' : 'text-orange-600' }}">@numFa($kpis['year_profit']) <span class="text-xs text-gray-400">ریال</span></p>
        </div>
    </div>

    {{-- Monthly Trend Chart + Top Sources --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Monthly Trend --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                </svg>
                روند ماهانه (۶ ماه اخیر)
            </h3>
            <div class="space-y-3">
                @php $maxVal = collect($monthlyTrend)->max(fn($m) => max($m['income'], $m['expense'])) ?: 1; @endphp
                @foreach($monthlyTrend as $m)
                <div>
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ $m['label'] }}</span>
                        <span>درآمد: @numFa($m['income']) | هزینه: @numFa($m['expense'])</span>
                    </div>
                    <div class="flex gap-1 h-3">
                        <div class="bg-emerald-400 rounded-sm" style="width: {{ round($m['income'] / $maxVal * 100) }}%"></div>
                    </div>
                    <div class="flex gap-1 h-2 mt-0.5">
                        <div class="bg-red-300 rounded-sm" style="width: {{ round($m['expense'] / $maxVal * 100) }}%"></div>
                    </div>
                </div>
                @endforeach
                <div class="flex gap-4 text-xs text-gray-400 pt-2">
                    <span class="flex items-center gap-1"><span class="w-3 h-2 bg-emerald-400 rounded-sm inline-block"></span> درآمد</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-2 bg-red-300 rounded-sm inline-block"></span> هزینه</span>
                </div>
            </div>
        </div>

        {{-- Top Revenue Categories --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
                منابع درآمد (این ماه)
            </h3>
            @if(empty($topRevenue))
                <p class="text-sm text-gray-400 text-center py-4">هنوز داده‌ای ثبت نشده</p>
            @else
            <div class="space-y-3">
                @php $maxIncome = collect($topRevenue)->max('total') ?: 1; @endphp
                @foreach($topRevenue as $item)
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-700">{{ $item['label'] }}</span>
                        <span class="text-emerald-700 font-medium">@numFa($item['total'])</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                        <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ round($item['total'] / $maxIncome * 100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Top Sites + Top Expense Categories --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Top Sites --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                </svg>
                برترین سایت‌ها (این ماه)
            </h3>
            @if(empty($topSites))
                <p class="text-sm text-gray-400 text-center py-4">هنوز داده‌ای ثبت نشده</p>
            @else
            <div class="space-y-3">
                @foreach($topSites as $idx => $site)
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center shrink-0">{{ $idx + 1 }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $site['site'] }}</p>
                    </div>
                    <span class="text-sm font-bold text-emerald-700 shrink-0">@numFa($site['total'])</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Top Expense Categories --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                بزرگترین هزینه‌ها (این ماه)
            </h3>
            @if(empty($topExpense))
                <p class="text-sm text-gray-400 text-center py-4">هنوز هزینه‌ای ثبت نشده</p>
            @else
            <div class="space-y-3">
                @php $maxExp = collect($topExpense)->max('total') ?: 1; @endphp
                @foreach($topExpense as $item)
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-700">{{ $item['label'] }}</span>
                        <span class="text-red-600 font-medium">@numFa($item['total'])</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                        <div class="bg-red-400 h-1.5 rounded-full" style="width: {{ round($item['total'] / $maxExp * 100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Recent Transactions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                آخرین تراکنش‌ها
            </h3>
            <a href="{{ route('admin.finance-ledger.index') }}" class="text-xs text-indigo-600 hover:underline">مشاهده همه</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="px-4 py-3 text-right font-medium">تاریخ</th>
                        <th class="px-4 py-3 text-right font-medium">نوع</th>
                        <th class="px-4 py-3 text-right font-medium">دسته</th>
                        <th class="px-4 py-3 text-right font-medium">شرح</th>
                        <th class="px-4 py-3 text-right font-medium">مبلغ (ریال)</th>
                        <th class="px-4 py-3 text-right font-medium">منبع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($recent as $e)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">@dateFa($e->entry_date)</td>
                        <td class="px-4 py-3">
                            @if($e->isIncome())
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-700">درآمد</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">هزینه</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $e->categoryLabel() }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $e->description }}</td>
                        <td class="px-4 py-3 font-semibold {{ $e->isIncome() ? 'text-emerald-700' : 'text-red-600' }}">@numFa($e->amount)</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $e->isAutomatic() ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ $e->isAutomatic() ? 'خودکار' : 'دستی' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">هنوز تراکنشی ثبت نشده</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
