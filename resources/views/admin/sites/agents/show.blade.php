@extends('layouts.admin')

@section('header', 'ادمین پلتفرم — ' . $site->title)

@push('head')
<style>
.agent-card{background:#fff;border-radius:1.25rem;border:1px solid #e2e8f0;box-shadow:0 2px 16px rgba(15,23,42,.06);padding:1.75rem 2rem}
.agent-badge{display:inline-flex;align-items:center;gap:.375rem;padding:.25rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:600;letter-spacing:.02em}
.agent-badge--active{background:#dcfce7;color:#166534}
.agent-badge--inactive{background:#fee2e2;color:#991b1b}
.agent-info-row{display:flex;align-items:center;gap:.75rem;padding:.625rem 0;border-bottom:1px solid #f1f5f9}
.agent-info-row:last-child{border-bottom:none}
.agent-info-label{font-size:.8rem;color:#64748b;font-weight:500;min-width:9rem;flex-shrink:0}
.agent-info-value{font-size:.9rem;color:#1e293b;font-weight:500;direction:ltr;text-align:right}
.conn-dot{width:.625rem;height:.625rem;border-radius:9999px;display:inline-block}
.conn-dot--ok{background:#22c55e}
.conn-dot--fail{background:#ef4444}
.conn-dot--unknown{background:#94a3b8}
.pw-mask{font-family:monospace;letter-spacing:.1em;color:#6366f1;background:#f5f3ff;padding:.2rem .6rem;border-radius:.375rem;font-size:.9rem}
.action-btn{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1.1rem;border-radius:.625rem;font-size:.85rem;font-weight:600;transition:all .15s;cursor:pointer;border:none}
.action-btn--primary{background:#4f46e5;color:#fff}
.action-btn--primary:hover{background:#4338ca}
.action-btn--danger{background:#ef4444;color:#fff}
.action-btn--danger:hover{background:#dc2626}
.action-btn--outline{background:#fff;color:#374151;border:1px solid #d1d5db}
.action-btn--outline:hover{background:#f9fafb;border-color:#9ca3af}
.action-btn--green{background:#059669;color:#fff}
.action-btn--green:hover{background:#047857}
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.sites.edit', $site) }}" class="text-gray-400 hover:text-indigo-600 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">ادمین پلتفرم</h1>
            <p class="text-sm text-gray-500 mt-0.5">مدیریت دسترسی ادمین پلتفرم به سایت <span class="font-medium text-indigo-600">{{ $site->title }}</span></p>
        </div>
    </div>
</div>

{{-- Flash Password (shown once after provisioning) --}}
@if(session('agent_password'))
<div class="agent-card mb-5" style="border-color:#6366f1;background:linear-gradient(135deg,#f5f3ff,#ede9fe)">
    <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
        </div>
        <div class="flex-1">
            <h3 class="font-bold text-indigo-900 mb-1">رمز عبور تولید شد — فقط یک‌بار نمایش داده می‌شود</h3>
            <p class="text-sm text-indigo-700 mb-3">این رمز را در جای امنی ذخیره کنید. پس از بارگذاری مجدد صفحه قابل مشاهده نیست.</p>
            <div class="flex items-center gap-3">
                <span class="pw-mask text-lg" id="pw-display">{{ session('agent_password') }}</span>
                <button onclick="safeCopy('{{ addslashes(session('agent_password')) }}', this)"
                        class="action-btn action-btn--outline text-xs">
                    کپی رمز
                </button>
            </div>
            <p class="text-xs text-indigo-600 mt-2">موبایل: <span class="font-mono" dir="ltr">{{ $agent?->mobile }}</span> — آدرس ورود: <span class="font-mono" dir="ltr">{{ $site->domain }}/login</span></p>
        </div>
    </div>
</div>
@endif


@if($errors->any())
<div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 mb-5 text-sm">
    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
    <div>
        @foreach($errors->all() as $err)
            <p>{{ $err }}</p>
        @endforeach
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Current Agent Status --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Connection Status --}}
        <div class="agent-card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                    اتصال دیتابیس سایت
                </h2>
                @if($connectionOk === true)
                    <span class="agent-badge agent-badge--active">
                        <span class="conn-dot conn-dot--ok"></span> متصل
                    </span>
                @elseif($connectionOk === false)
                    <span class="agent-badge agent-badge--inactive">
                        <span class="conn-dot conn-dot--fail"></span> خطا در اتصال
                    </span>
                @else
                    <span class="agent-badge" style="background:#f1f5f9;color:#64748b">
                        <span class="conn-dot conn-dot--unknown"></span> اطلاعات DB تنظیم نشده
                    </span>
                @endif
            </div>
            <div class="agent-info-row">
                <span class="agent-info-label">هاست</span>
                <span class="agent-info-value">{{ $site->db_host ?: '—' }}</span>
            </div>
            <div class="agent-info-row">
                <span class="agent-info-label">نام دیتابیس</span>
                <span class="agent-info-value">{{ $site->db_name ?: '—' }}</span>
            </div>
            <div class="agent-info-row">
                <span class="agent-info-label">دامنه سایت</span>
                <span class="agent-info-value">{{ $site->domain ?: '—' }}</span>
            </div>
        </div>

        {{-- Agent Status --}}
        <div class="agent-card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    وضعیت ادمین پلتفرم
                </h2>
                @if($agent && $agent->is_active)
                    <span class="agent-badge agent-badge--active">فعال</span>
                @elseif($agent)
                    <span class="agent-badge agent-badge--inactive">غیرفعال</span>
                @else
                    <span class="agent-badge" style="background:#f1f5f9;color:#64748b">ثبت نشده</span>
                @endif
            </div>

            @if($agent)
            <div class="agent-info-row">
                <span class="agent-info-label">موبایل ادمین</span>
                <span class="agent-info-value font-mono">{{ $agent->mobile }}</span>
            </div>
            <div class="agent-info-row">
                <span class="agent-info-label">ID در سایت مشتری</span>
                <span class="agent-info-value">#{{ $agent->customer_site_user_id }}</span>
            </div>
            <div class="agent-info-row">
                <span class="agent-info-label">ایجاد توسط</span>
                <span class="agent-info-value">{{ $agent->provisionedBy?->name ?? 'نامشخص' }}</span>
            </div>
            <div class="agent-info-row">
                <span class="agent-info-label">تاریخ ایجاد</span>
                <span class="agent-info-value" dir="ltr">{{ $agent->provisioned_at?->format('Y/m/d H:i') ?? '—' }}</span>
            </div>
            <div class="agent-info-row">
                <span class="agent-info-label">آخرین دسترسی</span>
                <span class="agent-info-value" dir="ltr">{{ $agent->last_access_logged_at?->format('Y/m/d H:i') ?? 'ثبت نشده' }}</span>
            </div>
            @if($agent->notes)
            <div class="agent-info-row">
                <span class="agent-info-label">یادداشت</span>
                <span class="agent-info-value">{{ $agent->notes }}</span>
            </div>
            @endif

            {{-- Password reveal --}}
            @if($agent->is_active && $agent->agent_password_enc)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="flex items-center gap-3">
                    <button id="revealBtn" onclick="revealPassword()" class="action-btn action-btn--outline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        نمایش رمز عبور
                    </button>
                    <span id="pwValue" class="pw-mask hidden"></span>
                    <button id="copyBtn" onclick="copyPw()" class="action-btn action-btn--outline hidden text-xs">کپی</button>
                </div>
                <p class="text-xs text-gray-400 mt-2">نمایش رمز در لاگ پلتفرم ثبت می‌شود.</p>
            </div>
            @endif

            @else
            <div class="text-center py-8 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <p class="text-sm font-medium">هنوز ادمین پلتفرم برای این سایت ثبت نشده است</p>
            </div>
            @endif
        </div>

        {{-- Actions for active agent --}}
        @if($agent && $agent->is_active)
        <div class="agent-card">
            <h2 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
                عملیات
            </h2>
            <div class="flex flex-wrap gap-3">
                {{-- Log Access --}}
                <form method="POST" action="{{ route('admin.sites.agents.log-access', $site) }}">
                    @csrf
                    <button type="submit" class="action-btn action-btn--green">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        ثبت دسترسی
                    </button>
                </form>

                {{-- Revoke --}}
                <form method="POST" action="{{ route('admin.sites.agents.revoke', $site) }}"
                      onsubmit="return confirm('آیا از لغو دسترسی ادمین پلتفرم مطمئن هستید؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="action-btn action-btn--danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        لغو دسترسی
                    </button>
                </form>
            </div>
        </div>
        @endif

    </div>

    {{-- Right: Provision Form --}}
    <div class="space-y-5">
        <div class="agent-card">
            <h2 class="font-bold text-gray-800 mb-1 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                {{ $agent && $agent->is_active ? 'بازنشانی رمز' : 'ایجاد ادمین پلتفرم' }}
            </h2>
            <p class="text-xs text-gray-400 mb-4">
                {{ $agent && $agent->is_active
                    ? 'رمز عبور جدیدی تولید شده و در دیتابیس سایت مشتری به‌روز می‌شود.'
                    : 'یک کاربر ادمین در دیتابیس سایت مشتری ایجاد می‌شود.' }}
            </p>

            <form method="POST" action="{{ route('admin.sites.agents.provision', $site) }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">شماره موبایل ادمین</label>
                    <input type="text" name="mobile" dir="ltr"
                           placeholder="09123456789"
                           value="{{ old('mobile', $agent?->mobile) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <p class="text-xs text-gray-400 mt-1">این موبایل برای ورود به پنل سایت مشتری استفاده می‌شود.</p>
                </div>
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت (اختیاری)</label>
                    <textarea name="notes" rows="2"
                              placeholder="دلیل ایجاد دسترسی..."
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">{{ old('notes', $agent?->notes) }}</textarea>
                </div>
                <button type="submit" class="action-btn action-btn--primary w-full justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    {{ $agent && $agent->is_active ? 'بازنشانی رمز عبور' : 'ایجاد ادمین' }}
                </button>
            </form>
        </div>

        {{-- Info box --}}
        <div class="agent-card" style="background:#f8fafc">
            <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2 text-sm">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                راهنما
            </h3>
            <ul class="text-xs text-gray-500 space-y-2 list-none">
                <li class="flex gap-2">
                    <span class="text-indigo-400 font-bold mt-0.5">•</span>
                    <span>ادمین پلتفرم توسط ادمین سایت مشتری قابل حذف نیست.</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-indigo-400 font-bold mt-0.5">•</span>
                    <span>تمام عملیات در لاگ پلتفرم و لاگ سایت مشتری ثبت می‌شود.</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-indigo-400 font-bold mt-0.5">•</span>
                    <span>قبل از ایجاد، اتصال به DB سایت را تأیید کنید.</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-indigo-400 font-bold mt-0.5">•</span>
                    <span>رمز عبور پس از ایجاد فقط یک‌بار نمایش داده می‌شود.</span>
                </li>
            </ul>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
function safeCopy(text, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            if (btn) { var orig = btn.textContent; btn.textContent = '✓ کپی شد'; setTimeout(function(){ btn.textContent = orig; }, 2000); }
        }).catch(function() { fallbackCopy(text, btn); });
    } else {
        fallbackCopy(text, btn);
    }
}
function fallbackCopy(text, btn) {
    var el = document.createElement('textarea');
    el.value = text;
    el.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
    document.body.appendChild(el);
    el.focus();
    el.select();
    try {
        document.execCommand('copy');
        if (btn) { var orig = btn.textContent; btn.textContent = '✓ کپی شد'; setTimeout(function(){ btn.textContent = orig; }, 2000); }
    } catch(e) {
        alert('کپی خودکار پشتیبانی نمی‌شود. لطفاً متن را دستی کپی کنید.');
    }
    document.body.removeChild(el);
}
async function revealPassword() {
    try {
        const res = await fetch('{{ route('admin.sites.agents.reveal-password', $site) }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json',
                       'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
        const data = await res.json();
        document.getElementById('pwValue').textContent = data.password;
        document.getElementById('pwValue').classList.remove('hidden');
        document.getElementById('copyBtn').classList.remove('hidden');
        document.getElementById('revealBtn').classList.add('hidden');
    } catch(e) {
        alert('خطا در دریافت رمز عبور');
    }
}
function copyPw() {
    var pw = document.getElementById('pwValue').textContent;
    safeCopy(pw, document.getElementById('copyBtn'));
}
</script>
@endpush
