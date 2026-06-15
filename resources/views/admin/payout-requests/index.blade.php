@extends('layouts.admin')

@section('header', 'درخواست‌های تسویه نمایندگان')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">درخواست‌های تسویه حساب</h1>
    <a href="{{ route('admin.payout-requests.index') }}" class="text-sm text-indigo-600 hover:underline">بروزرسانی</a>
</div>


<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-3 text-right text-gray-600 font-medium">نماینده</th>
                <th class="p-3 text-right text-gray-600 font-medium">مبلغ (ریال)</th>
                <th class="p-3 text-right text-gray-600 font-medium">وضعیت</th>
                <th class="p-3 text-right text-gray-600 font-medium">تاریخ ثبت</th>
                <th class="p-3 text-right text-gray-600 font-medium">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($requests as $req)
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-3 font-medium text-gray-800">{{ $req->reseller->company_name ?? $req->reseller->user->mobile ?? '—' }}</td>
                    <td class="p-3 font-medium text-gray-700">{{ \App\Helpers\NumberHelper::formatFa($req->amount_irr) }}</td>
                    <td class="p-3">
                        @if($req->status === 'pending')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">در انتظار</span>
                        @elseif($req->status === 'approved')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">تأیید شده</span>
                        @elseif($req->status === 'paid')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">پرداخت شده</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">{{ $req->status }}</span>
                        @endif
                    </td>
                    <td class="p-3 text-gray-500">{{ \App\Helpers\Jalali::format($req->created_at) }}</td>
                    <td class="p-3">
                        @if(!in_array($req->status, ['paid', 'rejected']))
                            <div class="flex items-center gap-3">
                                <form method="post" action="{{ route('admin.payout-requests.mark-paid', $req) }}" class="inline" onsubmit="return confirm('تسویه انجام شود؟');">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-green-600 hover:text-green-800 text-xs font-medium">تسویه شد</button>
                                </form>
                                <span class="text-gray-400">|</span>
                                <form method="post" action="{{ route('admin.payout-requests.reject', $req) }}" class="inline flex items-center gap-1" onsubmit="return confirm('رد شود؟');">
                                    @csrf @method('PATCH')
                                    <input type="text" name="admin_note" placeholder="دلیل رد" class="border border-gray-200 rounded px-2 py-1 text-xs w-32 focus:outline-none focus:ring-1 focus:ring-red-300">
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">رد</button>
                                </form>
                            </div>
                        @else
                            <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-8 text-center text-gray-400">
                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        درخواست تسویه‌ای وجود ندارد.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($requests->hasPages())
    <div class="mt-4">{{ $requests->links() }}</div>
@endif
@endsection
