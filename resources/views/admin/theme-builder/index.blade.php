@extends('layouts.admin')

@section('header', 'ساختار تم (Theme Builder)')

@section('content')


<div class="flex flex-wrap gap-2 items-center justify-between">
    <div class="flex flex-wrap gap-2 items-center">
        <a href="{{ route('admin.theme-builder.create') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg transition-all"
           style="background:#4f46e5;color:#fff;box-shadow:0 1px 4px rgba(79,70,229,.3)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            ایجاد تم جدید
        </a>
        <a href="{{ route('admin.theme-builder.create', ['site_id' => 0]) }}"
           class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg border transition-all"
           style="background:#f8fafc;color:#374151;border-color:#d1d5db">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
            قالب پلتفرم
        </a>
        <a href="{{ route('admin.theme-builder.ai-form') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg transition-all"
           style="background:linear-gradient(135deg,#6366f1,#9333ea);color:#fff;box-shadow:0 1px 4px rgba(99,102,241,.35)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            ساخت با AI
        </a>
        <a href="{{ route('admin.theme-builder.ai-generator') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg transition-all"
           style="background:linear-gradient(135deg,#7c3aed,#db2777);color:#fff;box-shadow:0 1px 4px rgba(124,58,237,.35)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            مولد هوشمند (TB3 S8)
        </a>
        <a href="{{ route('admin.theme-builder.json-import.form') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg transition-all"
           style="background:linear-gradient(135deg,#06b6d4,#6366f1);color:#fff;box-shadow:0 1px 4px rgba(6,182,212,.35)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/></svg>
            وارد کردن JSON قالب
        </a>
        <form action="{{ route('admin.theme-builder.import') }}" method="post" enctype="multipart/form-data" class="inline-flex items-center gap-2">
            @csrf
            <input type="file" name="file" accept=".json" class="form-control w-auto max-w-50" required>
            <input type="hidden" name="site_id" value="{{ $siteId ?? 0 }}">
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg border transition-all"
                    style="background:#f8fafc;color:#374151;border-color:#d1d5db">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                وارد کردن تم
            </button>
        </form>
    </div>
    <form method="get" class="flex items-center gap-2">
        <label class="text-sm text-gray-600">سایت:</label>
        <select name="site_id" onchange="this.form.submit()" class="form-select w-48">
            <option value="0" {{ ($siteId ?? 0) == 0 ? 'selected' : '' }}>همه / قالب‌های پلتفرم</option>
            @foreach($sites as $s)
                <option value="{{ $s->id }}" {{ ($siteId ?? 0) == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">نام / شناسه</th>
                <th class="p-3 text-right">سایت</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">آخرین تغییر</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($themes as $theme)
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3">
                        <span class="font-medium">{{ $theme->name }}</span>
                        <span class="text-gray-500 block text-xs">{{ $theme->slug }}</span>
                    </td>
                    <td class="p-3">
                        @if($theme->site_id)
                            <a href="{{ route('admin.sites.edit', $theme->site) }}" class="text-indigo-600">{{ $theme->site->title ?? '—' }}</a>
                        @else
                            <span class="text-gray-400">قالب پلتفرم</span>
                        @endif
                    </td>
                    <td class="p-3">
                        @if($theme->isPublished())
                            <span class="text-green-600 font-medium">منتشر شده</span>
                        @else
                            <span class="text-amber-600">پیش‌نویس</span>
                        @endif
                    </td>
                    <td class="p-3 text-gray-600">{{ \App\Helpers\Jalali::formatFa($theme->updated_at, 'Y/m/d H:i') }}</td>
                    <td class="p-3 flex flex-wrap items-center gap-1">
                        <a href="{{ route('admin.theme-builder.canvas', $theme) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition">
                            🎨 قالب‌ساز
                        </a>
                        {{-- دکمه نصب روی سایت --}}
                        <button type="button"
                            onclick="openInstallModal({{ $theme->id }}, '{{ addslashes($theme->name) }}')"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition">
                            🚀 نصب روی سایت
                        </button>
                        <button type="button"
                            onclick="openSyncModal({{ $theme->id }}, '{{ addslashes($theme->name) }}')"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 transition"
                            title="اگر صفحات قالب در سایت مشتری نمایش نمی‌یابد، از این دکمه استفاده کنید">
                            🔄 همگام‌سازی صفحات
                        </button>
                        <a href="{{ route('admin.theme-builder.edit', $theme) }}" class="text-indigo-600 hover:underline text-xs" title="ویرایش خام JSON هدر، فوتر و استایل‌های سراسری">هدر، فوتر و استایل‌ها</a>
                        <a href="{{ route('admin.theme-builder.export', $theme) }}" class="text-indigo-600 hover:underline text-xs">JSON</a>
                        <a href="{{ route('admin.theme-builder.clone-form', $theme) }}" class="text-indigo-600 hover:underline text-xs">کپی</a>
                        <form action="{{ route('admin.theme-builder.destroy', $theme) }}" method="post" class="inline" onsubmit="return confirm('حذف این تم؟');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-xs">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-6 text-center text-gray-500">تمی یافت نشد. یک تم جدید ایجاد کنید یا از فایل JSON وارد کنید.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-3 border-t">{{ $themes->links() }}</div>
</div>

{{-- ── Modal نصب روی سایت ────────────────────────────────────────────── --}}
<div id="install-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        {{-- Header --}}
        <div class="bg-linear-to-r from-emerald-600 to-teal-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center text-white text-lg">🚀</div>
                <div>
                    <h3 class="text-white font-bold text-base">نصب قالب روی سایت</h3>
                    <p id="modal-theme-name" class="text-emerald-100 text-xs mt-0.5"></p>
                </div>
            </div>
            <button onclick="closeInstallModal()" class="text-white/70 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Body --}}
        <form id="install-form" method="POST" action="">
            @csrf
            <div class="p-6 space-y-4">
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 flex items-start gap-2 text-sm text-amber-800">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    قالب فعلی سایت مقصد جایگزین می‌شود. این عمل برگشت‌پذیر نیست مگر از طریق export/import.
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سایت مقصد را انتخاب کنید</label>
                    <select name="site_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">— انتخاب سایت —</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}">
                                {{ $s->title }}
                                @if($s->domain) — {{ $s->domain }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1">
                    <div class="font-medium text-gray-700 mb-1">این قالب شامل:</div>
                    <div>✅ هدر و فوتر کامل</div>
                    <div>✅ ۷ صفحه آماده (خانه، فروشگاه، آموزشگاه، پشتیبانی، پلن‌ها، درباره ما، وبلاگ)</div>
                    <div>✅ بلاک‌های قابل ویرایش با drag-and-drop</div>
                    <div>✅ استایل‌های حرفه‌ای آبی سازمانی</div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="border-t px-6 py-4 flex items-center justify-between bg-gray-50">
                <button type="button" onclick="closeInstallModal()"
                    class="px-4 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                    انصراف
                </button>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    نصب قالب
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Modal همگام‌سازی صفحات ───────────────────────────────────────────── --}}
<div id="sync-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-linear-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center text-white text-lg">🔄</div>
                <div>
                    <h3 class="text-white font-bold text-base">همگام‌سازی صفحات قالب</h3>
                    <p id="sync-modal-theme-name" class="text-blue-100 text-xs mt-0.5"></p>
                </div>
            </div>
            <button onclick="closeSyncModal()" class="text-white/70 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="sync-form" method="POST" action="">
            @csrf
            <div class="p-6 space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex items-start gap-2 text-sm text-blue-800">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    اگر بعد از نصب قالب، صفحات در ماژول صفحات سایت مشتری نمایش نمی‌یابد، از این گزینه برای ایجاد مجدد صفحات استفاده کنید. صفحات موجود دست‌نخورده می‌مانند.
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سایت مقصد را انتخاب کنید</label>
                    <select name="site_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— انتخاب سایت —</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}">{{ $s->title }}@if($s->domain) — {{ $s->domain }}@endif</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="border-t px-6 py-4 flex items-center justify-between bg-gray-50">
                <button type="button" onclick="closeSyncModal()"
                    class="px-4 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                    انصراف
                </button>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    همگام‌سازی صفحات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openInstallModal(themeId, themeName) {
    document.getElementById('modal-theme-name').textContent = themeName;
    document.getElementById('install-form').action = '/admin/theme-builder/' + themeId + '/install-to-site';
    const modal = document.getElementById('install-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeInstallModal() {
    const modal = document.getElementById('install-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
document.getElementById('install-modal').addEventListener('click', function(e) {
    if (e.target === this) closeInstallModal();
});

function openSyncModal(themeId, themeName) {
    document.getElementById('sync-modal-theme-name').textContent = themeName;
    document.getElementById('sync-form').action = '/admin/theme-builder/' + themeId + '/sync-pages';
    const modal = document.getElementById('sync-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeSyncModal() {
    const modal = document.getElementById('sync-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
document.getElementById('sync-modal').addEventListener('click', function(e) {
    if (e.target === this) closeSyncModal();
});
</script>

@endsection
