@extends('layouts.admin')

@section('header', 'سیستم تاریخچه بروزرسانی‌ها و امکانات جدید — ' . $site->title)

@section('content')
<div class="max-w-6xl mx-auto py-5 space-y-6" x-data="{ activeTab: 'create', updateScope: 'fleet' }">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-600">
            <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 hover:underline">سایت‌ها</a>
            <span class="mx-1 text-gray-400">/</span>
            <a href="{{ route('admin.sites.edit', $site) }}" class="text-indigo-600 hover:underline">{{ $site->title }}</a>
            <span class="mx-1 text-gray-400">/</span>
            <span class="text-gray-800 font-semibold">مدیریت و ثبت بروزرسانی‌ها</span>
        </p>
        <div class="flex items-center gap-2">
            <button type="button" @click="activeTab = 'create'" :class="activeTab === 'create' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition shadow-sm">
                ➕ ثبت بروزرسانی جدید
            </button>
            <button type="button" @click="activeTab = 'fleet'" :class="activeTab === 'fleet' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition shadow-sm">
                🚀 بروزرسانی‌های سراسری ناوگان ({{ $fleetUpdates->count() }})
            </button>
            <button type="button" @click="activeTab = 'site'" :class="activeTab === 'site' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition shadow-sm">
                🏢 بروزرسانی‌های این سایت ({{ $siteUpdates->count() }})
            </button>
            <button type="button" @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition shadow-sm">
                📋 تمام لاگ‌ها ({{ $allUpdates->total() }})
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center gap-2">
            <span>✅</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- TAB 1: FORM CREATE UPDATE --}}
    <div x-show="activeTab === 'create'" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-5">
        <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900">ثبت لاگ بروزرسانی و امکانات جدید</h3>
                <p class="text-xs text-gray-500 mt-0.5">امکان انتشار سراسری برای تمام ناوگان یا به صورت اختصاصی برای سایت جاری</p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                <span>نسخه کنونی ناوگان:</span>
                <span class="font-mono font-bold">v1.4.0</span>
            </span>
        </div>

        <form method="POST" action="{{ route('admin.sites.updates.store', $site) }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">دامنه توزیع (محدوده بروزرسانی) <span class="text-rose-500">*</span></label>
                    <select name="scope" x-model="updateScope" class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 p-2 font-medium">
                        <option value="fleet">سراسری برای کل ناوگان (Global Fleet Update)</option>
                        <option value="site">اختصاصی فقط برای این سایت ({{ $site->title }})</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">نسخه معنایی (SemVer) <span class="text-rose-500">*</span></label>
                    <input type="text" name="version" class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 p-2 font-mono" placeholder="مثال: v1.4.0 یا v1.4.1" value="{{ old('version') }}" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">نوع بروزرسانی</label>
                    <select name="update_type" class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 p-2">
                        <option value="feature">امکان جدید (New Feature)</option>
                        <option value="improvement">بهبود و ارتقا (Improvement)</option>
                        <option value="security">امنیتی (Security)</option>
                        <option value="fix">رفع ایراد (Bug Fix)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">عنوان فارسی بروزرسانی <span class="text-rose-500">*</span></label>
                    <input type="text" name="title" class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 p-2" placeholder="مثال: هدر شناور شیشه‌ای مدرن و پلتفرم Canvas" value="{{ old('title') }}" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">تاریخ شمسی انتشار</label>
                    <x-persian-date-input name="shamsi_date" :value="old('shamsi_date', \App\Helpers\Jalali::format(now()))" placeholder="۱۴۰۵/۰۹/۰۸" class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 p-2" />
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">دسته‌بندی امکانات و تغییرات (JSON ساختاریافته - اختیاری)</label>
                <textarea name="features_json" rows="4" class="w-full text-xs font-mono rounded-lg border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 p-2 ltr text-left" placeholder='[{"category":"هدر شیشه‌ای","items":["پوسته جدید glass_nimbus","کشوی موبایل RTL"]}]'>{{ old('features_json') }}</textarea>
                <p class="text-[11px] text-gray-500 mt-1">فرمت اختیاری JSON شامل آرایه‌ای از دسته‌ها (`category`) و لیست موارد (`items`) برای نمایش ساختاریافته در داشبورد مشتریان.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">یادداشت و توضیحات کلی بروزرسانی</label>
                <textarea name="notes" rows="3" class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 p-2" placeholder="توضیحات و خلاصه تغییرات این نسخه...">{{ old('notes') }}</textarea>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-md">
                    <span>🚀</span>
                    <span>انتشار و ثبت لاگ بروزرسانی</span>
                </button>
            </div>
        </form>
    </div>

    {{-- TAB 2: FLEET UPDATES --}}
    <div x-show="activeTab === 'fleet'" class="space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-bold text-gray-800">تاریخچه بروزرسانی‌های سراسری ناوگان (Fleet Updates)</h4>
            <span class="text-xs text-gray-500">قابل مشاهده برای تمام سایت‌ها و مشتریان</span>
        </div>

        @if($fleetUpdates->isEmpty())
            <div class="bg-white rounded-2xl p-8 text-center border border-gray-200 text-gray-400 text-sm">
                هیچ بروزرسانی سراسری هنوز ثبت نشده است.
            </div>
        @else
            <div class="space-y-3">
                @foreach($fleetUpdates as $update)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-3">
                        <div class="flex items-start justify-between flex-wrap gap-2 border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-100 text-indigo-800 font-mono">
                                    {{ $update->version ?: $update->version_label }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $update->update_type === 'feature' ? 'bg-emerald-100 text-emerald-800' : ($update->update_type === 'security' ? 'bg-rose-100 text-rose-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ $update->getTypeLabel() }}
                                </span>
                                <h5 class="text-sm font-bold text-gray-900">{{ $update->title ?: 'بروزرسانی' }}</h5>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-500 font-medium">📅 {{ $update->shamsi_date ?: ($update->distributed_at ? \App\Helpers\Jalali::formatFa($update->distributed_at) : '—') }}</span>
                                <form method="POST" action="{{ route('admin.sites.updates.destroy', $update) }}" onsubmit="return confirm('آیا از حذف این بروزرسانی اطمینان دارید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-600 hover:text-rose-800 font-bold px-2 py-1 rounded hover:bg-rose-50 transition">حذف</button>
                                </form>
                            </div>
                        </div>

                        @if(!empty($update->notes))
                            <p class="text-xs text-gray-600 leading-relaxed">{{ $update->notes }}</p>
                        @endif

                        @if(!empty($update->features) && is_array($update->features))
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
                                @foreach($update->features as $group)
                                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
                                        <div class="text-xs font-bold text-indigo-900 mb-2">{{ $group['category'] ?? 'امکانات' }}</div>
                                        <ul class="text-[11px] text-gray-600 space-y-1">
                                            @foreach($group['items'] ?? [] as $item)
                                                <li class="flex items-start gap-1.5">
                                                    <span class="text-emerald-500 font-bold">✓</span>
                                                    <span>{{ $item }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- TAB 3: SITE UPDATES --}}
    <div x-show="activeTab === 'site'" class="space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-bold text-gray-800">بروزرسانی‌های اختصاصی سایت: {{ $site->title }}</h4>
            <span class="text-xs text-gray-500">فقط توسط این سایت دریافت می‌شود</span>
        </div>

        @if($siteUpdates->isEmpty())
            <div class="bg-white rounded-2xl p-8 text-center border border-gray-200 text-gray-400 text-sm">
                هیچ بروزرسانی اختصاصی برای این سایت ثبت نشده است.
            </div>
        @else
            <div class="space-y-3">
                @foreach($siteUpdates as $update)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-3">
                        <div class="flex items-start justify-between flex-wrap gap-2 border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-100 text-amber-900 font-mono">
                                    {{ $update->version ?: $update->version_label }}
                                </span>
                                <h5 class="text-sm font-bold text-gray-900">{{ $update->title ?: 'بروزرسانی سایت' }}</h5>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-500 font-medium">📅 {{ $update->shamsi_date ?: ($update->distributed_at ? \App\Helpers\Jalali::formatFa($update->distributed_at) : '—') }}</span>
                                <form method="POST" action="{{ route('admin.sites.updates.destroy', $update) }}" onsubmit="return confirm('آیا از حذف این بروزرسانی اطمینان دارید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-600 hover:text-rose-800 font-bold px-2 py-1 rounded hover:bg-rose-50 transition">حذف</button>
                                </form>
                            </div>
                        </div>
                        @if(!empty($update->notes))
                            <p class="text-xs text-gray-600 leading-relaxed">{{ $update->notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- TAB 4: ALL LOGS --}}
    <div x-show="activeTab === 'all'" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-xs text-right">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold">
                <tr>
                    <th class="p-3">نسخه</th>
                    <th class="p-3">عنوان بروزرسانی</th>
                    <th class="p-3">دامنه انتشار</th>
                    <th class="p-3">نوع</th>
                    <th class="p-3">تاریخ شمسی</th>
                    <th class="p-3">توضیحات</th>
                    <th class="p-3 text-center">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-medium">
                @forelse($allUpdates as $update)
                    <tr class="hover:bg-gray-50/70 transition">
                        <td class="p-3 font-mono font-bold text-gray-900">{{ $update->version ?: $update->version_label }}</td>
                        <td class="p-3 font-semibold text-gray-800">{{ $update->title ?: '—' }}</td>
                        <td class="p-3">
                            @if($update->isFleet())
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800">کل ناوگان</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-800">{{ $update->site?->title ?? 'سایت #' . $update->site_id }}</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $update->update_type === 'feature' ? 'bg-emerald-100 text-emerald-800' : ($update->update_type === 'security' ? 'bg-rose-100 text-rose-800' : 'bg-blue-100 text-blue-800') }}">
                                {{ $update->getTypeLabel() }}
                            </span>
                        </td>
                        <td class="p-3 text-gray-600">{{ $update->shamsi_date ?: ($update->distributed_at ? \App\Helpers\Jalali::formatFa($update->distributed_at) : '—') }}</td>
                        <td class="p-3 text-gray-500 max-w-xs truncate">{{ $update->notes ?: '—' }}</td>
                        <td class="p-3 text-center">
                            <form method="POST" action="{{ route('admin.sites.updates.destroy', $update) }}" onsubmit="return confirm('آیا از حذف این بروزرسانی اطمینان دارید؟');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-800 font-bold px-2 py-1 rounded hover:bg-rose-50 transition">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-gray-400">هیچ لاگ بروزرسانی یافت نشد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($allUpdates->hasPages())
            <div class="p-3 border-t border-gray-100">
                {{ $allUpdates->links() }}
            </div>
        @endif
    </div>
</div>

@push('head')
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
@endpush
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.persian-date-input') && typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({ format: 'YYYY/MM/DD', persianDigit: true, initialValueType: 'persian' });
    }
});
</script>
@endpush
@endsection
