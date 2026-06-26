@extends('layouts.admin')

@section('title', 'سفارش‌های باندل')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.theme-bundles.index') }}"
           class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">سفارش‌های باندل</h1>
            <p class="text-sm text-gray-500">تاریخچه خرید باندل‌ها توسط سایت‌ها</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if($orders->isEmpty())
            <div class="p-16 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p>هنوز سفارشی ثبت نشده.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-right text-gray-500 text-xs font-medium">
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">باندل</th>
                            <th class="px-5 py-3">سایت</th>
                            <th class="px-5 py-3">مبلغ</th>
                            <th class="px-5 py-3">درگاه</th>
                            <th class="px-5 py-3">وضعیت</th>
                            <th class="px-5 py-3">تاریخ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($orders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-mono text-gray-400 text-xs">{{ $order->id }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-800">{{ $order->bundle?->name ?? '—' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-gray-600">{{ $order->site?->domain ?? "Site #{$order->site_id}" }}</span>
                                </td>
                                <td class="px-5 py-3 font-medium text-gray-800" dir="ltr">
                                    @numFa($order->amount_rial) ریال
                                </td>
                                <td class="px-5 py-3 text-gray-500 font-mono text-xs">
                                    {{ $order->gateway ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    @if($order->status === 'paid')
                                        <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>پرداخت شده
                                        </span>
                                    @elseif($order->status === 'pending')
                                        <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>در انتظار
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 bg-rose-100 text-rose-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>ناموفق
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-400 text-xs">
                                    {{ $order->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-4 border-t border-gray-100">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
