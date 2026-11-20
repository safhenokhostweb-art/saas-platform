@extends('layouts.admin')

@section('header', 'حذف کامل سایت')

@section('content')
@php
    $fmtBytes = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    };
    $badge = fn ($action) => match ($action) {
        'delete'         => ['حذف می‌شود', 'bg-red-50 text-red-700 border-red-200'],
        'manual'         => ['اقدام دستی', 'bg-amber-50 text-amber-800 border-amber-200'],
        'not_applicable' => ['مربوط نیست', 'bg-gray-50 text-gray-500 border-gray-200'],
        'retained'       => ['حفظ می‌شود', 'bg-green-50 text-green-700 border-green-200'],
        default          => [$action, 'bg-gray-50 text-gray-600 border-gray-200'],
    };
    $blocked = !empty($plan['blockers']);
@endphp

<div class="max-w-4xl mx-auto" dir="rtl">

    <div class="flex items-start gap-3 mb-5">
        <a href="{{ route('admin.sites.index') }}" class="mt-1 text-gray-400 hover:text-gray-600" aria-label="بازگشت به فهرست سایت‌ها">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-gray-900">حذف کامل و غیرقابل بازگشت: {{ $site->title }}</h1>
            <p class="text-sm text-gray-500 mt-0.5"><span dir="ltr" class="font-mono">{{ $site->domain }}</span> · سایت #{{ $site->id }}</p>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pr-5 space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
        <p class="font-semibold mb-1">این عملیات بلافاصله اجرا می‌شود و قابل بازگشت نیست.</p>
        <p class="text-red-800">پوشهٔ سایت روی سرور، پیکربندی Nginx، گواهی SSL، دیتابیس و کاربر آن، کران و ورکر صف، و رکوردهای سایت در پنل پاک می‌شوند. رکورد مشتری حفظ می‌شود.</p>
    </div>

    @if($blocked)
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold mb-1">حذف کامل فعلاً ممکن نیست:</p>
            <ul class="list-disc pr-5 space-y-0.5">@foreach($plan['blockers'] as $b)<li>{{ $b }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- 1. Options (re-renders the dry-run) --}}
    <form method="GET" action="{{ route('admin.sites.purge.show', $site) }}" class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-800 mb-3">۱. گزینه‌های حذف</h2>
        <label class="flex items-start gap-2 mb-2 cursor-pointer">
            <input type="checkbox" name="delete_backups" value="1" @checked($deleteBackups) class="mt-1 rounded text-red-600">
            <span class="text-sm text-gray-800">حذف همهٔ بکاپ‌های این سایت در آروان و سرور پلتفرم
                <span class="block text-xs text-gray-500">پیشوندهای <span dir="ltr" class="font-mono">tenants/site-{{ $site->id }}/</span> و بکاپ‌های قدیمی. بکاپ نهایی هم گرفته <strong>نمی‌شود</strong>. بدون این گزینه، پیش از حذف یک بکاپ نهایی گرفته و همهٔ بکاپ‌ها نگه داشته می‌شوند.</span>
            </span>
        </label>
        <label class="flex items-start gap-2 cursor-pointer">
            <input type="checkbox" name="delete_media" value="1" @checked($deleteMedia) class="mt-1 rounded text-red-600">
            <span class="text-sm text-gray-800">حذف همهٔ فایل‌های مدیای سایت در آروان
                <span class="block text-xs text-gray-500">پیشوند <span dir="ltr" class="font-mono">sites/{{ $site->id }}/</span> در باکت مدیا (عمومی و خصوصی).</span>
            </span>
        </label>
        <button type="submit" class="mt-3 btn btn-outline-secondary text-sm">به‌روزرسانی پیش‌نمایش</button>
    </form>

    {{-- 2. Dry-run --}}
    <div class="mb-5 rounded-xl border border-gray-200 bg-white shadow-sm">
        <h2 class="text-sm font-semibold text-gray-800 p-4 pb-2">۲. پیش‌نمایش دقیق (Dry-run)</h2>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @if($plan['server'] ?? null)
                <tr>
                    <td class="p-3 text-gray-500 w-40 align-top">سرور مقصد</td>
                    <td class="p-3"><span class="font-medium">{{ $plan['server']['label'] }}</span> <span dir="ltr" class="font-mono text-xs text-gray-500">{{ $plan['server']['host'] }} ({{ $plan['server']['type'] }})</span></td>
                    <td class="p-3"></td>
                </tr>
                @endif
                @if(isset($plan['web_root']))
                @php [$l, $c] = $badge($plan['web_root']['action']); @endphp
                <tr>
                    <td class="p-3 text-gray-500 align-top">پوشهٔ سایت</td>
                    <td class="p-3"><span dir="ltr" class="font-mono text-xs break-all">{{ $plan['web_root']['path'] }}</span>
                        @if(!empty($plan['web_root']['reason']))<p class="text-xs text-amber-700 mt-1">{{ $plan['web_root']['reason'] }}</p>@endif
                    </td>
                    <td class="p-3 whitespace-nowrap"><span class="text-xs border rounded px-2 py-0.5 {{ $c }}">{{ $l }}</span></td>
                </tr>
                @endif
                @if(isset($plan['nginx']))
                @php [$l, $c] = $badge($plan['nginx']['action']); @endphp
                <tr>
                    <td class="p-3 text-gray-500 align-top">Nginx</td>
                    <td class="p-3" dir="ltr">
                        @if(isset($plan['nginx']['available']))<span class="font-mono text-xs block text-left">{{ $plan['nginx']['available'] }}</span><span class="font-mono text-xs block text-left">{{ $plan['nginx']['enabled'] }}</span>
                        @else<span class="text-xs text-gray-500" dir="rtl">{{ $plan['nginx']['reason'] ?? '' }}</span>@endif
                    </td>
                    <td class="p-3 whitespace-nowrap"><span class="text-xs border rounded px-2 py-0.5 {{ $c }}">{{ $l }}</span></td>
                </tr>
                @endif
                @foreach($plan['services'] ?? [] as $svc => $path)
                <tr>
                    <td class="p-3 text-gray-500">{{ $svc === 'cron' ? 'زمان‌بند (cron)' : 'ورکر صف (Supervisor)' }}</td>
                    <td class="p-3"><span dir="ltr" class="font-mono text-xs">{{ $path }}</span></td>
                    <td class="p-3"><span class="text-xs border rounded px-2 py-0.5 {{ $badge('delete')[1] }}">{{ $badge('delete')[0] }}</span></td>
                </tr>
                @endforeach
                @foreach($plan['certificates'] ?? [] as $cert)
                @php [$l, $c] = $badge($cert['action']); @endphp
                <tr>
                    <td class="p-3 text-gray-500 align-top">گواهی SSL</td>
                    <td class="p-3"><span dir="ltr" class="font-mono text-xs">{{ $cert['name'] }}</span>
                        @if(!empty($cert['reason']))<p class="text-xs text-amber-700 mt-1">{{ $cert['reason'] }}</p>@endif
                    </td>
                    <td class="p-3"><span class="text-xs border rounded px-2 py-0.5 {{ $c }}">{{ $l }}</span></td>
                </tr>
                @endforeach
                @if(isset($plan['database']))
                @php [$l, $c] = $badge($plan['database']['action']); @endphp
                <tr>
                    <td class="p-3 text-gray-500">دیتابیس</td>
                    <td class="p-3" dir="ltr"><span class="font-mono text-xs">{{ $plan['database']['name'] ?? '—' }}</span> <span class="text-gray-400 text-xs">user:</span> <span class="font-mono text-xs">{{ $plan['database']['user'] ?? '—' }}</span></td>
                    <td class="p-3"><span class="text-xs border rounded px-2 py-0.5 {{ $c }}">{{ $l }}</span></td>
                </tr>
                @endif
                @foreach(['backups' => 'بکاپ آروان', 'media' => 'مدیای آروان'] as $section => $label)
                    @foreach($plan[$section]['prefixes'] ?? [] as $prefix)
                    @php
                        $inv = $inventory[$prefix] ?? [];
                        [$l, $c] = $badge(($plan[$section]['delete'] ?? false) ? 'delete' : 'retained');
                    @endphp
                    <tr>
                        <td class="p-3 text-gray-500">{{ $label }}</td>
                        <td class="p-3"><span dir="ltr" class="font-mono text-xs">{{ $prefix }}</span>
                            <span class="text-xs text-gray-500 mr-2">
                                @if(isset($inv['error'])) (شمارش ممکن نشد: {{ $inv['error'] }})
                                @elseif(isset($inv['objects'])) {{ number_format($inv['objects']) }} آبجکت · {{ $fmtBytes($inv['bytes']) }}@endif
                            </span>
                        </td>
                        <td class="p-3"><span class="text-xs border rounded px-2 py-0.5 {{ $c }}">{{ $l }}</span></td>
                    </tr>
                    @endforeach
                @endforeach
                <tr>
                    <td class="p-3 text-gray-500">رکوردهای پنل</td>
                    <td class="p-3 text-xs text-gray-700">سایت، ماژول‌ها، تنظیمات، دامنه‌ها ({{ $plan['platform']['site_domains'] ?? 0 }})، لایسنس‌ها ({{ $plan['platform']['licenses'] ?? 0 }}). سوابق خرید و لاگ‌ها برای حسابرسی حفظ می‌شوند.</td>
                    <td class="p-3"><span class="text-xs border rounded px-2 py-0.5 {{ $badge('delete')[1] }}">{{ $badge('delete')[0] }}</span></td>
                </tr>
                <tr>
                    <td class="p-3 text-gray-500">مشتری</td>
                    <td class="p-3 text-xs text-gray-700">{{ $site->customer?->name ?? $site->customer?->company ?? '—' }}</td>
                    <td class="p-3"><span class="text-xs border rounded px-2 py-0.5 {{ $badge('retained')[1] }}">{{ $badge('retained')[0] }}</span></td>
                </tr>
            </tbody>
        </table>
        </div>
        @if($manualItems)
            <div class="m-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                <p class="font-semibold mb-1">این موارد به‌طور خودکار حذف نمی‌شوند و بعد از اجرا باید دستی بررسی شوند:</p>
                <ul class="list-disc pr-5 space-y-0.5">@foreach($manualItems as $m)<li>{{ $m }}</li>@endforeach</ul>
            </div>
        @endif
        <p class="px-4 pb-3 text-xs text-gray-400" dir="ltr">plan sha256: {{ $planHash }}</p>
    </div>

    {{-- 3. Confirmation --}}
    @unless($blocked)
    <form method="POST" action="{{ route('admin.sites.purge.store', $site) }}" class="rounded-xl border-2 border-red-300 bg-white p-4 shadow-sm">
        @csrf
        <input type="hidden" name="plan_hash" value="{{ $planHash }}">
        <input type="hidden" name="delete_backups" value="{{ $deleteBackups ? 1 : 0 }}">
        <input type="hidden" name="delete_media" value="{{ $deleteMedia ? 1 : 0 }}">

        <h2 class="text-sm font-semibold text-gray-800 mb-3">۳. تأیید نهایی</h2>

        <label for="confirm_domain" class="form-label">برای تأیید، دامنهٔ سایت را دقیقاً تایپ کنید: <span dir="ltr" class="font-mono text-red-700">{{ $site->domain }}</span></label>
        <input id="confirm_domain" type="text" name="confirm_domain" dir="ltr" autocomplete="off" spellcheck="false" required
               value="{{ old('confirm_domain') }}" class="form-control font-mono mb-3 @error('confirm_domain') border-red-400 @enderror">

        <label for="current_password" class="form-label">رمز عبور حساب خودتان</label>
        <input id="current_password" type="password" name="current_password" autocomplete="current-password" required
               class="form-control mb-3 @error('current_password') border-red-400 @enderror">

        <label class="flex items-start gap-2 mb-4 cursor-pointer">
            <input type="checkbox" name="acknowledge" value="1" required class="mt-1 rounded text-red-600">
            <span class="text-sm text-gray-800">می‌دانم این عملیات فوراً اجرا می‌شود و غیرقابل بازگشت است
                @if($deleteBackups)<strong class="text-red-700"> و هیچ بکاپی از این سایت باقی نمی‌ماند</strong>@endif.</span>
        </label>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">حذف کامل سایت</button>
            <a href="{{ route('admin.sites.index') }}" class="btn btn-outline-secondary text-sm">انصراف</a>
        </div>
    </form>
    @endunless
</div>
@endsection
