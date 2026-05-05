@extends('layouts.admin')

@section('header', 'محصولات فروشگاه')

@section('content')
<a href="{{ route('admin.products.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded">افزودن محصول</a>
@if(session('success'))<div class="alert alert-success mt-2">{{ session('success') }}</div>@endif

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">SKU / نام</th>
                <th class="p-3 text-right">نوع</th>
                <th class="p-3 text-right">قیمت (ریال)</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
                <tr class="border-t">
                    <td class="p-3"><strong>{{ $product->sku }}</strong><span class="block text-gray-500">{{ $product->name_fa }}</span></td>
                    <td class="p-3">{{ $product->typeLabel() }}</td>
                    <td class="p-3">{{ number_format($product->price_irr) }}</td>
                    <td class="p-3">{{ $product->is_active ? 'فعال' : 'غیرفعال' }}</td>
                    <td class="p-3">
                        <a href="{{ route('admin.products.edit', $product) }}" class="text-indigo-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline">@csrf @method('DELETE')
                            <button type="submit" class="text-red-600" onclick="return confirm('حذف شود؟');">حذف</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@include('admin.products.guide')
@endsection
