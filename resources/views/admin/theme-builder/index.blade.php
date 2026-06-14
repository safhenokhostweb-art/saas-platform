@extends('layouts.admin')

@section('header', 'ساختار تم (Theme Builder)')

@section('content')

{{-- flash messages --}}
@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2">
    <svg class="w-5 h-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg flex items-center gap-2">
    <svg class="w-5 h-5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    {{ session('error') }}
</div>
@endif

<div class="flex flex-wrap gap-2 items-center justify-between">
    <div class="flex flex-wrap gap-2 items-center">
        <a href="{{ route('admin.theme-builder.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            ایجاد تم جدید
        </a>
        <a href="{{ route('admin.theme-builder.create', ['site_id' => 0]) }}" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded hover:bg-slate-50">
            قالب پلتفرم
        </a>
        <a href="{{ route('admin.theme-builder.ai-form') }}"
           class="inline-flex items-center gap-2 bg-linear-to-r from-indigo-500 to-purple-600 text-white px-4 py-2 rounded hover:from-indigo-600 hover:to-purple-700 font-medium shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            ساخت با AI
        </a>
        <form action="{{ route('admin.theme-builder.import') }}" method="post" enctype="multipart/form-data" class="inline-flex items-center gap-2">
            @csrf
            <input type="file" name="file" accept=".json" class="form-control w-auto max-w-50" required>
            <input type="hidden" name="site_id" value="{{ $siteId ?? 0 }}">
            <button type="submit" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded hover:bg-slate-50">وارد کردن تم</button>
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
                    <td class="p-3 text-gray-600">{{ $theme->updated_at?->format('Y/m/d H:i') }}</td>
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
                        <a href="{{ route('admin.theme-builder.edit', $theme) }}" class="text-indigo-600 hover:underline text-xs">ویرایش</a>
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
// بستن با کلیک روی پس‌زمینه
document.getElementById('install-modal').addEventListener('click', function(e) {
    if (e.target === this) closeInstallModal();
});
</script>

@endsection
