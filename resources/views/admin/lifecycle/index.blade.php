@extends('layouts.admin')

@section('title', 'چرخه عمر — ' . $site->title)

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('admin.sites.index') }}" class="hover:text-indigo-600">سایت‌ها</a>
        <span>/</span>
        <a href="{{ route('admin.sites.show', $site) }}" class="hover:text-indigo-600">{{ $site->title }}</a>
        <span>/</span>
        <span class="text-gray-800 font-medium">چرخه عمر</span>
    </div>

    {{-- Site Status Card --}}
    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ $site->title }}</h1>
                <div class="text-sm text-gray-500 mt-0.5">{{ $site->domain }}</div>
                <div class="mt-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold {{ $site->lifecycleStatusBadgeClass() }}">
                        {{ $site->lifecycleStatusLabel() }}
                    </span>
                </div>
            </div>
            <div class="text-left space-y-1 text-xs text-gray-500">
                @if($site->grace_period_ends_at)
                <div>پایان دوره اعتبار: <span class="font-medium text-yellow-700">{{ \Morilog\Jalali\CalendarUtils::strftime('Y/m/d', $site->grace_period_ends_at) }}</span></div>
                @endif
                @if($site->suspended_at)
                <div>تاریخ تعلیق: <span class="font-medium text-red-700">{{ \Morilog\Jalali\CalendarUtils::strftime('Y/m/d', $site->suspended_at) }}</span></div>
                @endif
                @if($site->archived_at)
                <div>تاریخ آرشیو: <span class="font-medium text-gray-700">{{ \Morilog\Jalali\CalendarUtils::strftime('Y/m/d', $site->archived_at) }}</span></div>
                {{-- GAP-L05: Days until permanent deletion (365 days after archive) --}}
                @php
                    $deletionDate = $site->archived_at->addDays(365);
                    $daysLeft = (int) now()->diffInDays($deletionDate, false);
                @endphp
                @if($daysLeft > 0)
                <div class="mt-1">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $daysLeft <= 30 ? 'bg-red-100 text-red-700' : ($daysLeft <= 90 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $daysLeft }} روز تا حذف دائمی
                    </span>
                </div>
                @else
                <div class="mt-1">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        آماده حذف (تاریخ گذشته)
                    </span>
                </div>
                @endif
                @endif
                @if($site->lifecycle_changed_at)
                <div>آخرین تغییر: {{ \Morilog\Jalali\CalendarUtils::strftime('Y/m/d H:i', $site->lifecycle_changed_at) }}</div>
                @endif
            </div>
        </div>

        @if($site->suspended_reason)
        <div class="mt-4 p-3 bg-red-50 border border-red-100 rounded-lg text-sm text-red-700">
            <span class="font-medium">دلیل تعلیق:</span> {{ $site->suspended_reason }}
        </div>
        @endif
    </div>

    {{-- Pending Invoices --}}
    @foreach($invoices->where('status', 'pending') as $invoice)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-bold text-amber-800">فاکتور پرداخت‌نشده</h3>
                <p class="text-sm text-amber-700 mt-1">{{ $invoice->invoice_number }} — {{ $invoice->typeLabel() }}</p>
                <p class="text-lg font-bold text-amber-900 mt-1">
                    {{ number_format($invoice->amount) }} ریال
                </p>
            </div>
            <form method="POST" action="{{ route('admin.sites.lifecycle.invoice-paid', [$site, $invoice]) }}"
                  class="flex gap-2 items-end" x-data="{ ref: '' }">
                @csrf
                <input x-model="ref" name="payment_reference" type="text"
                       placeholder="شناسه پرداخت"
                       class="border border-amber-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400"/>
                <button type="submit"
                        class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700 transition">
                    ثبت پرداخت
                </button>
            </form>
        </div>
    </div>
    @endforeach

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Force Transition --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm" x-data="{ open: false }">
            <h2 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                تغییر دستی وضعیت
            </h2>

            <button @click="open = !open"
                    class="w-full px-4 py-2 border-2 border-dashed border-gray-300 text-gray-600 rounded-lg text-sm hover:border-indigo-400 hover:text-indigo-600 transition">
                تغییر وضعیت سایت...
            </button>

            <div x-show="open" x-transition class="mt-4">
                <form method="POST" action="{{ route('admin.sites.lifecycle.transition', $site) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت جدید</label>
                        <select name="to_status" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            @foreach(\App\Models\Site::LIFECYCLE_STATES as $state)
                            @if($state !== $site->getLifecycleStatus())
                            <option value="{{ $state }}">{{ \App\Models\SiteLifecycleInvoice::statusLabel($state) }}</option>
                            @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">دلیل تغییر</label>
                        <input name="reason" type="text" required maxlength="500"
                               placeholder="دلیل تغییر وضعیت..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                    </div>
                    <button type="submit"
                            class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition"
                            onclick="return confirm('آیا از تغییر وضعیت اطمینان دارید؟')">
                        اعمال تغییر
                    </button>
                </form>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <h2 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                عملیات سریع
            </h2>
            <div class="space-y-2">

                @if($site->isArchived())
                <form method="POST" action="{{ route('admin.sites.lifecycle.restore', $site) }}">
                    @csrf
                    <button type="submit"
                            onclick="return confirm('آیا از بازگردانی این سایت اطمینان دارید؟')"
                            class="w-full px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        بازگردانی سایت (Force Restore)
                    </button>
                </form>
                @endif

                @if($site->archive_path)
                <div class="text-xs text-gray-500 bg-gray-50 rounded-lg p-3">
                    <span class="font-medium">مسیر آرشیو:</span>
                    <code class="mr-1">{{ $site->archive_path }}</code>
                </div>
                @endif

                @if(! $site->isLifecycleActive() && ! $site->isArchived())
                <div class="text-xs text-gray-500 italic p-2">
                    برای بازگردانی سایت، ابتدا وضعیت را از طریق «تغییر دستی وضعیت» به Active تغییر دهید.
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Invoice History --}}
    @if($invoices->isNotEmpty())
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 font-bold text-gray-800">تاریخچه فاکتورها</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                <tr>
                    <th class="px-4 py-2 text-right">شماره</th>
                    <th class="px-4 py-2 text-right">نوع</th>
                    <th class="px-4 py-2 text-right">مبلغ</th>
                    <th class="px-4 py-2 text-right">وضعیت</th>
                    <th class="px-4 py-2 text-right">تاریخ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($invoices as $invoice)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $invoice->invoice_number }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $invoice->typeLabel() }}</td>
                    <td class="px-4 py-3 font-medium">{{ number_format($invoice->amount) }} ریال</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-700' : ($invoice->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ match($invoice->status) { 'paid' => 'پرداخت‌شده', 'pending' => 'در انتظار', default => 'لغو‌شده' } }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ \Morilog\Jalali\CalendarUtils::strftime('Y/m/d', $invoice->created_at) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Lifecycle Log --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 font-bold text-gray-800">تاریخچه تغییرات</div>
        @if($logs->isEmpty())
        <div class="p-8 text-center text-gray-400 text-sm">هیچ تغییری ثبت نشده است.</div>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($logs as $log)
            <div class="px-6 py-4 flex items-start gap-4">
                <div class="mt-0.5">
                    <span class="inline-block w-2 h-2 rounded-full {{ match($log->to_status) {
                        'active' => 'bg-green-500',
                        'grace_period' => 'bg-yellow-500',
                        'suspended' => 'bg-red-500',
                        'archived' => 'bg-gray-400',
                        'deleted' => 'bg-black',
                        default => 'bg-gray-300'
                    } }}"></span>
                </div>
                <div class="flex-1">
                    <div class="text-sm font-medium text-gray-900">
                        @if($log->from_status)
                        <span class="text-gray-500">{{ \App\Models\SiteLifecycleInvoice::statusLabel($log->from_status) }}</span>
                        <span class="mx-1">←</span>
                        @endif
                        {{ \App\Models\SiteLifecycleInvoice::statusLabel($log->to_status) }}
                    </div>
                    @if($log->reason)
                    <div class="text-xs text-gray-500 mt-0.5">{{ $log->reason }}</div>
                    @endif
                    <div class="text-xs text-gray-400 mt-1 flex items-center gap-2">
                        <span>{{ \Morilog\Jalali\CalendarUtils::strftime('Y/m/d H:i', $log->created_at) }}</span>
                        @if($log->is_system)
                        <span class="px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-xs">سیستمی</span>
                        @else
                        <span class="px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded text-xs">دستی توسط ادمین</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        {{ $logs->links() }}
        @endif
    </div>

</div>
@endsection
