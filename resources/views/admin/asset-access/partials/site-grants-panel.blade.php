@php
    use SaaSPlatform\ThemeRuntime\Access\AssetAccessDecision;
    use SaaSPlatform\ThemeRuntime\Access\AssetRef;

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

<div class="card mt-6" id="site-asset-access-section" data-testid="site-asset-access">
    <div class="card-body space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <h3 class="font-bold text-gray-900">دسترسی قالب‌ها و ویجت‌های اختصاصی این سایت</h3>
                <p class="text-xs text-gray-500 mt-1">
                    دسترسی از حوزهٔ کسب‌وکار، کلیدهای لایسنس فعال سایت و اختصاص‌های مستقیم زیر محاسبه می‌شود.
                </p>
            </div>
            <div class="flex flex-wrap gap-1.5" data-testid="site-entitlement-keys">
                @forelse($entitlements->entitlementKeys as $key)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono" dir="ltr">{{ $key }}</span>
                @empty
                    <span class="text-xs text-gray-400">بدون کلید لایسنس اختصاصی</span>
                @endforelse
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-sm align-middle text-sm" data-testid="site-asset-rows">
                <thead>
                    <tr class="text-gray-500 text-xs"><th>مورد</th><th>کیت</th><th>وضعیت برای این سایت</th><th>اختصاص</th></tr>
                </thead>
                <tbody>
                    @forelse($assetRows as $ref => $row)
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
