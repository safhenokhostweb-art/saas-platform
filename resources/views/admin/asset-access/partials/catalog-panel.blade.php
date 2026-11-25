@php
    use SaaSPlatform\ThemeRuntime\Access\AssetRef;
    use SaaSPlatform\ThemeRuntime\Access\BusinessVerticals;

    $ruleSummary = function ($rule) {
        if ($rule->isPublic) {
            return 'عمومی — همهٔ سایت‌ها';
        }
        $parts = [];
        if ($rule->requiredVertical) {
            $parts[] = 'حوزه: ' . BusinessVerticals::label($rule->requiredVertical);
        }
        if ($rule->entitlementKeys) {
            $parts[] = 'کلید لایسنس: ' . implode('، ', $rule->entitlementKeys);
        }
        if (!$parts) {
            $parts[] = 'فقط با اختصاص مستقیم';
        }
        $parts[] = $rule->lockedDisplay === 'teaser' ? 'قفل‌شده نمایش داده می‌شود' : 'در صورت عدم دسترسی پنهان';

        return implode(' · ', $parts);
    };
@endphp

<div class="space-y-5" data-testid="asset-access-catalog">
    <p class="text-sm text-gray-500 max-w-3xl leading-7">
        هر قالب آماده و ویجت ویرایشگر Canvas در کاتالوگ مرکزی پلتفرم طبقه‌بندی شده است. موارد <strong>عمومی</strong> به همهٔ سایت‌ها می‌رسند؛
        موارد <strong>اختصاصی</strong> فقط به سایتی می‌رسند که شرایط آن (حوزهٔ کسب‌وکار و کلید لایسنس) را داشته باشد یا از صفحهٔ ویرایش همان سایت به آن اختصاص داده شده باشد.
        @if(!empty($motionCatalogUrl))
            دسترسی موشن‌ها در <a href="{{ $motionCatalogUrl }}" class="text-indigo-600 hover:underline">کاتالوگ موشن‌ها</a> مدیریت می‌شود.
        @endif
    </p>

    <div class="card">
        <div class="card-body space-y-3">
            <h3 class="font-bold text-gray-900">موارد اختصاصی ({{ count($restricted) }})</h3>
            <div class="overflow-x-auto">
                <table class="table table-sm align-middle text-sm" data-testid="asset-access-restricted">
                    <thead>
                        <tr class="text-gray-500 text-xs">
                            <th>مورد</th><th>نوع</th><th>کیت</th><th>سیاست اعمال‌شده</th><th>سایت‌های دارای اختصاص</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($restricted as $ref => $asset)
                            @php $override = $overrides->get($ref); @endphp
                            <tr data-testid="asset-row-{{ $ref }}">
                                <td>
                                    <div class="font-medium text-gray-900">{{ $asset['name'] }}</div>
                                    <div class="text-xs text-gray-400 font-mono" dir="ltr">{{ $ref }}</div>
                                </td>
                                <td class="text-xs">{{ AssetRef::KIND_LABELS[$asset['kind']] ?? $asset['kind'] }}</td>
                                <td class="text-xs">{{ $asset['rule']->familyLabel ?? $asset['family'] ?? '—' }}</td>
                                <td class="text-xs leading-6">
                                    {{ $ruleSummary($override ? $override->rule() : $asset['rule']) }}
                                    @if($override)
                                        <span class="ms-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">بازنویسی‌شده</span>
                                    @endif
                                </td>
                                <td class="text-xs">{{ $grantCounts[$ref] ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-gray-400 text-center py-6">هیچ مورد اختصاصی تعریف نشده است.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body space-y-3">
            <h3 class="font-bold text-gray-900">بازنویسی سیاست یک مورد</h3>
            <p class="text-xs text-gray-500 leading-6">
                بازنویسی، سیاست اعلام‌شده در کاتالوگ را برای همهٔ سایت‌ها و بدون انتشار نسخهٔ جدید جایگزین می‌کند (مثلاً عمومی کردن یک قالب یا اختصاصی کردن یک ویجت).
                با «بازگرداندن پیش‌فرض» سیاست کاتالوگ دوباره اعمال می‌شود.
            </p>
            <form method="POST" action="{{ route('admin.asset-access.overrides.save') }}" class="grid gap-3 md:grid-cols-3" data-testid="asset-override-form">
                @csrf
                <label class="text-xs text-gray-600 md:col-span-3">مورد
                    <select name="asset" class="form-select form-select-sm mt-1" required>
                        @foreach($listed as $ref => $name)
                            <option value="{{ $ref }}">{{ $name }} — {{ $ref }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-gray-600">دسترسی
                    <select name="visibility" class="form-select form-select-sm mt-1">
                        <option value="restricted">اختصاصی</option>
                        <option value="public">عمومی</option>
                    </select>
                </label>
                <label class="text-xs text-gray-600">حوزهٔ کسب‌وکار لازم
                    <select name="required_vertical" class="form-select form-select-sm mt-1">
                        <option value="">— بدون شرط حوزه —</option>
                        @foreach($verticals as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-gray-600">کلیدهای لایسنس (یکی کافی است)
                    <input name="entitlement_keys" class="form-control form-control-sm mt-1" dir="ltr" placeholder="solar_industry_kit">
                </label>
                <label class="text-xs text-gray-600">نمایش در صورت عدم دسترسی
                    <select name="locked_display" class="form-select form-select-sm mt-1">
                        <option value="teaser">قفل‌شده با نشان (مسیر ارتقا)</option>
                        <option value="hidden">پنهان</option>
                    </select>
                </label>
                <label class="text-xs text-gray-600 md:col-span-2">یادداشت
                    <input name="note" class="form-control form-control-sm mt-1" maxlength="500">
                </label>
                <div class="md:col-span-3"><button class="btn btn-sm btn-primary">ذخیرهٔ بازنویسی</button></div>
            </form>

            @if($overrides->isNotEmpty())
                <div class="border-t pt-3 space-y-2">
                    <h4 class="text-sm font-semibold text-gray-800">بازنویسی‌های فعال</h4>
                    @foreach($overrides as $ref => $override)
                        <div class="flex items-center justify-between gap-3 flex-wrap text-xs" data-testid="asset-override-{{ $ref }}">
                            <div>
                                <span class="font-mono" dir="ltr">{{ $ref }}</span> —
                                {{ $ruleSummary($override->rule()) }}
                                @if($override->note)<span class="text-gray-400">({{ $override->note }})</span>@endif
                            </div>
                            <form method="POST" action="{{ route('admin.asset-access.overrides.clear') }}">
                                @csrf
                                <input type="hidden" name="asset" value="{{ $ref }}">
                                <button class="btn btn-sm btn-outline-secondary">بازگرداندن پیش‌فرض</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
