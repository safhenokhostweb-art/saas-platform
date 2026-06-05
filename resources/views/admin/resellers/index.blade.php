@extends('layouts.admin')

@section('header', 'درخواست‌های تسویه نمایندگان')

@section('content')
<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">نماینده</th>
                <th class="p-3 text-right">مبلغ (ریال)</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">تاریخ</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $req)
                <tr class="border-t">
                    <td class="p-3">{{ $req->reseller->company_name ?? $req->reseller->user->mobile ?? '—' }}</td>
                    <td class="p-3 font-medium">{{ \App\Helpers\NumberHelper::formatFa($req->amount_irr) }}</td>
                    <td class="p-3">{{ $req->status === 'pending' ? 'در انتظار' : ($req->status === 'approved' ? 'تأیید شده' : $req->status) }}</td>
                    <td class="p-3">{{ \App\Helpers\Jalali::format($req->created_at) }}</td>
                    <td class="p-3">
                        @if($req->status !== 'paid' && $req->status !== 'rejected')
                            <form method="post" action="{{ route('admin.payout-requests.mark-paid', $req) }}" class="inline">@csrf @method('PATCH')<button type="submit" class="text-green-600 hover:underline">تسویه شد</button></form>
                            <span class="text-gray-300 mx-1">|</span>
                            <form method="post" action="{{ route('admin.payout-requests.reject', $req) }}" class="inline" onsubmit="return confirm('رد شود؟');">@csrf @method('PATCH')<input type="text" name="admin_note" placeholder="دلیل رد" class="form-control text-sm w-32 inline-block"> <button type="submit" class="text-red-600 hover:underline">رد</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-center text-gray-500">درخواستی وجود ندارد.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $requests->links() }}
@endsection
