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
    @endif
</div>
@endsection
