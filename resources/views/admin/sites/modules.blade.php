@extends('layouts.admin')

@section('content')
<h1 class="text-xl font-bold mb-4">
    مدیریت ماژول‌های سایت: {{ $site->domain }}
</h1>
<p class="mb-4">
    <a href="{{ route('admin.sites.module-upgrades.index', $site) }}" class="text-indigo-600 hover:underline text-sm">مشاهدهٔ بروزرسانی‌های موجود ماژول‌ها →</a>
    <a href="{{ route('admin.sites.asset-access', $site) }}" class="text-indigo-600 hover:underline text-sm ms-4">قالب‌ها و ویجت‌های اختصاصی این سایت →</a>
</p>
<p class="text-gray-600 mb-4">
    در اینجا فقط ماژول‌های <strong>رایگان (قابل اختصاص دستی)</strong> و <strong>فقط با لایسنس</strong> نمایش داده می‌شوند.
    ماژول‌های <strong>پولی</strong> فقط از طریق فروشگاه ماژول در پنل مشتری قابل خرید و فعال‌سازی هستند.
</p>

<form method="POST" action="{{ route('admin.sites.modules.update', $site) }}">
    @csrf
    @method('PUT')

    @forelse($modules as $module)
        @php
            $siteModule = $site->modules->firstWhere('id', $module->id);
            $isActive = $siteModule && $siteModule->pivot->is_active;
            $hasLicense = $hasLicenseByModuleId[$module->id] ?? true;
            $canToggle = $module->isFreeAssignable() || ($module->isLicenseOnly() && $hasLicense);
        @endphp
        <div class="border p-4 rounded mb-4">
            <label class="flex items-center gap-2">
                @if($canToggle)
                    <input type="hidden" name="modules[{{ $module->id }}]" value="0">
                @endif
                <input type="checkbox"
                       name="modules[{{ $module->id }}]"
                       value="1"
                       {{ $isActive ? 'checked' : '' }}
                       {{ !$canToggle ? 'disabled' : '' }}>
                <strong>{{ $module->title }}</strong>
                @if($module->isLicenseOnly())
                    <span class="text-xs text-amber-600">(فقط با لایسنس — {{ $hasLicense ? 'لایسنس دارد' : 'لایسنس صادر کنید' }})</span>
                @else
                    <span class="text-xs text-gray-500">(رایگان)</span>
                @endif
            </label>

            @if($module->features->count())
                <div class="mt-2 mr-6 space-y-2">
                    @foreach($module->features as $feature)
                        <label class="flex items-center gap-2">
                            <input type="checkbox"
                                   name="features[{{ $feature->id }}]"
                                   value="1"
                                   {{ in_array($feature->id, $enabledFeatureIds ?? [], true) ? 'checked' : '' }}>
                            {{ $feature->title }}
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <p class="text-gray-500">هیچ ماژولی برای اختصاص دستی تعریف نشده است. ماژول‌های پولی در فروشگاه ماژول پنل مشتری نمایش داده می‌شوند.</p>
    @endforelse

    @if($modules->isNotEmpty())
        <button class="btn btn-primary">ذخیره تنظیمات</button>
    @endif
</form>
@endsection
