@extends('layouts.app')

@section('title', 'فروشگاه')

@section('content')
<div class="max-w-4xl mx-auto">
    <h2 class="text-xl font-bold mb-4">خرید لایسنس و ماژول</h2>
    @if($preselectedSiteId ?? null)
        @php $preselectedSite = auth()->user()->customer?->sites->firstWhere('id', $preselectedSiteId); @endphp
        @if($preselectedSite)
            <div class="alert alert-success mb-4">شما برای سایت «{{ \App\Helpers\NumberHelper::toPersianNumbers($preselectedSite->title) }}» پشتیبانی یا ماژول خریداری می‌کنید. سایت از قبل انتخاب شده است.</div>
        @endif
    @endif

    <div class="grid gap-4">
        @forelse($products as $product)
            <div class="card p-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <strong>{{ \App\Helpers\NumberHelper::toPersianNumbers($product->name_fa) }}</strong>
                    <span class="text-gray-500 text-sm block">{{ $product->typeLabel() }}</span>
                    @if($product->type === \App\Models\Product::TYPE_MODULE_ADDON && $product->module)
                        <span class="text-gray-600 text-sm block mt-1">{{ \App\Helpers\NumberHelper::toPersianNumbers($product->module->title) }}</span>
                        @if($product->module->description)
                            <p class="text-gray-500 text-xs mt-1">{{ \App\Helpers\NumberHelper::toPersianNumbers(Str::limit($product->module->description, 120)) }}</p>
                        @endif
                    @endif
                    @if($product->type === \App\Models\Product::TYPE_SUPPORT_PLAN && $product->supportPlan)
                        <span class="text-gray-600 text-sm block mt-1">{{ \App\Helpers\NumberHelper::toPersianNumbers($product->supportPlan->title ?? 'پشتیبانی') }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @php
                        $price = $product->price_irr;
                        if ($wholesaleDiscount > 0) {
                            $price = (int) round($price * (100 - $wholesaleDiscount) / 100);
                        }
                    @endphp
                    <span class="font-bold">{{ \App\Helpers\NumberHelper::formatFa($price)) }} ریال</span>
                    @if($wholesaleDiscount > 0)
                        <span class="text-gray-500 text-sm line-through">{{ \App\Helpers\NumberHelper::formatFa($product->price_irr)) }}</span>
                        <span class="text-green-600 text-xs">تخفیف عمده {{ \App\Helpers\NumberHelper::toPersianNumbers($wholesaleDiscount) }}%</span>
                    @endif
                    @php
                        $enabledModuleIdsBySite = $enabledModuleIdsBySite ?? [];
                    @endphp
                    <form action="{{ route('store.cart.add') }}" method="post" class="inline">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        @if(in_array($product->type, [\App\Models\Product::TYPE_MODULE_ADDON, \App\Models\Product::TYPE_SUPPORT_PLAN]))
                            <select name="site_id" class="form-control inline-block w-auto" required>
                                <option value="">انتخاب سایت</option>
                                @foreach(auth()->user()->customer?->sites ?? [] as $site)
                                    @php $siteHasModule = $product->module_id && in_array($product->module_id, $enabledModuleIdsBySite[$site->id] ?? [], true); @endphp
                                    <option value="{{ $site->id }}" {{ old('site_id', $preselectedSiteId ?? null) == $site->id ? 'selected' : '' }}>
                                        {{ \App\Helpers\NumberHelper::toPersianNumbers($site->title) }} ({{ $site->domain ?: '—' }}){{ $siteHasModule ? ' — خریداری‌شده' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @if($product->module_id && collect($enabledModuleIdsBySite)->flatten()->unique()->contains($product->module_id))
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 mr-1">برای حداقل یک سایت دارید</span>
                            @endif
                        @endif
                        <button type="submit" class="btn btn-primary">افزودن به سبد</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-gray-500">محصولی برای فروش تعریف نشده است.</p>
        @endforelse
    </div>

    <p class="mt-4"><a href="{{ route('store.cart') }}" class="btn btn-primary">مشاهده سبد خرید</a></p>
</div>
@endsection
