@extends('layouts.admin')

@section('header', 'تسویه کمیسیون نمایندگان')

@section('content')
@include('admin.reseller-commissions.guide')
<div class="card p-4 mb-4">
    <strong>جمع کمیسیون در انتظار:</strong> <span class="text-amber-600 font-bold">{{ \App\Helpers\NumberHelper::formatFa($totalPending) }} ریال</span>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">نماینده</th>
                <th class="p-3 text-right">فاکتور</th>
                <th class="p-3 text-right">مبلغ (ریال)</th>
                <th class="p-3 text-right">تاریخ</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pending as $c)
                <tr class="border-t">
                    <td class="p-3">{{ $c->reseller->company_name ?: $c->reseller->user->mobile ?? '—' }}</td>
                    <td class="p-3">#{{ $c->invoice_id }}</td>
                    <td class="p-3 font-medium">{{ \App\Helpers\NumberHelper::formatFa($c->amount_irr) }}</td>
                    <td class="p-3 text-gray-500">{{ $c->created_at->format('Y/m/d') }}</td>
                    <td class="p-3">
                        <form method="POST" action="{{ route('admin.reseller-commissions.mark-paid', $c) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-green-600 hover:underline">تسویه</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-center text-gray-500">کمیسیون در انتظاری وجود ندارد.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $pending->links() }}
@endsection
