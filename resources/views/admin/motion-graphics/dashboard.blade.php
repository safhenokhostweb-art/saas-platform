@extends('layouts.admin')

@section('title', 'داشبورد موشن گرافیک')
@section('header', 'داشبورد موشن گرافیک')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    @include('admin.motion-graphics.partials.site-nav', ['site' => $site, 'active' => 'dashboard'])

    @if($error)
        <div class="alert alert-warning">
            این قابلیت برای سایت #{{ $site->id }} فعال نیست ({{ $error->failureCode }}).
            برای فعال‌سازی: <code dir="ltr">php artisan motion:internal-test-site {{ $site->id }}</code>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card"><div class="card-body">
                <div class="text-xs text-gray-500 mb-1">کل دارایی‌های موشن</div>
                <div class="text-2xl font-bold text-gray-900">{{ $summary->totalAssets }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-xs text-gray-500 mb-1">قالب‌ها</div>
                <div class="text-2xl font-bold text-gray-900">{{ $summary->totalTemplates }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-xs text-gray-500 mb-1">بسته‌های اختصاص‌یافته</div>
                <div class="text-2xl font-bold text-gray-900">{{ $summary->assignedPackages }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-xs text-gray-500 mb-1">سایت‌های آزمایشی فعال</div>
                <div class="text-2xl font-bold text-gray-900">{{ $summary->activatedInternalSites }}</div>
            </div></div>
        </div>

        {{-- Phase 9 (docs/motion-commercialization/ADMIN_MANAGEMENT.md): commercial/licensing summary --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="card"><div class="card-body">
                <div class="text-xs text-gray-500 mb-1">سایت‌های دارای لایسنس معتبر</div>
                <div class="text-2xl font-bold text-gray-900">{{ $summary->licensedSitesCount }}</div>
                <div class="text-[11px] text-gray-400 mt-1">لایسنس با فعال‌سازی فنی یکسان نیست — سایت می‌تواند لایسنس داشته باشد ولی هنوز فعال نشده باشد.</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-xs text-gray-500 mb-1">وضعیت ماژول در فروشگاه</div>
                <div class="text-2xl font-bold text-gray-900">
                    @if($summary->moduleStatus === 'active')
                        <span class="text-emerald-600">فعال و قابل فروش</span>
                    @elseif($summary->moduleStatus === 'inactive')
                        <span class="text-amber-600">غیرفعال</span>
                    @else
                        <span class="text-red-600">ثبت نشده</span>
                    @endif
                </div>
            </div></div>
        </div>
    @endif
</div>
@endsection
