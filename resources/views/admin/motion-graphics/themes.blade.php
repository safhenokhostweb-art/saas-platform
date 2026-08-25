@extends('layouts.admin')

@section('title', 'اختصاص بسته موشن به تم فروشگاهی')
@section('header', 'اختصاص بسته موشن به تم فروشگاهی')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    @include('admin.motion-graphics.partials.site-nav', ['site' => $site, 'active' => 'themes'])

    <p class="text-sm text-gray-500">
        این صفحه معادل «اختصاص قالب» است، با این تفاوت که به یک تم کامل فروشگاهی (Theme Builder → Marketplace)
        وصل می‌شود، نه یک ThemeTemplate تکی. سرویس و منطق مجوز/فعال‌سازی زیرین کاملاً یکسان و بازاستفاده‌شده است.
    </p>

    @if($error)
        <div class="alert alert-warning">
            این قابلیت برای سایت #{{ $site->id }} فعال نیست ({{ $error->failureCode }}).
            برای فعال‌سازی: <code dir="ltr">php artisan motion:internal-test-site {{ $site->id }}</code>
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <h2 class="text-base font-bold text-gray-900 mb-4">تم‌های فروشگاهی ({{ $themes->count() }})</h2>
                @if($themes->isEmpty())
                    <p class="text-sm text-gray-400">هیچ تم فروشگاهی یافت نشد.</p>
                @else
                    <div class="space-y-2 max-h-[32rem] overflow-y-auto">
                        @foreach($themes as $theme)
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-800">{{ $theme->name }}</div>
                                        <div class="text-xs text-gray-400">{{ $theme->category }} · {{ number_format((int) $theme->marketplace_price) }} تومان</div>
                                        @if(!empty($theme->settings['motion']['package_reference'] ?? null))
                                            <div class="text-xs text-emerald-600 mt-1">✓ بسته موشن اختصاص‌یافته: {{ $theme->settings['motion']['package_reference'] }}</div>
                                        @endif
                                    </div>
                                </div>
                                <details>
                                    <summary class="text-xs text-indigo-600 cursor-pointer">اختصاص بسته موشن به این تم</summary>
                                    <form method="post" action="{{ route('admin.motion-graphics.themes.assign', [$site, $theme]) }}" class="mt-3 space-y-2">
                                        @csrf
                                        @include('admin.motion-graphics.partials.assignment-fields')
                                        <div class="flex gap-2">
                                            <button type="submit" class="btn btn-sm btn-primary">اختصاص بسته به تم</button>
                                            <button type="submit" formaction="{{ route('admin.motion-graphics.themes.activate', [$site, $theme]) }}" class="btn btn-sm btn-success">فعال‌سازی برای این سایت</button>
                                        </div>
                                    </form>
                                </details>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
