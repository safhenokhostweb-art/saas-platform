@extends('layouts.app')

@section('title', 'داشبورد نمایندگی')

@section('content')
<div class="max-w-5xl mx-auto">
    <h2 class="text-xl font-bold mb-4">داشبورد نمایندگی — {{ $reseller->company_name ?: $reseller->user->mobile }}</h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="card p-4">
            <div class="text-gray-500 text-sm">جمع فروش مشتریان</div>
            <div class="text-xl font-bold mt-1">{{ number_format($totalSales) }} ریال</div>
        </div>
        <div class="card p-4">
            <div class="text-gray-500 text-sm">کمیسیون در انتظار</div>
            <div class="text-xl font-bold text-amber-600 mt-1">{{ number_format($totalPending) }} ریال</div>
        </div>
        <div class="card p-4">
            <div class="text-gray-500 text-sm">کمیسیون تسویه‌شده</div>
            <div class="text-xl font-bold text-green-600 mt-1">{{ number_format($totalPaid) }} ریال</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="card p-4">
            <h3 class="font-bold mb-3">مشتریان ({{ $reseller->customers->count() }})</h3>
            @forelse($reseller->customers as $c)
                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                    <span>{{ $c->company_name ?: $c->name }}</span>
                    <span class="text-gray-500 text-sm">{{ $c->sites->count() }} سایت</span>
                </div>
            @empty
                <p class="text-gray-500 text-sm">هنوز مشتریی تخصیص داده نشده است.</p>
            @endforelse
        </div>

        <div class="card p-4">
            <h3 class="font-bold mb-3">آخرین فاکتورهای پرداخت‌شده</h3>
            @forelse($invoices as $inv)
                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                    <span class="text-sm">{{ $inv->customer->name ?? $inv->customer->company_name ?? '—' }}</span>
                    <span class="font-medium">{{ number_format($inv->amount_irr) }} ریال</span>
                </div>
            @empty
                <p class="text-gray-500 text-sm">فاکتور پرداخت‌شده‌ای وجود ندارد.</p>
            @endforelse
        </div>
    </div>

    @if($totalPending > 0)
    <div class="card p-4 mt-6">
        <form method="post" action="{{ route('reseller.payout-request.store') }}" class="mb-4">
            @csrf
            <button type="submit" class="btn btn-primary">درخواست تسویه ({{ number_format($totalPending) }} ریال)</button>
        </form>
    </div>
    @endif

    <div class="card p-4 mt-6">
        <h3 class="font-bold mb-3">کمیسیون‌های در انتظار</h3>
        @forelse($commissionsPending as $com)
            <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                <span class="text-sm">فاکتور #{{ $com->invoice_id }}</span>
                <span class="font-medium text-amber-600">{{ number_format($com->amount_irr) }} ریال</span>
            </div>
        @empty
            <p class="text-gray-500 text-sm">کمیسیون در انتظاری وجود ندارد.</p>
        @endforelse
    </div>
</div>
@endsection
