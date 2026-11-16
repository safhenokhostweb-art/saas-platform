@extends('layouts.admin')

@section('title', 'کاتالوگ موشن‌ها')
@section('header', 'کاتالوگ و دسترسی موشن‌ها')

@php
    use App\Modules\MotionGraphics\Models\MotionSiteAssetGrant;
    use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

    $sourceLabels = [
        MotionSiteAssetGrant::SOURCE_ADMIN_GRANT => 'اعطای مدیر',
        MotionSiteAssetGrant::SOURCE_PURCHASE => 'خرید',
        MotionSiteAssetGrant::SOURCE_LICENSE => 'لایسنس',
    ];
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-5" data-testid="motion-catalog">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-500 max-w-3xl leading-7">
            موشن‌های <strong>عمومی</strong> به همه سایت‌هایی که موشن گرافیک برایشان فعال است می‌رسند.
            موشن‌های <strong>خصوصی</strong> فقط به سایتی می‌رسند که در همین صفحه به آن اعطا شده باشند؛ لایسنس پرمیوم به‌تنهایی آن‌ها را باز نمی‌کند.
            غیرفعال‌کردن یک موشن آن را از دریافت بعدی مانیفست همه سایت‌ها حذف می‌کند، بدون اینکه نسخه‌ها یا اعطاها پاک شوند.
        </p>
        <a href="{{ route('admin.motion-graphics.select-site') }}" class="btn btn-sm btn-outline-secondary">بازگشت به مدیریت سایت‌ها</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @forelse($motions as $motion)
        @php
            $published = $motion->versions->firstWhere('version', $motion->published_version);
            $isExclusive = $motion->ownership_type === MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC;
            $isPrivate = ! $isExclusive && $motion->visibility() === MotionGraphic::VISIBILITY_PRIVATE;
            $enabled = $motion->isRuntimeEnabled();
            $motionGrants = $grants->get($motion->uuid, collect());
            $activeGrants = $motionGrants->where('status', MotionSiteAssetGrant::STATUS_ACTIVE);
        @endphp
        <div class="card" data-testid="motion-catalog-row-{{ $motion->slug }}">
            <div class="card-body space-y-4">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0 space-y-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-gray-900">{{ $motion->name }}</h3>
                            @if($isExclusive)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200">اختصاصی سایت #{{ $motion->owner_site_identifier }}</span>
                            @elseif($isPrivate)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200" data-testid="motion-visibility-{{ $motion->slug }}">خصوصی</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200" data-testid="motion-visibility-{{ $motion->slug }}">عمومی</span>
                            @endif
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-50 text-gray-600 border border-gray-200">
                                {{ $motion->licenseTier() === MotionGraphic::LICENSE_TIER_FREE ? 'رایگان' : 'پرمیوم' }}
                            </span>
                            @if($enabled)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-600 text-white" data-testid="motion-state-{{ $motion->slug }}">فعال</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-red-600 text-white" data-testid="motion-state-{{ $motion->slug }}">غیرفعال</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                            <span dir="ltr" class="font-mono">{{ $motion->slug }}</span>
                            @if($motion->category)<span>دسته: {{ $motion->category->name }}</span>@endif
                            <span>
                                نسخه منتشرشده:
                                @if($published)
                                    <strong class="text-gray-800" dir="ltr" data-testid="motion-version-{{ $motion->slug }}">{{ $published->versionLabel() }}</strong>
                                    <span class="text-gray-400">(#{{ $published->version }}@if($published->buildId()) · build <span dir="ltr" class="font-mono">{{ $published->buildId() }}</span>@endif)</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </span>
                            <span>سایت‌های دارای بسته: {{ (int) ($boundSites[$motion->uuid] ?? 0) }}</span>
                            @if($isPrivate)<span>اعطای فعال: {{ $activeGrants->count() }}</span>@endif
                        </div>
                        @if(! $enabled && $motion->runtime_disabled_at)
                            <div class="text-xs text-red-700">غیرفعال از {{ \App\Helpers\Jalali::formatFa(\Carbon\Carbon::parse($motion->runtime_disabled_at), 'Y/m/d H:i') }}</div>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.motion-graphics.catalog.availability', $motion) }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
                        <button type="submit" class="btn btn-sm {{ $enabled ? 'btn-danger' : 'btn-success' }}" data-testid="motion-toggle-{{ $motion->slug }}"
                                @if($enabled) onclick="return confirm('این موشن از همه سایت‌ها پنهان می‌شود. ادامه می‌دهید؟')" @endif>
                            {{ $enabled ? 'غیرفعال‌سازی فوری' : 'فعال‌سازی دوباره' }}
                        </button>
                    </form>
                </div>

                @if($isPrivate)
                    <div class="border-t border-gray-100 pt-4 space-y-3">
                        <h4 class="text-sm font-bold text-gray-800">سایت‌های دارای دسترسی</h4>
                        @if($motionGrants->isEmpty())
                            <p class="text-xs text-gray-400">هنوز به هیچ سایتی اعطا نشده است؛ این موشن به هیچ سایتی تحویل داده نمی‌شود.</p>
                        @else
                            <ul class="divide-y divide-gray-100 text-sm">
                                @foreach($motionGrants as $grant)
                                    <li class="py-2 flex items-center justify-between gap-3 flex-wrap" data-testid="motion-grant-{{ $motion->slug }}-{{ $grant->site_id }}">
                                        <div class="min-w-0">
                                            <span class="font-medium text-gray-800">#{{ $grant->site_id }} — {{ $grant->site?->title ?? $grant->site?->domain ?? 'بدون عنوان' }}</span>
                                            <span class="text-xs text-gray-500">· {{ $sourceLabels[$grant->source] ?? $grant->source }}@if($grant->reference) · <span dir="ltr">{{ $grant->reference }}</span>@endif</span>
                                            @if($grant->status === MotionSiteAssetGrant::STATUS_ACTIVE)
                                                <span class="text-xs text-emerald-700">· فعال</span>
                                            @else
                                                <span class="text-xs text-gray-400">· لغو شده</span>
                                            @endif
                                        </div>
                                        @if($grant->status === MotionSiteAssetGrant::STATUS_ACTIVE)
                                            <form method="POST" action="{{ route('admin.motion-graphics.catalog.grants.revoke', $grant) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('دسترسی این سایت لغو شود؟')">لغو دسترسی</button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('admin.motion-graphics.catalog.grants.store', $motion) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end">
                            @csrf
                            <label class="block sm:col-span-2">
                                <span class="form-label">سایت</span>
                                <select name="site_id" class="form-select" required>
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}">#{{ $site->id }} — {{ $site->title ?? $site->domain ?? 'بدون عنوان' }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="form-label">مبنا</span>
                                <select name="source" class="form-select">
                                    @foreach($sourceLabels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="form-label">شماره سفارش/مرجع (اختیاری)</span>
                                <input type="text" name="reference" maxlength="191" class="form-input" dir="ltr">
                            </label>
                            <div class="sm:col-span-4">
                                <button type="submit" class="btn btn-primary btn-sm" data-testid="motion-grant-submit-{{ $motion->slug }}">اعطای دسترسی به این سایت</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-sm text-gray-400">هنوز هیچ موشنی در کاتالوگ منتشر نشده است.</div></div>
    @endforelse
</div>
@endsection
