@extends('layouts.admin')
@section('title', 'خزانه بازیابی محلی')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- ─── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">خزانه بازیابی محلی</h1>
            <p class="text-slate-500 mt-1 text-sm">ذخیره و تأیید نسخه‌های پشتیبان پلتفرم و مشتریان روی دیسک محلی</p>
        </div>
        <div class="flex gap-2">
            <button onclick="verifyAll()"
                class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                <x-core-ui::icon name="shield-check" class="w-4 h-4" />
                تأیید همه
            </button>
        </div>
    </div>

    {{-- ─── Flash ───────────────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- ─── Status Cards ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Overall Status --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                @php
                    $statusColor = match($status['overall_status']) {
                        'healthy'        => 'text-green-600 bg-green-50',
                        'degraded'       => 'text-yellow-600 bg-yellow-50',
                        'not_configured' => 'text-slate-500 bg-slate-50',
                        default          => 'text-slate-400 bg-slate-50',
                    };
                    $statusLabel = match($status['overall_status']) {
                        'healthy'        => 'سالم',
                        'degraded'       => 'با خطا',
                        'not_configured' => 'پیکربندی نشده',
                        'empty'          => 'خالی',
                        default          => 'نامشخص',
                    };
                @endphp
                <div class="p-2 rounded-lg {{ $statusColor }}">
                    <x-core-ui::icon name="server" class="w-5 h-5" />
                </div>
                <div>
                    <p class="text-xs text-slate-500">وضعیت کلی</p>
                    <p class="font-bold text-slate-800">{{ $statusLabel }}</p>
                </div>
            </div>
        </div>

        {{-- Disk Usage --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-blue-50 text-blue-600">
                    <x-core-ui::icon name="archive" class="w-5 h-5" />
                </div>
                <div>
                    <p class="text-xs text-slate-500">فضای مصرفی</p>
                    <p class="font-bold text-slate-800">{{ $status['total_size_human'] }}</p>
                    <p class="text-xs text-slate-400">{{ $status['free_human'] }} آزاد</p>
                </div>
            </div>
        </div>

        {{-- Verified Count --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-green-50 text-green-600">
                    <x-core-ui::icon name="check-circle" class="w-5 h-5" />
                </div>
                <div>
                    <p class="text-xs text-slate-500">تأیید شده</p>
                    <p class="font-bold text-slate-800">{{ $status['verified_count'] }}</p>
                    @if($status['failed_count'] > 0)
                        <p class="text-xs text-red-500">{{ $status['failed_count'] }} ناموفق</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Last Sync --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-purple-50 text-purple-600">
                    <x-core-ui::icon name="clock" class="w-5 h-5" />
                </div>
                <div>
                    <p class="text-xs text-slate-500">آخرین همگام‌سازی</p>
                    <p class="font-bold text-slate-800 text-sm">
                        {{ $status['last_sync'] ? $status['last_sync']->diffForHumans() : 'هرگز' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Configuration Card ──────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2">
            <x-core-ui::icon name="cog" class="w-5 h-5 text-slate-500" />
            <h2 class="font-semibold text-slate-800">پیکربندی خزانه</h2>
        </div>
        <form action="{{ route('admin.infrastructure.recovery-repository.save') }}" method="POST" class="p-6 space-y-5">
            @csrf

            {{-- Path --}}
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">مسیر خزانه محلی</label>
                <div class="flex gap-2">
                    <input type="text" name="local_path" id="localPathInput"
                        value="{{ config('installer.recovery_repository.local_path') }}"
                        placeholder="مثال: /var/backups/saas-platform یا D:\SAAS_PLATFORM_BACKUPS"
                        class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                    <button type="button" onclick="validatePath()"
                        class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm hover:bg-slate-200 transition">
                        بررسی مسیر
                    </button>
                </div>
                <div id="pathValidationResult" class="text-xs mt-1 hidden"></div>
                <p class="text-xs text-slate-400">
                    خالی بگذارید تا از مسیر پیش‌فرض <code>storage/app/local-recovery-repo</code> استفاده شود.
                    دایرکتوری باید وجود داشته باشد یا قابل ساخت باشد.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Policy --}}
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">سیاست پشتیبان‌گیری</label>
                    <select name="policy" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <option value="both"  {{ config('installer.recovery_repository.policy') === 'both'  ? 'selected' : '' }}>هر دو (Local + Arvan) — توصیه شده</option>
                        <option value="local" {{ config('installer.recovery_repository.policy') === 'local' ? 'selected' : '' }}>فقط محلی</option>
                        <option value="arvan" {{ config('installer.recovery_repository.policy') === 'arvan' ? 'selected' : '' }}>فقط Arvan</option>
                    </select>
                </div>

                {{-- Priority --}}
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">اولویت بازیابی</label>
                    <select name="priority" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <option value="local_first" {{ config('installer.recovery_repository.priority') === 'local_first' ? 'selected' : '' }}>محلی اول، سپس Arvan</option>
                        <option value="arvan_first" {{ config('installer.recovery_repository.priority') === 'arvan_first' ? 'selected' : '' }}>Arvan اول، سپس محلی</option>
                    </select>
                </div>

                {{-- Min Free GB --}}
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">حداقل فضای آزاد (GB)</label>
                    <input type="number" name="min_free_gb" min="1" max="1000"
                        value="{{ config('installer.recovery_repository.min_free_gb', 10) }}"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
            </div>

            {{-- Failure Policy Info --}}
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                <strong>سیاست خرابی:</strong>
                اگر ذخیره محلی ناموفق باشد → Arvan ادامه می‌دهد.
                اگر Arvan ناموفق باشد → محلی ادامه می‌دهد.
                اگر هر دو ناموفق باشند → عملیات متوقف می‌شود.
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    ذخیره تنظیمات
                </button>
            </div>
        </form>
    </div>

    {{-- ─── Path Validation Result (current status) ─────────────────────────── --}}
    @if(!$status['is_configured'])
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
            <x-core-ui::icon name="exclamation-circle" class="w-5 h-5 text-red-500 mt-0.5 shrink-0" />
            <div>
                <p class="font-semibold text-red-800 text-sm">خزانه محلی پیکربندی نشده</p>
                <p class="text-red-600 text-xs mt-1">{{ $status['validation']['message'] }}</p>
                <p class="text-red-500 text-xs mt-1">پلتفرم در حال استفاده از Arvan است. برای فعال کردن پشتیبان‌گیری محلی، مسیر را وارد کنید.</p>
            </div>
        </div>
    @else
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
            <x-core-ui::icon name="check-circle" class="w-5 h-5 text-green-500 shrink-0" />
            <div>
                <p class="font-semibold text-green-800 text-sm">خزانه محلی فعال</p>
                <p class="text-green-600 text-xs mt-0.5 font-mono">{{ $status['repo_path'] }}</p>
            </div>
        </div>
    @endif

    {{-- ─── Platform Backups ────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-core-ui::icon name="server" class="w-5 h-5 text-slate-500" />
                <h2 class="font-semibold text-slate-800">پشتیبان‌های پلتفرم</h2>
                <span class="text-xs bg-slate-100 text-slate-600 rounded-full px-2 py-0.5">{{ $platformBackups->count() }}</span>
            </div>
        </div>
        @if($platformBackups->isEmpty())
            <div class="px-6 py-8 text-center text-slate-400 text-sm">
                هنوز هیچ پشتیبان پلتفرمی در خزانه محلی ذخیره نشده است.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs">
                        <tr>
                            <th class="px-4 py-3 text-right font-medium">برچسب</th>
                            <th class="px-4 py-3 text-right font-medium">مسیر</th>
                            <th class="px-4 py-3 text-right font-medium">حجم</th>
                            <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                            <th class="px-4 py-3 text-right font-medium">تأیید شده</th>
                            <th class="px-4 py-3 text-right font-medium">تاریخ</th>
                            <th class="px-4 py-3 text-right font-medium">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($platformBackups as $log)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $log->baseline_label ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-400 font-mono max-w-xs truncate" title="{{ $log->local_path }}">{{ $log->local_path }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $log->sizeHuman() }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $log->statusBadge() }}">
                                    {{ $log->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">
                                {{ $log->verified_at ? $log->verified_at->diffForHumans() : '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button onclick="verifyLog({{ $log->id }}, this)"
                                        class="text-xs text-indigo-600 hover:underline">تأیید</button>
                                    <form method="POST"
                                        action="{{ route('admin.infrastructure.recovery-repository.destroy', $log) }}"
                                        onsubmit="return confirm('رکورد و فایل‌های دیسک حذف شوند؟')"
                                        class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:underline">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ─── Customer Backups ────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-core-ui::icon name="users" class="w-5 h-5 text-slate-500" />
                <h2 class="font-semibold text-slate-800">پشتیبان‌های مشتریان</h2>
                <span class="text-xs bg-slate-100 text-slate-600 rounded-full px-2 py-0.5">{{ $customerBackups->count() }}</span>
            </div>
            {{-- Site Filter --}}
            <form method="GET" class="flex items-center gap-2">
                <select name="site_id" onchange="this.form.submit()"
                    class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <option value="">همه سایت‌ها</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" {{ $siteFilter === $site->id ? 'selected' : '' }}>
                            {{ $site->title }} ({{ $site->domain }})
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
        @if($customerBackups->isEmpty())
            <div class="px-6 py-8 text-center text-slate-400 text-sm">
                هنوز هیچ پشتیبان مشتری‌ای در خزانه محلی ذخیره نشده است.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs">
                        <tr>
                            <th class="px-4 py-3 text-right font-medium">سایت</th>
                            <th class="px-4 py-3 text-right font-medium">UUID نصب</th>
                            <th class="px-4 py-3 text-right font-medium">مسیر</th>
                            <th class="px-4 py-3 text-right font-medium">حجم</th>
                            <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                            <th class="px-4 py-3 text-right font-medium">تاریخ</th>
                            <th class="px-4 py-3 text-right font-medium">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($customerBackups as $log)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700">
                                {{ $log->site?->title ?? "Site-{$log->site_id}" }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400">
                                {{ $log->installation_uuid ? substr($log->installation_uuid, 0, 8) . '…' : '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400 font-mono max-w-xs truncate" title="{{ $log->local_path }}">
                                {{ $log->local_path }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $log->sizeHuman() }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $log->statusBadge() }}">
                                    {{ $log->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button onclick="verifyLog({{ $log->id }}, this)"
                                        class="text-xs text-indigo-600 hover:underline">تأیید</button>
                                    <form method="POST"
                                        action="{{ route('admin.infrastructure.recovery-repository.destroy', $log) }}"
                                        onsubmit="return confirm('رکورد و فایل‌های دیسک حذف شوند؟')"
                                        class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:underline">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

<script>
function validatePath() {
    var path    = document.getElementById('localPathInput').value;
    var result  = document.getElementById('pathValidationResult');
    result.classList.remove('hidden');
    result.textContent = 'در حال بررسی…';
    result.className = 'text-xs mt-1 text-slate-500';

    fetch('{{ route('admin.infrastructure.recovery-repository.validate-path') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ path: path }),
    })
    .then(r => r.json())
    .then(data => {
        result.classList.remove('hidden');
        result.textContent = data.message;
        result.className = 'text-xs mt-1 ' + (data.is_valid ? 'text-green-600' : 'text-red-600');
    })
    .catch(() => {
        result.textContent = 'خطا در بررسی مسیر';
        result.className = 'text-xs mt-1 text-red-600';
    });
}

function verifyLog(logId, btn) {
    btn.disabled = true;
    btn.textContent = 'در حال تأیید…';

    fetch('{{ route('admin.infrastructure.recovery-repository.verify', '__ID__') }}'.replace('__ID__', logId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    })
    .then(r => r.json())
    .then(data => {
        btn.textContent = data.pass ? '✓ تأیید شد' : '✗ ناموفق';
        btn.className = 'text-xs ' + (data.pass ? 'text-green-600' : 'text-red-600');
        btn.disabled = false;
        alert(data.message);
    })
    .catch(() => {
        btn.textContent = 'تأیید';
        btn.disabled = false;
    });
}

function verifyAll() {
    if (!confirm('تأیید SHA-256 تمام رکوردها اجرا شود؟')) return;

    fetch('{{ route('admin.infrastructure.recovery-repository.verify-all') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    })
    .then(r => r.json())
    .then(data => {
        alert('نتیجه تأیید: ' + data.passed + ' موفق، ' + data.failed + ' ناموفق از ' + data.total + ' رکورد');
        location.reload();
    });
}
</script>
@endsection
