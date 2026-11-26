@extends('layouts.app')

@section('title', 'فروشگاه ماژول‌ها')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">فروشگاه ماژول‌ها</h1>
            <p class="text-gray-600 mt-1">ماژول مورد نظر را انتخاب و برای یکی از سایت‌های خود به سبد خرید اضافه کنید.</p>
        </div>
        <a href="{{ route('store.cart') }}" class="btn btn-primary inline-flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            سبد خرید
        </a>
    </div>

    @if($preselectedSiteId ?? null)
        @php $preselectedSite = auth()->user()->customer?->sites->firstWhere('id', $preselectedSiteId); @endphp
        @if($preselectedSite)
            <div class="alert alert-success mb-4">شما برای سایت «{{ \App\Helpers\NumberHelper::toPersianNumbers($preselectedSite->title) }}» خرید می‌کنید. سایت از قبل انتخاب شده است.</div>
        @endif
    @endif

    @if($products->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-gray-500">در حال حاضر ماژولی برای فروش در فروشگاه تعریف نشده است.</p>
            <p class="mt-2 text-sm text-gray-400">از پنل ادمین پلتفرم می‌توانید ماژول‌ها را به فروشگاه اضافه کنید.</p>
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($products as $product)
                @php
                    $module = $product->module;
                    $price = $product->price_irr;
                    if ($wholesaleDiscount > 0) {
                        $price = (int) round($price * (100 - $wholesaleDiscount) / 100);
                    }
                    $sites = auth()->user()->customer?->sites ?? collect();
                    $hasForAnySite = $product->module_id && collect($enabledModuleIdsBySite ?? [])->flatten()->unique()->contains($product->module_id);
                @endphp
                <div class="card flex flex-col justify-between overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="p-5 flex-1 flex flex-col">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <h3 class="font-bold text-lg text-gray-800">{{ \App\Helpers\NumberHelper::toPersianNumbers($product->name_fa) }}</h3>
                            @if($hasForAnySite)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800 shrink-0">خریداری‌شده</span>
                            @endif
                        </div>
                        @if($module?->short_description)
                            <p class="text-gray-600 text-sm mb-3 line-clamp-3">{{ \App\Helpers\NumberHelper::toPersianNumbers($module->short_description) }}</p>
                        @elseif($module?->description)
                            <p class="text-gray-600 text-sm mb-3 line-clamp-3">{{ \App\Helpers\NumberHelper::toPersianNumbers(Str::limit($module->description, 140)) }}</p>
                        @else
                            <p class="text-gray-500 text-sm mb-3">ماژول افزودنی برای سایت شما.</p>
                        @endif
                        <div class="mt-auto">
                            <div class="flex items-center gap-2 flex-wrap mb-3">

                                <span class="font-bold text-indigo-600 text-lg">{{ \App\Helpers\NumberHelper::formatFa($price) }}</span>
                                <span class="text-gray-500 text-sm">ریال</span>
                                @if($wholesaleDiscount > 0)

                                    <span class="text-gray-400 text-sm line-through">{{ \App\Helpers\NumberHelper::formatFa($product->price_irr) }}</span>
                                    <span class="text-green-600 text-xs">تخفیف {{ \App\Helpers\NumberHelper::toPersianNumbers($wholesaleDiscount) }}٪</span>
                                @endif
                            </div>
                            <form action="{{ route('store.cart.add') }}" method="post" class="space-y-2">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <select name="site_id" class="form-control text-sm w-full" required>
                                    <option value="">انتخاب سایت</option>
                                    @foreach($sites as $site)
                                        @php $siteHasModule = $product->module_id && in_array($product->module_id, $enabledModuleIdsBySite[$site->id] ?? [], true); @endphp
                                        <option value="{{ $site->id }}" {{ old('site_id', $preselectedSiteId ?? null) == $site->id ? 'selected' : '' }} {{ $siteHasModule ? 'disabled' : '' }}>
                                            {{ \App\Helpers\NumberHelper::toPersianNumbers($site->title) }}{{ $siteHasModule ? ' — خریداری‌شده' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary w-full text-sm py-2">
                                    افزودن به سبد خرید
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex flex-wrap gap-4 justify-center">
            <a href="{{ route('store.cart') }}" class="btn btn-primary">مشاهده سبد خرید</a>
            <a href="{{ route('store.index') }}" class="btn border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">همه محصولات فروشگاه</a>
        </div>
    @endif
</div>
@endsection
