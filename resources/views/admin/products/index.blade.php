@extends('layouts.admin')

@section('header', 'محصولات فروشگاه پلتفرم')

@section('content')
<x-module-features module='products' />
<p class='mb-4'>
    <a href="{{ route('admin.products.create') }}" class='btn btn-primary'>افزودن محصول جدید</a>
</p>

<div class='overflow-x-auto'>
    <table class='w-full text-sm text-right border-collapse'>
        <thead>
            <tr class='bg-gray-100 text-gray-700'>
                <th class='px-4 py-2 border border-gray-200'>SKU</th>
                <th class='px-4 py-2 border border-gray-200'>نام محصول</th>
                <th class='px-4 py-2 border border-gray-200'>نوع</th>
                <th class='px-4 py-2 border border-gray-200'>قیمت (ریال)</th>
                <th class='px-4 py-2 border border-gray-200'>وضعیت</th>
                <th class='px-4 py-2 border border-gray-200'>عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
            <tr class='hover:bg-gray-50'>
                <td class='px-4 py-2 border border-gray-200 font-mono text-xs'>{{ $product->sku }}</td>
                <td class='px-4 py-2 border border-gray-200'>{{ $product->name_fa }}</td>
                <td class='px-4 py-2 border border-gray-200'>
                    @if($product->type === 'license_template') لایسنس قالب
                    @elseif($product->type === 'module_addon') ماژول افزودنی
                    @elseif($product->type === 'support_plan') پلن پشتیبانی
                    @else {{ $product->type }}
                    @endif
                </td>
                <td class='px-4 py-2 border border-gray-200'>@numFa($product->price_irr)</td>
                <td class='px-4 py-2 border border-gray-200'>
                    @if($product->is_active)
                        <span class='text-green-600 font-medium'>فعال</span>
                    @else
                        <span class='text-red-500'>غیرفعال</span>
                    @endif
                </td>
                <td class='px-4 py-2 border border-gray-200 space-x-2 space-x-reverse'>
                    <a href="{{ route('admin.products.edit', $product) }}" class='text-indigo-600 hover:underline text-xs'>ویرایش</a>
                    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class='inline'
                          onsubmit="return confirm('حذف این محصول؟')">
                        @csrf @method('DELETE')
                        <button type='submit' class='text-red-500 hover:underline text-xs'>حذف</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan='6' class='px-4 py-4 text-center text-gray-500 border border-gray-200'>هیچ محصولی یافت نشد.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.products.guide')
@endsection
