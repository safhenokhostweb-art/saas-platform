@extends('layouts.admin')

@section('title', 'پیش‌نمایش اجرای موشن گرافیک')
@section('header', 'پیش‌نمایش اجرای موشن گرافیک')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    @include('admin.motion-graphics.partials.site-nav', ['site' => $site, 'active' => 'preview'])

    @if($error)
        <div class="alert alert-warning">
            این قابلیت برای سایت #{{ $site->id }} فعال نیست ({{ $error->failureCode }}).
            برای فعال‌سازی: <code dir="ltr">php artisan motion:internal-test-site {{ $site->id }}</code>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="card lg:col-span-1">
                <div class="card-body">
                    <h2 class="text-base font-bold text-gray-900 mb-4">بسته‌های فعال این سایت</h2>
                    @if($packages->isEmpty())
                        <p class="text-sm text-gray-400">هنوز هیچ بسته‌ای برای این سایت فعال نشده است.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($packages as $package)
                                <a href="{{ route('admin.motion-graphics.preview.show', [$site, $package]) }}"
                                   class="block border rounded-lg p-3 text-sm hover:bg-indigo-50 transition-colors {{ $selectedPackage && $selectedPackage->id === $package->id ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200' }}">
                                    <div class="font-semibold text-gray-800">{{ $package->template_reference }}</div>
                                    <div class="text-xs text-gray-400">{{ $package->items->count() }} دارایی</div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card lg:col-span-2">
                <div class="card-body">
                    <h2 class="text-base font-bold text-gray-900 mb-4">خروجی اجرا (Runtime Projection)</h2>

                    @if($projections === null)
                        <p class="text-sm text-gray-400">یک بسته را از فهرست کنار انتخاب کنید.</p>
                    @elseif(empty($projections))
                        <p class="text-sm text-gray-400">هیچ خروجی اجرایی برای این بسته موجود نیست.</p>
                    @else
                        <div class="space-y-4">
                            @foreach($projections as $projection)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-mono text-xs text-gray-600">{{ $projection->assetReference->assetId }}</span>
                                        <span class="text-xs text-gray-400">نسخه {{ $projection->publishedVersion }} · {{ $projection->assetReference->type }}</span>
                                    </div>
                                    @if($projection->optimizedReference)
                                        <div class="text-xs text-gray-500 mb-2">مرجع بهینه‌شده: <span class="font-mono">{{ $projection->optimizedReference }}</span></div>
                                    @endif
                                    @if(!empty($projection->themeTokens))
                                        <div class="mb-2">
                                            <div class="text-xs font-semibold text-gray-600 mb-1">تطبیق تم (Theme Tokens)</div>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($projection->themeTokens as $key => $value)
                                                    <span class="text-[11px] bg-gray-100 rounded px-2 py-0.5">{{ $key }}: {{ $value }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    @if(!empty($projection->placementRules))
                                        <div>
                                            <div class="text-xs font-semibold text-gray-600 mb-1">قواعد چیدمان (Placement)</div>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($projection->placementRules as $key => $value)
                                                    <span class="text-[11px] bg-gray-100 rounded px-2 py-0.5">{{ $key }}: {{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
