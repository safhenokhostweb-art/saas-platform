@props([
    'updates'     => [],    // array of {module, version, type: 'security'|'feature'|'patch', description, released_at}
    'licensed'    => true,  // whether the current tenant has a valid license
    'dismissible' => true,
])
@php
$securityUpdates = collect($updates)->where('type', 'security');
$featureUpdates  = collect($updates)->where('type', 'feature');
$patchUpdates    = collect($updates)->where('type', 'patch');
@endphp

@if($securityUpdates->isNotEmpty())
{{-- Security updates: ALWAYS visible regardless of license --}}
<div x-data="{ show: true }"
     x-show="show"
     x-transition.duration.300ms
     class="flex items-start gap-3 bg-red-50 border border-red-200 rounded-2xl px-5 py-4 mb-4"
     role="alert"
     aria-live="polite">
    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-red-100 shrink-0 mt-0.5">
        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-bold text-red-800">
            {{ $securityUpdates->count() }} بروزرسانی امنیتی در دسترس است
        </p>
        @foreach($securityUpdates as $update)
        <p class="text-xs text-red-700 mt-0.5">
            <span class="font-semibold">{{ $update['module'] ?? '' }}</span>
            نسخه {{ $update['version'] ?? '' }} — {{ $update['description'] ?? '' }}
        </p>
        @endforeach
        <p class="text-xs text-red-600 mt-1 font-medium">
            بروزرسانی‌های امنیتی برای همه مستاجران قابل مشاهده است.
        </p>
    </div>
    @if($dismissible)
    <button @click="show = false" aria-label="بستن" class="text-red-400 hover:text-red-600 transition-colors shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
    @endif
</div>
@endif

@if($licensed && $featureUpdates->isNotEmpty())
{{-- Feature updates: ONLY visible to licensed tenants --}}
<div x-data="{ show: true }"
     x-show="show"
     x-transition.duration.300ms
     class="flex items-start gap-3 bg-indigo-50 border border-indigo-200 rounded-2xl px-5 py-4 mb-4">
    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-indigo-100 shrink-0 mt-0.5">
        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-bold text-indigo-800">
            {{ $featureUpdates->count() }} قابلیت جدید در دسترس است
        </p>
        @foreach($featureUpdates->take(3) as $update)
        <p class="text-xs text-indigo-700 mt-0.5">
            <span class="font-semibold">{{ $update['module'] ?? '' }}</span> v{{ $update['version'] ?? '' }}
            — {{ $update['description'] ?? '' }}
        </p>
        @endforeach
    </div>
    @if($dismissible)
    <button @click="show = false" aria-label="بستن" class="text-indigo-400 hover:text-indigo-600 transition-colors shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
    @endif
</div>
@endif

@if(! $licensed && ($featureUpdates->isNotEmpty() || $patchUpdates->isNotEmpty()))
{{-- Unlicensed tenants see a teaser but NOT update details --}}
<div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-2xl px-5 py-3.5 mb-4">
    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
    </svg>
    <p class="text-xs text-gray-500 flex-1">
        {{ $featureUpdates->count() + $patchUpdates->count() }} بروزرسانی قابلیت در انتظار است —
        <a href="#" class="font-semibold text-indigo-600 hover:underline">لایسنس فعال کنید</a> تا دسترسی پیدا کنید.
    </p>
</div>
@endif
