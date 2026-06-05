@extends('layouts.admin')

@section('header', 'درگاه‌های پرداخت بانکی')

@section('content')
<a href="{{ route('admin.payment-gateways.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded">افزودن درگاه</a>
<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">نام</th>
                <th class="p-3 text-right">نوع</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">پیش‌فرض</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gateways as $gw)
                <tr class="border-t">
                    <td class="p-3"><strong>{{ $gw->name }}</strong></td>
                    <td class="p-3">{{ \App\Models\PaymentGateway::drivers()[$gw->driver] ?? $gw->driver }}</td>
                    <td class="p-3">{{ $gw->is_active ? 'فعال' : 'غیرفعال' }}</td>
                    <td class="p-3">{{ $gw->is_default ? 'بله' : '—' }}</td>
                    <td class="p-3">
                        <a href="{{ route('admin.payment-gateways.edit', $gw) }}" class="text-indigo-600 hover:underline">ویرایش</a>
                        @if(!$gw->invoices()->exists())
                            <form method="POST" action="{{ route('admin.payment-gateways.destroy', $gw) }}" class="inline">@csrf @method('DELETE')
                                <button type="submit" class="text-red-600" onclick="return confirm('حذف شود؟');">حذف</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-center text-gray-500">هنوز درگاهی تعریف نشده. از پنل یا .env استفاده می‌شود.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('admin.payment-gateways.guide')
@endsection
