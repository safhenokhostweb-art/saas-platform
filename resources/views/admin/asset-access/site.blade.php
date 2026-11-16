@extends('layouts.admin')

@section('title', 'دسترسی دارایی‌های اختصاصی سایت')
@section('header', 'دسترسی قالب‌ها و ویجت‌های اختصاصی: ' . $site->domain)

@php
    use SaaSPlatform\ThemeRuntime\Access\AssetAccessDecision;
    use SaaSPlatform\ThemeRuntime\Access\AssetRef;
    use SaaSPlatform\ThemeRuntime\Access\BusinessVerticals;

    $stateLabels = [
        AssetAccessDecision::STATE_AVAILABLE => ['در دسترس', 'bg-emerald-600 text-white'],
        AssetAccessDecision::STATE_LOCKED => ['قفل (با نشان)', 'bg-amber-100 text-amber-800 border border-amber-200'],
        AssetAccessDecision::STATE_HIDDEN => ['پنهان', 'bg-gray-100 text-gray-600 border border-gray-200'],
    ];
    $reasonLabels = [
        AssetAccessDecision::REASON_GRANT => 'اختصاص مستقیم',
        AssetAccessDecision::REASON_ENTITLED => 'شرایط حوزه و لایسنس برقرار است',
        AssetAccessDecision::REASON_VERTICAL_MISMATCH => 'حوزهٔ کسب‌وکار سایت مطابق نیست',
        AssetAccessDecision::REASON_ENTITLEMENT_REQUIRED => 'کلید لایسنس لازم را ندارد',
        AssetAccessDecision::REASON_GRANT_REQUIRED => 'فقط با اختصاص مستقیم',
    ];
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-5" data-testid="site-asset-access">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-500 max-w-3xl leading-7">
            این صفحه نشان می‌دهد کدام قالب‌ها و ویجت‌های اختصاصی در ویرایشگر Canvas این سایت در دسترس، قفل یا پنهان هستند.
            دسترسی از حوزهٔ کسب‌وکار، کلیدهای لایسنس فعال سایت و اختصاص‌های مستقیم همین صفحه محاسبه می‌شود؛
            سایت پاسخ را در بار بعدی باز کردن ویرایشگر دریافت می‌کند. محتوایی که قبلاً در صفحات سایت درج شده، با لغو دسترسی حذف نمی‌شود.
        </p>
        <div class="flex gap-2">
            <a href="{{ route('admin.sites.modules.edit', $site) }}" class="btn btn-sm btn-outline-secondary">ماژول‌ها و لایسنس‌ها</a>
            <a href="{{ route('admin.asset-access.index') }}" class="btn btn-sm btn-outline-secondary">کاتالوگ مرکزی</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="card-body grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('admin.sites.asset-access.vertical', $site) }}" class="space-y-2" data-testid="site-vertical-form">
                @csrf
                <label class="text-sm font-semibold text-gray-800 block">حوزهٔ کسب‌وکار سایت</label>
                <div class="flex gap-2">
                    <select name="business_vertical" class="form-select form-select-sm">
                        <option value="">— تعیین نشده —</option>
                        @foreach($verticals as $value => $label)
                            <option value="{{ $value }}" @selected($site->business_vertical === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-primary">ذخیره</button>
                </div>
                <p class="text-xs text-gray-400">کیت‌های اختصاصی یک صنعت فقط برای سایت‌های همان حوزه نمایش داده می‌شوند.</p>
            </form>
            <div class="space-y-2">
                <div class="text-sm font-semibold text-gray-800">کلیدهای لایسنس فعال سایت</div>
                <div class="flex flex-wrap gap-1.5" data-testid="site-entitlement-keys">
                    @forelse($entitlements->entitlementKeys as $key)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono" dir="ltr">{{ $key }}</span>
                    @empty
                        <span class="text-xs text-gray-400">هیچ کلید فعالی وجود ندارد.</span>
                    @endforelse
                </div>
                <p class="text-xs text-gray-400">کلیدها همان ماژول‌های فعال و دارای لایسنس سایت هستند و از صفحهٔ ماژول‌ها مدیریت می‌شوند.</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body space-y-3">
            <h3 class="font-bold text-gray-900">موارد اختصاصی</h3>
            <div class="overflow-x-auto">
                <table class="table table-sm align-middle text-sm" data-testid="site-asset-rows">
                    <thead>
                        <tr class="text-gray-500 text-xs"><th>مورد</th><th>کیت</th><th>وضعیت برای این سایت</th><th>اختصاص</th></tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $ref => $row)
                            @php
                                [$stateLabel, $stateClass] = $stateLabels[$row['decision']->state];
                                $grant = $row['grant'];
                                $activeGrant = $grant && $grant->isActive();
                            @endphp
                            <tr data-testid="site-asset-row-{{ $ref }}">
                                <td>
                                    <div class="font-medium text-gray-900">{{ $row['name'] }}</div>
                                    <div class="text-xs text-gray-400"><span class="font-mono" dir="ltr">{{ $ref }}</span> · {{ AssetRef::KIND_LABELS[$row['kind']] ?? $row['kind'] }}</div>
                                </td>
                                <td class="text-xs">{{ $row['rule']->familyLabel ?? '—' }}</td>
                                <td class="text-xs space-y-1">
                                    <span class="px-2 py-0.5 rounded-full {{ $stateClass }}" data-testid="site-asset-state-{{ $ref }}">{{ $stateLabel }}</span>
                                    <div class="text-gray-500">{{ $reasonLabels[$row['decision']->reason] ?? $row['decision']->reason }}</div>
                                </td>
                                <td class="text-xs">
                                    @if($activeGrant)
                                        <div class="mb-1 text-gray-600">
                                            {{ $sourceLabels[$grant->source] ?? $grant->source }}
                                            @if($grant->reference)<span class="text-gray-400">({{ $grant->reference }})</span>@endif
                                            · {{ optional($grant->granted_at)->format('Y-m-d') }}
                                        </div>
                                        <form method="POST" action="{{ route('admin.sites.asset-access.revoke', $site) }}" onsubmit="return confirm('دسترسی این سایت لغو شود؟')">
                                            @csrf
                                            <input type="hidden" name="asset" value="{{ $ref }}">
                                            <button class="btn btn-sm btn-outline-danger">لغو اختصاص</button>
                                        </form>
                                    @else
                                        @if($grant)
                                            <div class="mb-1 text-gray-400">لغو‌شده در {{ optional($grant->revoked_at)->format('Y-m-d') }}</div>
                                        @endif
                                        <form method="POST" action="{{ route('admin.sites.asset-access.grant', $site) }}" class="flex flex-wrap gap-1.5 items-center">
                                            @csrf
                                            <input type="hidden" name="asset" value="{{ $ref }}">
                                            <select name="source" class="form-select form-select-sm w-auto">
                                                @foreach($sourceLabels as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <input name="reference" class="form-control form-control-sm w-32" placeholder="شمارهٔ سفارش">
                                            <button class="btn btn-sm btn-primary">اختصاص</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-gray-400 text-center py-6">هیچ مورد اختصاصی در کاتالوگ وجود ندارد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
