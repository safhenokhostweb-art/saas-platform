@extends('layouts.app')

@section('title', 'سبد خرید')

@section('content')
<div class="max-w-4xl mx-auto">
    <h2 class="text-xl font-bold mb-4">سبد خرید</h2>

    @if(empty($items))
        <p class="text-gray-500">سبد خرید خالی است.</p>
        <p class="mt-2"><a href="{{ route('store.index') }}" class="btn btn-primary">بازگشت به فروشگاه</a></p>
    @else
    <form action="{{ route('checkout.process') }}" method="post" class="card p-4">
        @csrf
        <table class="w-full text-sm mb-4">
            <thead><tr class="border-b"><th class="p-2 text-right">محصول</th><th class="p-2 text-right">سایت</th><th class="p-2 text-right">قیمت</th><th></th></tr></thead>
            <tbody>
                @foreach($items as $row)
                    <tr class="border-b">
                        <td class="p-2">{{ \App\Helpers\NumberHelper::toPersianNumbers($row['product']->name_fa) }}</td>
                        <td class="p-2">
                            @if($row['product']->type === \App\Models\Product::TYPE_LICENSE_TEMPLATE)
                                <input type="text" name="new_site_title" class="form-control" placeholder="عنوان سایت جدید" value="{{ old('new_site_title') }}">
                                <input type="text" name="new_site_domain" class="form-control mt-1" placeholder="دامنه (اختیاری)" value="{{ old('new_site_domain') }}">
                            @else
                                <select name="site_id[{{ $row['key'] }}]" class="form-control">
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}" {{ $row['site_id'] == $site->id ? 'selected' : '' }}>{{ \App\Helpers\NumberHelper::toPersianNumbers($site->title) }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td class="p-2">@numFa($row['line_total']) ریال</td>
                        <td class="p-2">
                            <a href="{{ route('store.cart.remove', $row['key']) }}" onclick="return confirm('حذف شود؟');" class="text-red-600">حذف</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="font-bold">جمع: @numFa($total) ریال</p>
        <button type="submit" class="btn btn-primary mt-4">پرداخت و انتقال به درگاه</button>
    </form>

    <p class="mt-4"><a href="{{ route('store.index') }}" class="text-indigo-600">ادامه خرید</a></p>
    @endif
</div>
@endsection
