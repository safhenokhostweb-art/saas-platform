@extends('layouts.admin')

@section('header','مشتری‌ها')

@section('content')
@include('admin.customers.guide')
<a href="{{ route('admin.customers.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded">
    افزودن مشتری
</a>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
        <tr>
            <th class="p-3 text-right">نام / شرکت</th>
            <th class="p-3 text-right">موبایل</th>
            <th class="p-3 text-right">ایمیل</th>
            <th class="p-3 text-right">تعداد سایت‌ها</th>
            <th class="p-3 text-right">عملیات</th>
        </tr>
        </thead>
        <tbody>
        @foreach($customers as $customer)
            <tr class="border-t">
                <td class="p-3">
                    <span class="font-medium">{{ $customer->name }}</span>
                    @if($customer->company_name)
                        <span class="text-gray-500 block text-xs">{{ $customer->company_name }}</span>
                    @endif
                </td>
                <td class="p-3">{{ $customer->mobile }}</td>
                <td class="p-3">{{ $customer->email ?? '—' }}</td>
                <td class="p-3">
                    @php $count = $customer->sites()->count(); @endphp
                    @if($count > 0)
                        <a href="{{ route('admin.sites.index') }}?customer_id={{ $customer->id }}" class="text-indigo-600 hover:underline">{{ $count }} سایت</a>
                    @else
                        <span class="text-gray-400">۰</span>
                    @endif
                </td>
                <td class="p-3 space-x-2">
                    <a href="{{ route('admin.customers.edit',$customer) }}" class="text-indigo-600">ویرایش</a>
                    <form method="POST" action="{{ route('admin.customers.destroy',$customer) }}" class="inline">
                        @csrf @method('DELETE')
                        <button class="text-red-600" onclick="return confirm('مشتری و سایت‌هایش حذف شوند؟');">حذف</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
{{ $customers->links() }}
@endsection
