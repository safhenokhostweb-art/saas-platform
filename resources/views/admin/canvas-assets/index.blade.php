@extends('layouts.admin')

@section('title', 'مدیریت یکپارچه دارایی‌های Canvas')
@section('header', 'مدیریت دارایی‌ها، مارکت‌پلیس و مجوزدهی Canvas')

@php
    use SaaSPlatform\ThemeRuntime\Access\AssetRef;
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    activeTab: '{{ $activeTab ?? 'buttons' }}',
    importModalOpen: false,
    grantModalOpen: false,
    grantAssetKind: '',
    grantAssetKey: '',
    grantAssetTitle: '',
    grantVersion: '',
    versionModalOpen: false,
    versionAssetKind: '',
    versionAssetKey: '',
    versionActionUrl: '',
    versionCurrent: '',
    openGrantModal(kind, key, title, version) {
        this.grantAssetKind = kind;
        this.grantAssetKey = key;
        this.grantAssetTitle = title;
        this.grantVersion = version || '';
        this.grantModalOpen = true;
    },
    openVersionModal(kind, key, actionUrl, current) {
        this.versionAssetKind = kind;
        this.versionAssetKey = key;
        this.versionActionUrl = actionUrl;
        this.versionCurrent = current || '1.0.0';
        this.versionModalOpen = true;
    }
}">

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl flex items-center gap-2 text-sm">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl flex items-center gap-2 text-sm">
            <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm space-y-1">
            <div class="font-bold">لطفاً خطاهای زیر را برطرف کنید:</div>
            <ul class="list-disc list-inside text-xs space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Top Navigation Tabs -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-2 flex flex-wrap gap-2 items-center justify-between">
        <div class="flex flex-wrap gap-1">
            <button type="button" @click="activeTab = 'buttons'"
                    :class="activeTab === 'buttons' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                <span>کتابخانه دکمه‌ها (دسته‌ای)</span>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'buttons' ? 'bg-indigo-700 text-indigo-100' : 'bg-gray-100 text-gray-600'">{{ $buttonPacks->count() }}</span>
            </button>

            <button type="button" @click="activeTab = 'icons'"
                    :class="activeTab === 'icons' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>کتابخانه آیکن‌های اختصاصی (دسته‌ای)</span>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'icons' ? 'bg-indigo-700 text-indigo-100' : 'bg-gray-100 text-gray-600'">{{ $iconPacks->count() }}</span>
            </button>

            <button type="button" @click="activeTab = 'headers'"
                    :class="activeTab === 'headers' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                <span>قالب‌های هدر</span>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'headers' ? 'bg-indigo-700 text-indigo-100' : 'bg-gray-100 text-gray-600'">{{ $headerPresets->count() }}</span>
            </button>

            <button type="button" @click="activeTab = 'footers'"
                    :class="activeTab === 'footers' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 18h16M4 12h16m-7-6h7"></path></svg>
                <span>قالب‌های فوتر</span>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'footers' ? 'bg-indigo-700 text-indigo-100' : 'bg-gray-100 text-gray-600'">{{ $footerPresets->count() }}</span>
            </button>

            <button type="button" @click="activeTab = 'sections'"
                    :class="activeTab === 'sections' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <span>سکشن‌های آماده</span>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'sections' ? 'bg-indigo-700 text-indigo-100' : 'bg-gray-100 text-gray-600'">{{ $sectionPresets->count() }}</span>
            </button>

            <button type="button" @click="activeTab = 'motions'"
                    :class="activeTab === 'motions' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>ویجت‌های موشن گرافیک (تکی)</span>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'motions' ? 'bg-indigo-700 text-indigo-100' : 'bg-gray-100 text-gray-600'">{{ $motions->count() }}</span>
            </button>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" @click="importModalOpen = true"
                    class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                <span>ورود گروهی با JSON</span>
            </button>
            <div class="text-xs text-gray-400 px-1 hidden md:block">
                مرکز کنترل لایسنس، قیمت‌گذاری و نگارش ۴ دارایی Canvas
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 1: BUTTON PACKS -->
    <!-- ===================================================================== -->
    <div x-show="activeTab === 'buttons'" class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">کتابخانه‌های دکمه (Button Libraries)</h2>
                <p class="text-xs text-gray-500 mt-0.5">مدیریت پکیج‌های دکمه (فانتزی، شیشه‌ای، کلاسیک، مدرن) جهت قیمت‌گذاری، انتشار نسخه و فروش دسته‌ای.</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create-button-pack').showModal()"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>افزودن پکیج دکمه جدید</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse($buttonPacks as $pack)
                @php
                    $grantCount = $grantCounts->get(AssetRef::KIND_BUTTON_PACK . ':' . $pack->slug, 0);
                @endphp
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col justify-between hover:border-indigo-200 transition">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 font-medium">{{ $pack->category_label ?: 'عمومی' }}</span>
                                <h3 class="font-bold text-gray-900 text-base mt-1.5">{{ $pack->title }}</h3>
                                <div class="text-xs text-gray-400 font-mono mt-0.5">{{ $pack->slug }}</div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                @if($pack->access_type === 'free')
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">رایگان</span>
                                @else
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200 font-medium">پولی: {{ number_format($pack->price) }} تومان</span>
                                @endif
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono font-bold" dir="ltr">v{{ $pack->current_version }}</span>
                            </div>
                        </div>

                        @if($pack->description)
                            <p class="text-xs text-gray-500 line-clamp-2 leading-5">{{ $pack->description }}</p>
                        @endif

                        <!-- Presets list preview -->
                        <div class="bg-gray-50 rounded-xl p-3 space-y-2 border border-gray-100">
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span class="font-medium">پریست‌های دکمه ({{ $pack->presets_count }})</span>
                                <button type="button" onclick="document.getElementById('modal-add-preset-{{ $pack->id }}').showModal()"
                                        class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">+ افزودن پریست</button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @forelse($pack->presets as $preset)
                                    <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-lg bg-white border border-gray-200 shadow-2xs">
                                        <span>{{ $preset->title }}</span>
                                        <form method="POST" action="{{ route('admin.canvas-assets.button-presets.destroy', $preset) }}" onsubmit="return confirm('این پریست حذف شود؟')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-gray-400 hover:text-rose-600 ml-0.5">×</button>
                                        </form>
                                    </span>
                                @empty
                                    <span class="text-xs text-gray-400 italic">هنوز پریستی افزوده نشده است.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="text-xs text-gray-500 flex items-center justify-between pt-1">
                            <span>اختصاص‌های فعال:</span>
                            <span class="font-bold text-gray-800">{{ $grantCount }} سایت</span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-center justify-between gap-2 mt-4">
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="openGrantModal('{{ AssetRef::KIND_BUTTON_PACK }}', '{{ $pack->slug }}', '{{ $pack->title }}', '{{ $pack->current_version }}')"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium transition">
                                اختصاص به سایت‌ها
                            </button>
                            <button type="button" @click="openVersionModal('{{ AssetRef::KIND_BUTTON_PACK }}', '{{ $pack->slug }}', '{{ route('admin.canvas-assets.button-packs.publish-version', $pack) }}', '{{ $pack->current_version }}')"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition">
                                انتشار نسخه
                            </button>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" onclick="document.getElementById('modal-edit-pack-{{ $pack->id }}').showModal()"
                                    class="p-1.5 text-gray-400 hover:text-indigo-600 transition" title="ویرایش مشخصات">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.canvas-assets.button-packs.destroy', $pack) }}" onsubmit="return confirm('آیا از حذف این پکیج دکمه اطمینان دارید؟')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-rose-600 transition" title="حذف پکیج">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal: Add Preset to Pack -->
                <dialog id="modal-add-preset-{{ $pack->id }}" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-lg border border-gray-100">
                    <form method="POST" action="{{ route('admin.canvas-assets.button-presets.store', $pack) }}" class="space-y-4">
                        @csrf
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="font-bold text-gray-900 text-base">افزودن پریست به پک «{{ $pack->title }}»</h3>
                            <button type="button" onclick="document.getElementById('modal-add-preset-{{ $pack->id }}').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">عنوان پریست</label>
                            <input type="text" name="title" required placeholder="مثلاً: دکمه نئونی صورتی" class="w-full text-sm rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">شناسه یکتا (Slug)</label>
                            <input type="text" name="slug" placeholder="مثلاً: neon-pink" class="w-full text-sm rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 font-mono" dir="ltr">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">توکن‌های استایل CSS (JSON)</label>
                            <textarea name="css_tokens" rows="5" required class="w-full text-xs font-mono rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500" dir="ltr">{
  "padding": "0.75rem 1.75rem",
  "border_radius": "9999px",
  "background": "linear-gradient(135deg, #ec4899, #8b5cf6)",
  "text_color": "#ffffff",
  "font_weight": "600",
  "shadow": "0 8px 20px -4px rgba(236, 72, 153, 0.5)"
}</textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                            <button type="button" onclick="document.getElementById('modal-add-preset-{{ $pack->id }}').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                            <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ذخیره پریست</button>
                        </div>
                    </form>
                </dialog>

                <!-- Modal: Edit Button Pack -->
                <dialog id="modal-edit-pack-{{ $pack->id }}" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-lg border border-gray-100">
                    <form method="POST" action="{{ route('admin.canvas-assets.button-packs.update', $pack) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="font-bold text-gray-900 text-base">ویرایش پک «{{ $pack->title }}»</h3>
                            <button type="button" onclick="document.getElementById('modal-edit-pack-{{ $pack->id }}').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">عنوان پک</label>
                                <input type="text" name="title" value="{{ $pack->title }}" required class="w-full text-sm rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">شناسه یکتا (Slug)</label>
                                <input type="text" name="slug" value="{{ $pack->slug }}" required class="w-full text-sm rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 font-mono" dir="ltr">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">دسته بندی</label>
                                <input type="text" name="category_label" value="{{ $pack->category_label }}" placeholder="مدرن، کلاسیک..." class="w-full text-sm rounded-xl border-gray-200">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">نوع دسترسی</label>
                                <select name="access_type" class="w-full text-sm rounded-xl border-gray-200">
                                    <option value="free" {{ $pack->access_type === 'free' ? 'selected' : '' }}>رایگان (همه سایت‌ها)</option>
                                    <option value="paid" {{ $pack->access_type === 'paid' ? 'selected' : '' }}>پولی (مارکت‌پلیس / نیازمند مجوز)</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">قیمت (تومان)</label>
                                <input type="number" name="price" value="{{ $pack->price }}" class="w-full text-sm rounded-xl border-gray-200">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">قیمت با تخفیف (تومان)</label>
                                <input type="number" name="discount_price" value="{{ $pack->discount_price }}" class="w-full text-sm rounded-xl border-gray-200">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">توضیحات</label>
                            <textarea name="description" rows="2" class="w-full text-xs rounded-xl border-gray-200">{{ $pack->description }}</textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" id="active-{{ $pack->id }}" value="1" {{ $pack->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="active-{{ $pack->id }}" class="text-xs text-gray-700">پکیج فعال و در دسترس باشد</label>
                        </div>
                        <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                            <button type="button" onclick="document.getElementById('modal-edit-pack-{{ $pack->id }}').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                            <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ذخیره تغییرات</button>
                        </div>
                    </form>
                </dialog>
            @empty
                <div class="col-span-full py-12 text-center bg-white rounded-2xl border border-gray-100">
                    <p class="text-sm text-gray-400">هنوز هیچ پکیج دکمه‌ای ثبت نشده است.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal: Create Button Pack -->
    <dialog id="modal-create-button-pack" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-lg border border-gray-100">
        <form method="POST" action="{{ route('admin.canvas-assets.button-packs.store') }}" class="space-y-4">
            @csrf
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 text-base">افزودن پکیج جدید دکمه‌ها</h3>
                <button type="button" onclick="document.getElementById('modal-create-button-pack').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">عنوان پک</label>
                    <input type="text" name="title" required placeholder="مثلاً: دکمه‌های نئونی ۲۰۲۶" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">شناسه یکتا (Slug)</label>
                    <input type="text" name="slug" placeholder="مثلاً: neon-pack" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">دسته بندی</label>
                    <input type="text" name="category_label" placeholder="مدرن، فانتزی، کلاسیک..." class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نوع دسترسی</label>
                    <select name="access_type" class="w-full text-sm rounded-xl border-gray-200">
                        <option value="free">رایگان (عمومی)</option>
                        <option value="paid" selected>پولی (مارکت‌پلیس / اختصاصی)</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">قیمت پکیج (تومان)</label>
                    <input type="number" name="price" value="49000" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نسخه اولیه</label>
                    <input type="text" name="current_version" value="1.0.0" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">توضیحات پکیج</label>
                <textarea name="description" rows="2" placeholder="مجموعه دکمه‌های فانتزی و گرادیان جذاب برای صفحات لندینگ" class="w-full text-xs rounded-xl border-gray-200"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="create-btn-active" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="create-btn-active" class="text-xs text-gray-700">پکیج فعال و در دسترس باشد</label>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('modal-create-button-pack').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ایجاد پکیج دکمه</button>
            </div>
        </form>
    </dialog>

    <!-- ===================================================================== -->
    <!-- TAB 2: ICON PACKS -->
    <!-- ===================================================================== -->
    <div x-show="activeTab === 'icons'" class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">کتابخانه‌های آیکن‌های اختصاصی (Icon Libraries)</h2>
                <p class="text-xs text-gray-500 mt-0.5">مدیریت پکیج‌های آیکن SVG (فانتزی، گل و گیاه، غذاها، پزشکی، معماری) جهت فروش دسته‌ای و استفاده در Canvas.</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create-icon-pack').showModal()"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>افزودن پکیج آیکن جدید</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse($iconPacks as $pack)
                @php
                    $grantCount = $grantCounts->get(AssetRef::KIND_ICON_PACK . ':' . $pack->slug, 0);
                @endphp
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col justify-between hover:border-indigo-200 transition">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 font-medium">{{ $pack->category_label ?: 'عمومی' }}</span>
                                <h3 class="font-bold text-gray-900 text-base mt-1.5">{{ $pack->title }}</h3>
                                <div class="text-xs text-gray-400 font-mono mt-0.5">{{ $pack->slug }}</div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                @if($pack->access_type === 'free')
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">رایگان</span>
                                @else
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200 font-medium">پولی: {{ number_format($pack->price) }} تومان</span>
                                @endif
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono font-bold" dir="ltr">v{{ $pack->current_version }}</span>
                            </div>
                        </div>

                        @if($pack->description)
                            <p class="text-xs text-gray-500 line-clamp-2 leading-5">{{ $pack->description }}</p>
                        @endif

                        <!-- Icons preview grid -->
                        <div class="bg-gray-50 rounded-xl p-3 space-y-2 border border-gray-100">
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span class="font-medium">آیکن‌های پکیج ({{ $pack->items_count }})</span>
                                <button type="button" onclick="document.getElementById('modal-add-icon-{{ $pack->id }}').showModal()"
                                        class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">+ افزودن SVG</button>
                            </div>
                            <div class="flex flex-wrap gap-2 max-h-36 overflow-y-auto p-1">
                                @forelse($pack->items as $item)
                                    <div class="relative group bg-white p-2 rounded-lg border border-gray-200 flex flex-col items-center justify-center w-12 h-12 shadow-2xs">
                                        <div class="w-6 h-6 text-gray-700 flex items-center justify-center">
                                            {!! $item->svg_content !!}
                                        </div>
                                        <form method="POST" action="{{ route('admin.canvas-assets.icon-items.destroy', $item) }}" onsubmit="return confirm('این آیکن حذف شود؟')"
                                              class="absolute -top-1 -right-1 hidden group-hover:block bg-rose-600 text-white rounded-full w-4 h-4 text-center text-[10px] leading-3 cursor-pointer">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full h-full">×</button>
                                        </form>
                                    </div>
                                @empty
                                    <span class="text-xs text-gray-400 italic">هنوز آیکنی اضافه نشده است.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="text-xs text-gray-500 flex items-center justify-between pt-1">
                            <span>اختصاص‌های فعال:</span>
                            <span class="font-bold text-gray-800">{{ $grantCount }} سایت</span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-center justify-between gap-2 mt-4">
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="openGrantModal('{{ AssetRef::KIND_ICON_PACK }}', '{{ $pack->slug }}', '{{ $pack->title }}', '{{ $pack->current_version }}')"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium transition">
                                اختصاص به سایت‌ها
                            </button>
                            <button type="button" @click="openVersionModal('{{ AssetRef::KIND_ICON_PACK }}', '{{ $pack->slug }}', '{{ route('admin.canvas-assets.icon-packs.publish-version', $pack) }}', '{{ $pack->current_version }}')"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition">
                                انتشار نسخه
                            </button>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" onclick="document.getElementById('modal-edit-iconpack-{{ $pack->id }}').showModal()"
                                    class="p-1.5 text-gray-400 hover:text-indigo-600 transition" title="ویرایش">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.canvas-assets.icon-packs.destroy', $pack) }}" onsubmit="return confirm('این پکیج آیکن حذف شود؟')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-rose-600 transition" title="حذف">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal: Add SVG Item to Icon Pack -->
                <dialog id="modal-add-icon-{{ $pack->id }}" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-lg border border-gray-100">
                    <form method="POST" action="{{ route('admin.canvas-assets.icon-items.store', $pack) }}" class="space-y-4">
                        @csrf
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="font-bold text-gray-900 text-base">افزودن آیکن SVG به پک «{{ $pack->title }}»</h3>
                            <button type="button" onclick="document.getElementById('modal-add-icon-{{ $pack->id }}').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">عنوان آیکن</label>
                                <input type="text" name="title" required placeholder="مثلاً: برگ سبز" class="w-full text-sm rounded-xl border-gray-200">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">شناسه یکتا (Slug)</label>
                                <input type="text" name="slug" placeholder="مثلاً: green-leaf" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">کلمات کلیدی جستجو (با کاما)</label>
                            <input type="text" name="keywords" placeholder="برگ، گل، طبیعت، سبز" class="w-full text-sm rounded-xl border-gray-200">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">محتوای کد SVG (پاک‌سازی خودکار تگ‌های اسکریپت)</label>
                            <textarea name="svg_content" rows="6" required placeholder="<svg viewBox='0 0 24 24'>...</svg>" class="w-full text-xs font-mono rounded-xl border-gray-200" dir="ltr"></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                            <button type="button" onclick="document.getElementById('modal-add-icon-{{ $pack->id }}').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                            <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ذخیره آیکن</button>
                        </div>
                    </form>
                </dialog>

                <!-- Modal: Edit Icon Pack -->
                <dialog id="modal-edit-iconpack-{{ $pack->id }}" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-lg border border-gray-100">
                    <form method="POST" action="{{ route('admin.canvas-assets.icon-packs.update', $pack) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="font-bold text-gray-900 text-base">ویرایش پک آیکن «{{ $pack->title }}»</h3>
                            <button type="button" onclick="document.getElementById('modal-edit-iconpack-{{ $pack->id }}').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">عنوان پک</label>
                                <input type="text" name="title" value="{{ $pack->title }}" required class="w-full text-sm rounded-xl border-gray-200">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">شناسه یکتا (Slug)</label>
                                <input type="text" name="slug" value="{{ $pack->slug }}" required class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">دسته بندی</label>
                                <input type="text" name="category_label" value="{{ $pack->category_label }}" class="w-full text-sm rounded-xl border-gray-200">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">نوع دسترسی</label>
                                <select name="access_type" class="w-full text-sm rounded-xl border-gray-200">
                                    <option value="free" {{ $pack->access_type === 'free' ? 'selected' : '' }}>رایگان (عمومی)</option>
                                    <option value="paid" {{ $pack->access_type === 'paid' ? 'selected' : '' }}>پولی (مارکت‌پلیس / اختصاصی)</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">قیمت (تومان)</label>
                                <input type="number" name="price" value="{{ $pack->price }}" class="w-full text-sm rounded-xl border-gray-200">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">قیمت با تخفیف</label>
                                <input type="number" name="discount_price" value="{{ $pack->discount_price }}" class="w-full text-sm rounded-xl border-gray-200">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">توضیحات</label>
                            <textarea name="description" rows="2" class="w-full text-xs rounded-xl border-gray-200">{{ $pack->description }}</textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" id="active-icon-{{ $pack->id }}" value="1" {{ $pack->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="active-icon-{{ $pack->id }}" class="text-xs text-gray-700">پکیج فعال و در دسترس باشد</label>
                        </div>
                        <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                            <button type="button" onclick="document.getElementById('modal-edit-iconpack-{{ $pack->id }}').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                            <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ذخیره تغییرات</button>
                        </div>
                    </form>
                </dialog>
            @empty
                <div class="col-span-full py-12 text-center bg-white rounded-2xl border border-gray-100">
                    <p class="text-sm text-gray-400">هنوز هیچ پکیج آیکنی ثبت نشده است.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal: Create Icon Pack -->
    <dialog id="modal-create-icon-pack" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-lg border border-gray-100">
        <form method="POST" action="{{ route('admin.canvas-assets.icon-packs.store') }}" class="space-y-4">
            @csrf
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 text-base">افزودن پکیج جدید آیکن‌های اختصاصی</h3>
                <button type="button" onclick="document.getElementById('modal-create-icon-pack').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">عنوان پک</label>
                    <input type="text" name="title" required placeholder="مثلاً: پکیج آیکن‌های گل و گیاه" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">شناسه یکتا (Slug)</label>
                    <input type="text" name="slug" placeholder="مثلاً: plants-flowers" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">دسته بندی</label>
                    <input type="text" name="category_label" placeholder="طبیعت، پزشکی، معماری..." class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نوع دسترسی</label>
                    <select name="access_type" class="w-full text-sm rounded-xl border-gray-200">
                        <option value="free">رایگان (عمومی)</option>
                        <option value="paid" selected>پولی (مارکت‌پلیس / اختصاصی)</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">قیمت پکیج (تومان)</label>
                    <input type="number" name="price" value="59000" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نسخه اولیه</label>
                    <input type="text" name="current_version" value="1.0.0" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">توضیحات پکیج</label>
                <textarea name="description" rows="2" placeholder="مجموعه کامل آیکن‌های گل و گیاه برای سایت‌های گل‌فروشی و کشاورزی" class="w-full text-xs rounded-xl border-gray-200"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="create-icon-active" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="create-icon-active" class="text-xs text-gray-700">پکیج فعال و در دسترس باشد</label>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('modal-create-icon-pack').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ایجاد پکیج آیکن</button>
            </div>
        </form>
    </dialog>

    <!-- ===================================================================== -->
    <!-- TAB 3: HEADER PRESETS -->
    <!-- ===================================================================== -->
    <div x-show="activeTab === 'headers'" class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">قالب‌های آماده هدر (Header Presets)</h2>
                <p class="text-xs text-gray-500 mt-0.5">مدیریت قالب‌های هدر چندمنظوره با قابلیت فروش تکی و دسته‌ای و تزریق مستقیم به ویرایشگر Canvas.</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create-header').showModal()"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>افزودن قالب هدر جدید</span>
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-right text-sm">
                <thead class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500">
                    <tr>
                        <th class="py-3.5 px-4 font-semibold">عنوان و شناسه هدر</th>
                        <th class="py-3.5 px-4 font-semibold">کامپوننت ویجت</th>
                        <th class="py-3.5 px-4 font-semibold">نوع دسترسی / قیمت</th>
                        <th class="py-3.5 px-4 font-semibold">نسخه فعال</th>
                        <th class="py-3.5 px-4 font-semibold">اختصاص‌های فعال</th>
                        <th class="py-3.5 px-4 font-semibold text-left">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($headerPresets as $header)
                        @php
                            $grantCount = $grantCounts->get(AssetRef::KIND_HEADER . ':' . $header->slug, 0);
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-900">{{ $header->title }}</div>
                                <div class="text-xs text-gray-400 font-mono">{{ $header->slug }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono text-gray-600">
                                {{ $header->component_alias }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($header->access_type === 'free')
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">رایگان</span>
                                @else
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200 font-medium">{{ number_format($header->price) }} تومان</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono font-bold" dir="ltr">v{{ $header->current_version }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-gray-700 font-bold">
                                {{ $grantCount }} سایت
                            </td>
                            <td class="py-3.5 px-4 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" @click="openGrantModal('{{ AssetRef::KIND_HEADER }}', '{{ $header->slug }}', '{{ $header->title }}', '{{ $header->current_version }}')"
                                            class="text-xs px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium transition">
                                        اختصاص
                                    </button>
                                    <button type="button" @click="openVersionModal('{{ AssetRef::KIND_HEADER }}', '{{ $header->slug }}', '{{ route('admin.canvas-assets.header-presets.publish-version', $header) }}', '{{ $header->current_version }}')"
                                            class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition">
                                        انتشار نسخه
                                    </button>
                                    <form method="POST" action="{{ route('admin.canvas-assets.header-presets.destroy', $header) }}" onsubmit="return confirm('این هدر حذف شود؟')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-gray-400 hover:text-rose-600">✕</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-xs text-gray-400">هنوز قالب هدری ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Create Header Preset -->
    <dialog id="modal-create-header" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-lg border border-gray-100">
        <form method="POST" action="{{ route('admin.canvas-assets.header-presets.store') }}" class="space-y-4">
            @csrf
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 text-base">افزودن قالب هدر جدید</h3>
                <button type="button" onclick="document.getElementById('modal-create-header').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">عنوان هدر</label>
                    <input type="text" name="title" required placeholder="مثلاً: هدر فروشگاهی مدرن" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">شناسه یکتا (Slug)</label>
                    <input type="text" name="slug" required placeholder="مثلاً: header-ecommerce-v1" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نام کامپوننت / Alias</label>
                    <input type="text" name="component_alias" required value="nav_header" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نوع دسترسی</label>
                    <select name="access_type" class="w-full text-sm rounded-xl border-gray-200">
                        <option value="free">رایگان (عمومی)</option>
                        <option value="paid" selected>پولی (مارکت‌پلیس / اختصاصی)</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">قیمت هدر (تومان)</label>
                    <input type="number" name="price" value="89000" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نسخه اولیه</label>
                    <input type="text" name="current_version" value="1.0.0" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">ساختار بلوک‌های Canvas (JSON - اختیاری)</label>
                <textarea name="blocks" rows="3" placeholder='[{"type":"section","props":{...}}]' class="w-full text-xs rounded-xl border-gray-200 font-mono" dir="ltr"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="create-hdr-active" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="create-hdr-active" class="text-xs text-gray-700">قالب فعال و در دسترس باشد</label>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('modal-create-header').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ایجاد قالب هدر</button>
            </div>
        </form>
    </dialog>

    <!-- ===================================================================== -->
    <!-- TAB 4: FOOTER PRESETS -->
    <!-- ===================================================================== -->
    <div x-show="activeTab === 'footers'" class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">قالب‌های آماده فوتر (Footer Presets)</h2>
                <p class="text-xs text-gray-500 mt-0.5">مدیریت قالب‌های فوتر استاندارد با پشتیبانی از بلوک‌های Canvas و واگذاری به سایت‌ها.</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create-footer').showModal()"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>افزودن قالب فوتر جدید</span>
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-right text-sm">
                <thead class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500">
                    <tr>
                        <th class="py-3.5 px-4 font-semibold">عنوان و شناسه فوتر</th>
                        <th class="py-3.5 px-4 font-semibold">دسته‌بندی</th>
                        <th class="py-3.5 px-4 font-semibold">نوع دسترسی / قیمت</th>
                        <th class="py-3.5 px-4 font-semibold">نسخه فعال</th>
                        <th class="py-3.5 px-4 font-semibold">اختصاص‌های فعال</th>
                        <th class="py-3.5 px-4 font-semibold text-left">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($footerPresets as $footer)
                        @php
                            $grantCount = $grantCounts->get(AssetRef::KIND_FOOTER . ':' . $footer->slug, 0);
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-900">{{ $footer->title }}</div>
                                <div class="text-xs text-gray-400 font-mono">{{ $footer->slug }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-gray-600">
                                {{ $footer->category_label }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($footer->access_type === 'free')
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">رایگان</span>
                                @else
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200 font-medium">{{ number_format($footer->price) }} تومان</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono font-bold" dir="ltr">v{{ $footer->current_version }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-gray-700 font-bold">
                                {{ $grantCount }} سایت
                            </td>
                            <td class="py-3.5 px-4 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" @click="openGrantModal('{{ AssetRef::KIND_FOOTER }}', '{{ $footer->slug }}', '{{ $footer->title }}', '{{ $footer->current_version }}')"
                                            class="text-xs px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium transition">
                                        اختصاص
                                    </button>
                                    <button type="button" @click="openVersionModal('{{ AssetRef::KIND_FOOTER }}', '{{ $footer->slug }}', '{{ route('admin.canvas-assets.footer-presets.publish-version', $footer) }}', '{{ $footer->current_version }}')"
                                            class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition">
                                        انتشار نسخه
                                    </button>
                                    <form method="POST" action="{{ route('admin.canvas-assets.footer-presets.destroy', $footer) }}" onsubmit="return confirm('این فوتر حذف شود؟')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-gray-400 hover:text-rose-600">✕</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-xs text-gray-400">هنوز قالب فوتری ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Create Footer Preset -->
    <dialog id="modal-create-footer" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-lg border border-gray-100">
        <form method="POST" action="{{ route('admin.canvas-assets.footer-presets.store') }}" class="space-y-4">
            @csrf
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 text-base">افزودن قالب فوتر جدید</h3>
                <button type="button" onclick="document.getElementById('modal-create-footer').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">عنوان فوتر</label>
                    <input type="text" name="title" required placeholder="مثلاً: فوتر جامع شرکتی" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">شناسه یکتا (Slug)</label>
                    <input type="text" name="slug" required placeholder="مثلاً: footer-corporate-v1" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">دسته‌بندی</label>
                    <input type="text" name="category_label" value="فوتر" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نوع دسترسی</label>
                    <select name="access_type" class="w-full text-sm rounded-xl border-gray-200">
                        <option value="free">رایگان (عمومی)</option>
                        <option value="paid" selected>پولی (مارکت‌پلیس / اختصاصی)</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">قیمت فوتر (تومان)</label>
                    <input type="number" name="price" value="69000" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نسخه اولیه</label>
                    <input type="text" name="current_version" value="1.0.0" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">ساختار بلوک‌های Canvas (JSON - اختیاری)</label>
                <textarea name="blocks" rows="3" placeholder='[{"type":"footer_block","props":{...}}]' class="w-full text-xs rounded-xl border-gray-200 font-mono" dir="ltr"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="create-ftr-active" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="create-ftr-active" class="text-xs text-gray-700">قالب فعال و در دسترس باشد</label>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('modal-create-footer').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ایجاد قالب فوتر</button>
            </div>
        </form>
    </dialog>

    <!-- ===================================================================== -->
    <!-- TAB 5: SECTION PRESETS -->
    <!-- ===================================================================== -->
    <div x-show="activeTab === 'sections'" class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">سکشن‌های آماده (Section Presets)</h2>
                <p class="text-xs text-gray-500 mt-0.5">مدیریت بخش‌های آماده از پیش طراحی‌شده (Hero، ویژگی‌ها، فراخوان، تعرفه‌ها و...).</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create-section').showModal()"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>افزودن سکشن جدید</span>
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-right text-sm">
                <thead class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500">
                    <tr>
                        <th class="py-3.5 px-4 font-semibold">عنوان و شناسه سکشن</th>
                        <th class="py-3.5 px-4 font-semibold">دسته‌بندی</th>
                        <th class="py-3.5 px-4 font-semibold">نوع دسترسی / قیمت</th>
                        <th class="py-3.5 px-4 font-semibold">نسخه فعال</th>
                        <th class="py-3.5 px-4 font-semibold">اختصاص‌های فعال</th>
                        <th class="py-3.5 px-4 font-semibold text-left">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($sectionPresets as $section)
                        @php
                            $grantCount = $grantCounts->get(AssetRef::KIND_SECTION . ':' . $section->slug, 0);
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-900">{{ $section->title }}</div>
                                <div class="text-xs text-gray-400 font-mono">{{ $section->slug }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-gray-600">
                                {{ $section->category_label }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($section->access_type === 'free')
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">رایگان</span>
                                @else
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200 font-medium">{{ number_format($section->price) }} تومان</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono font-bold" dir="ltr">v{{ $section->current_version }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-gray-700 font-bold">
                                {{ $grantCount }} سایت
                            </td>
                            <td class="py-3.5 px-4 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" @click="openGrantModal('{{ AssetRef::KIND_SECTION }}', '{{ $section->slug }}', '{{ $section->title }}', '{{ $section->current_version }}')"
                                            class="text-xs px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium transition">
                                        اختصاص
                                    </button>
                                    <button type="button" @click="openVersionModal('{{ AssetRef::KIND_SECTION }}', '{{ $section->slug }}', '{{ route('admin.canvas-assets.section-presets.publish-version', $section) }}', '{{ $section->current_version }}')"
                                            class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition">
                                        انتشار نسخه
                                    </button>
                                    <form method="POST" action="{{ route('admin.canvas-assets.section-presets.destroy', $section) }}" onsubmit="return confirm('این سکشن حذف شود؟')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-gray-400 hover:text-rose-600">✕</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-xs text-gray-400">هنوز سکشنی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Create Section Preset -->
    <dialog id="modal-create-section" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-lg border border-gray-100">
        <form method="POST" action="{{ route('admin.canvas-assets.section-presets.store') }}" class="space-y-4">
            @csrf
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 text-base">افزودن سکشن جدید</h3>
                <button type="button" onclick="document.getElementById('modal-create-section').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">عنوان سکشن</label>
                    <input type="text" name="title" required placeholder="مثلاً: هیرو سکشن معرفی محصول" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">شناسه یکتا (Slug)</label>
                    <input type="text" name="slug" required placeholder="مثلاً: section-hero-intro-v1" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">دسته‌بندی</label>
                    <input type="text" name="category_label" value="هیرو" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نوع دسترسی</label>
                    <select name="access_type" class="w-full text-sm rounded-xl border-gray-200">
                        <option value="free">رایگان (عمومی)</option>
                        <option value="paid" selected>پولی (مارکت‌پلیس / اختصاصی)</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">قیمت سکشن (تومان)</label>
                    <input type="number" name="price" value="49000" class="w-full text-sm rounded-xl border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">نسخه اولیه</label>
                    <input type="text" name="current_version" value="1.0.0" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">ساختار بلوک‌های Canvas (JSON - اختیاری)</label>
                <textarea name="blocks" rows="3" placeholder='[{"type":"hero","props":{...}}]' class="w-full text-xs rounded-xl border-gray-200 font-mono" dir="ltr"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="create-sec-active" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="create-sec-active" class="text-xs text-gray-700">سکشن فعال و در دسترس باشد</label>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('modal-create-section').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ایجاد سکشن</button>
            </div>
        </form>
    </dialog>

    <!-- ===================================================================== -->
    <!-- TAB 4: MOTION GRAPHICS -->
    <!-- ===================================================================== -->
    <div x-show="activeTab === 'motions'" class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">ویجت‌های موشن گرافیک (Motion Graphics)</h2>
                <p class="text-xs text-gray-500 mt-0.5">مدیریت تجاری و قیمت‌گذاری تکی موشن گرافیک‌ها، صدور لایسنس و انتشار نسخه برای Canvas.</p>
            </div>
            @if(Route::has('admin.motion-graphics.catalog.index'))
                <a href="{{ route('admin.motion-graphics.catalog.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-medium transition flex items-center gap-1.5">
                    <span>کاتالوگ فنی موشن‌ها</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-right text-sm">
                <thead class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500">
                    <tr>
                        <th class="py-3.5 px-4 font-semibold">عنوان موشن گرافیک</th>
                        <th class="py-3.5 px-4 font-semibold">موتور / فرمت</th>
                        <th class="py-3.5 px-4 font-semibold">مدل تجاری / قیمت</th>
                        <th class="py-3.5 px-4 font-semibold">نسخه</th>
                        <th class="py-3.5 px-4 font-semibold">اختصاص‌های فعال</th>
                        <th class="py-3.5 px-4 font-semibold text-left">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($motions as $motion)
                        @php
                            $grantCount = $grantCounts->get(AssetRef::KIND_MOTION . ':' . $motion->slug, 0);
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-900">{{ $motion->name }}</div>
                                <div class="text-xs text-gray-400 font-mono">{{ $motion->slug }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono text-gray-600">
                                {{ $motion->engine ?? 'lottie' }} / {{ $motion->asset_type ?? 'json' }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if(($motion->access_type ?? 'free') === 'free')
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">رایگان</span>
                                @else
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200 font-medium">{{ number_format($motion->price ?? 0) }} تومان</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono font-bold" dir="ltr">v{{ $motion->current_version ?: ($motion->published_version ? '1.0.' . $motion->published_version : '1.0.0') }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-gray-700 font-bold">
                                {{ $grantCount }} سایت
                            </td>
                            <td class="py-3.5 px-4 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" @click="openGrantModal('{{ AssetRef::KIND_MOTION }}', '{{ $motion->slug }}', '{{ $motion->name }}', '{{ $motion->current_version ?: '1.0.0' }}')"
                                            class="text-xs px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium transition">
                                        اختصاص به سایت
                                    </button>
                                    <button type="button" onclick="document.getElementById('modal-edit-motion-{{ $motion->id }}').showModal()"
                                            class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition">
                                        قیمت‌گذاری
                                    </button>
                                    <button type="button" @click="openVersionModal('{{ AssetRef::KIND_MOTION }}', '{{ $motion->slug }}', '{{ route('admin.canvas-assets.motions.publish-version', $motion->id) }}', '{{ $motion->current_version ?: '1.0.0' }}')"
                                            class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition">
                                        انتشار نسخه
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal: Edit Motion Commercial -->
                        <dialog id="modal-edit-motion-{{ $motion->id }}" class="modal rounded-2xl p-6 bg-white shadow-xl backdrop:bg-black/40 w-full max-w-md border border-gray-100">
                            <form method="POST" action="{{ route('admin.canvas-assets.motions.commercial.update', $motion->id) }}" class="space-y-4">
                                @csrf
                                @method('PUT')
                                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                    <h3 class="font-bold text-gray-900 text-base">تنظیمات تجاری موشن «{{ $motion->name }}»</h3>
                                    <button type="button" onclick="document.getElementById('modal-edit-motion-{{ $motion->id }}').close()" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">نوع دسترسی</label>
                                    <select name="access_type" class="w-full text-sm rounded-xl border-gray-200">
                                        <option value="free" {{ ($motion->access_type ?? 'free') === 'free' ? 'selected' : '' }}>رایگان (عمومی)</option>
                                        <option value="paid" {{ ($motion->access_type ?? '') === 'paid' ? 'selected' : '' }}>پولی (مارکت‌پلیس / اختصاصی)</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">قیمت (تومان)</label>
                                        <input type="number" name="price" value="{{ $motion->price ?? 0 }}" class="w-full text-sm rounded-xl border-gray-200">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">قیمت با تخفیف</label>
                                        <input type="number" name="discount_price" value="{{ $motion->discount_price ?? '' }}" class="w-full text-sm rounded-xl border-gray-200">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">نسخه فعلی</label>
                                    <input type="text" name="current_version" value="{{ $motion->current_version ?: '1.0.0' }}" class="w-full text-sm rounded-xl border-gray-200 font-mono" dir="ltr">
                                </div>
                                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                                    <button type="button" onclick="document.getElementById('modal-edit-motion-{{ $motion->id }}').close()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                                    <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">ذخیره قیمت</button>
                                </div>
                            </form>
                        </dialog>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-xs text-gray-400">موشن گرافیکی در پایگاه‌داده یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- GLOBAL MODAL: GRANT ASSET TO SITE(S) -->
    <!-- ===================================================================== -->
    <div x-show="grantModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" style="display: none;">
        <div @click.away="grantModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100 space-y-4 text-right">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-gray-900 text-base">اختصاص دارایی به سایت‌های مشتری</h3>
                    <div class="text-xs text-gray-500 mt-0.5">
                        دارایی: <span class="font-bold text-indigo-700" x-text="grantAssetTitle"></span>
                        (<span class="font-mono text-gray-400" x-text="grantAssetKind + ':' + grantAssetKey"></span>)
                    </div>
                </div>
                <button type="button" @click="grantModalOpen = false" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
            </div>

            <form method="POST" action="{{ route('admin.canvas-assets.grants.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="asset_kind" :value="grantAssetKind">
                <input type="hidden" name="asset_key" :value="grantAssetKey">
                <input type="hidden" name="version" :value="grantVersion">

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-medium text-gray-700">انتخاب سایت‌های مشتری (یک یا چند سایت)</label>
                        <span class="text-[11px] text-gray-400">{{ $sites->count() }} سایت موجود</span>
                    </div>
                    <div class="max-h-52 overflow-y-auto border border-gray-200 rounded-xl p-2 space-y-1 bg-gray-50/50">
                        @foreach($sites as $site)
                            <label class="flex items-center gap-2 p-1.5 hover:bg-white rounded-lg cursor-pointer text-xs transition">
                                <input type="checkbox" name="site_ids[]" value="{{ $site->id }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="font-medium text-gray-800">{{ $site->title }}</span>
                                <span class="text-gray-400 text-[11px] font-mono mr-auto" dir="ltr">{{ $site->domain }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">توضیحات یا شناسه فاکتور / سفارش (اختیاری)</label>
                    <input type="text" name="reference" placeholder="مثلاً: هدیه ثبت نام / سفارش پلن VIP" class="w-full text-sm rounded-xl border-gray-200">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                    <button type="button" @click="grantModalOpen = false" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                    <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-sm">تأیید و بازگشایی دسترسی</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- GLOBAL MODAL: PUBLISH NEW VERSION -->
    <!-- ===================================================================== -->
    <div x-show="versionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" style="display: none;">
        <div @click.away="versionModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 space-y-4 text-right">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-gray-900 text-base">انتشار نسخه جدید دارایی</h3>
                    <div class="text-xs text-gray-500 mt-0.5">
                        دارایی: <span class="font-mono text-indigo-700" x-text="versionAssetKey"></span>
                        (نسخه فعلی: <span class="font-mono text-gray-600" x-text="versionCurrent"></span>)
                    </div>
                </div>
                <button type="button" @click="versionModalOpen = false" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
            </div>

            <form method="POST" :action="versionActionUrl" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">شماره نگارش جدید (SemVer استاندارد)</label>
                    <input type="text" name="version" required placeholder="مثلاً: 1.1.0" class="w-full text-sm rounded-xl border-gray-200 font-mono text-center" dir="ltr">
                    <span class="text-[11px] text-gray-400 mt-1 block">باید طبق ساختار Major.Minor.Patch و بزرگتر از نسخه فعلی باشد.</span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">تاریخچه تغییرات این نگارش (Changelog)</label>
                    <textarea name="changelog" rows="3" required placeholder="مثلاً: افزودن پریست‌های جدید نئونی، بهبود گرادیان‌ها و انیمیشن هاور" class="w-full text-xs rounded-xl border-gray-200"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                    <button type="button" @click="versionModalOpen = false" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                    <button type="submit" class="px-4 py-2 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-sm">انتشار نسخه جدید</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: BULK JSON IMPORT -->
    <!-- ===================================================================== -->
    <div x-show="importModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="importModalOpen = false" class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-gray-100 space-y-4 text-right">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">ورود گروهی دارایی‌های Canvas (JSON)</h3>
                        <p class="text-xs text-gray-500 mt-0.5">بارگذاری یا وارد کردن دسته‌ای پک‌های دکمه، آیکن، هدر و موشن با نسخه‌بندی SemVer</p>
                    </div>
                </div>
                <button type="button" @click="importModalOpen = false" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
            </div>

            <form method="POST" action="{{ route('admin.canvas-assets.import-json') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 space-y-1">
                    <div class="font-semibold text-slate-800">راهنمای ساختار فایل JSON:</div>
                    <div>می‌توانید کلیدهای <code class="bg-slate-200 px-1 py-0.5 rounded text-indigo-700">button_packs</code>، <code class="bg-slate-200 px-1 py-0.5 rounded text-indigo-700">icon_packs</code>، <code class="bg-slate-200 px-1 py-0.5 rounded text-indigo-700">header_presets</code> و <code class="bg-slate-200 px-1 py-0.5 rounded text-indigo-700">motion_graphics</code> را به صورت ترکیبی یا مجزا وارد نمایید. فیلدهای <code class="bg-slate-200 px-1 py-0.5 rounded">version</code> و <code class="bg-slate-200 px-1 py-0.5 rounded">changelog</code> خودکار در سیستم نسخه‌بندی ثبت می‌شوند.</div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">بارگذاری فایل JSON</label>
                    <input type="file" name="json_file" accept=".json,application/json,text/plain"
                           class="w-full text-xs text-slate-500 file:mr-2 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 border border-gray-200 rounded-xl p-1.5">
                </div>

                <div class="text-center text-xs text-gray-400 font-semibold">— یا چسباندن متن مستقیم JSON —</div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">متن محتوای JSON</label>
                    <textarea name="json_text" rows="6" dir="ltr" placeholder="{ &quot;button_packs&quot;: [...], &quot;icon_packs&quot;: [...] }"
                              class="w-full text-xs font-mono rounded-xl border-gray-200 bg-gray-50 focus:bg-white"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                    <button type="button" @click="importModalOpen = false" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">انصراف</button>
                    <button type="submit" class="px-5 py-2 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>شروع پردازش و ورود اطلاعات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
