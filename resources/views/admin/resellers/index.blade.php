@extends('layouts.admin')

@section('header', 'نمایندگان (Reseller)')

@section('content')
@include('admin.resellers.guide')
<a href="{{ route('admin.resellers.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded">افزودن نماینده</a>
@if(session('success'))<div class="alert alert-success mt-2">{{ session('success') }}</div>@endif

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">کاربر / شرکت</th>
                <th class="p-3 text-right">درصد کمیسیون</th>
                <th class="p-3 text-right">تعداد مشتریان</th>
                <th class="p-3 text-right">کمیسیون‌ها</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resellers as $r)
                <tr class="border-t">
                    <td class="p-3">
                        <span class="font-medium">{{ $r->company_name ?: ($r->user->mobile ?? '—') }}</span>
                        @if($r->company_name && $r->user)
                            <span class="block text-gray-500 text-xs">{{ $r->user->mobile }}</span>
                        @endif
                    </td>
                    <td class="p-3">{{ number_format((float) $r->commission_rate, 1) }}٪</td>
                    <td class="p-3">{{ $r->customers->count() }}</td>
                    <td class="p-3">
                        @php $pending = $r->commissions->where('status', 'pending')->sum('amount_irr'); @endphp
                        @if($pending > 0)
                            <span class="text-amber-600">{{ number_format($pending) }} ریال (در انتظار)</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="p-3 space-x-2">
                        <a href="{{ route('admin.resellers.edit', $r) }}" class="text-indigo-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('admin.resellers.destroy', $r) }}" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600" onclick="return confirm('نماینده حذف شود؟ مشتریانش بدون نماینده می‌مانند.');">حذف</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
{{ $resellers->links() }}
@endsection
