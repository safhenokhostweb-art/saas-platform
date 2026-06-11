@extends('layouts.admin')
@section('title', 'کتابخانه آیکن‌ها')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                <x-core-ui::icon name="spark" class="w-5 h-5 text-indigo-600" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">کتابخانه آیکن‌ها</h1>
                <p class="text-sm text-gray-500">{{ count($icons) }} آیکن Duotone منحصر‌به‌فرد پلتفرم</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <input
                type="text"
                id="icon-search"
                placeholder="جستجو در آیکن‌ها..."
                class="px-4 py-2 text-sm border border-gray-200 rounded-xl w-56 focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                oninput="filterIcons(this.value)"
            />
            <select id="size-select" onchange="changeSizePreview(this.value)"
                class="px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white">
                <option value="4">16px</option>
                <option value="5" selected>20px</option>
                <option value="6">24px</option>
                <option value="8">32px</option>
                <option value="10">40px</option>
            </select>
        </div>
    </div>

    {{-- Usage info --}}
    <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <x-core-ui::icon name="info-circle" class="w-5 h-5 text-indigo-600 mt-0.5 shrink-0" />
            <div class="text-sm text-indigo-800 space-y-1">
                <p class="font-semibold">نحوه استفاده</p>
                <code class="block bg-indigo-100 rounded-lg px-3 py-2 font-mono text-xs text-indigo-900 mt-1 ltr">
                    &lt;x-core-ui::icon name="home" class="w-5 h-5 text-blue-600" /&gt;
                </code>
            </div>
        </div>
    </div>

    {{-- Categories --}}
    @php
        $categories = [
            'ناوبری'         => ['home','dashboard','menu','search','filter','close','chevron-right','chevron-left','chevron-up','chevron-down','arrow-right','arrow-left','arrow-up','arrow-down','external-link','grid','list','sort','sidebar'],
            'اقدامات'        => ['plus','plus-circle','minus','edit','edit-box','trash','copy','save','download','upload','refresh','settings','dots-horizontal','dots-vertical','check','eye','eye-off','lock','unlock','share','link'],
            'کاربران'        => ['user','users','user-plus','user-check','login','logout','shield','shield-check','key','crown','profile'],
            'آموزش'          => ['book','book-open','graduation','courses','lesson','video-lesson','quiz','certificate','star','assignment','classroom','learning-path','progress','walkthrough'],
            'تجارت'          => ['cart','shopping-bag','tag','price-tag','credit-card','receipt','invoice','coupon','subscription','plan','wallet','order','store','package','referral'],
            'ارتباطات'       => ['bell','bell-ring','message','message-circle','sms','megaphone','broadcast','announcement','mail','phone'],
            'فایل و رسانه'   => ['file','file-text','image','video','music','folder','folder-open','paperclip','pdf'],
            'آمار و گزارش'   => ['bar-chart','line-chart','pie-chart','trending-up','trending-down','analytics','report','activity','stats'],
            'وضعیت'          => ['check-circle','x-circle','alert-triangle','info-circle','loader','clock','calendar','badge-check','flag'],
            'زیرساخت'        => ['server','database','cloud','cloud-upload','backup','rocket','monitor','terminal','code','api','webhook','log','deploy','cpu'],
            'متفرقه'         => ['globe','map-pin','sun','moon','help','spark','zap','ai','puzzle','tenant','module'],
        ];
    @endphp

    @foreach($categories as $label => $names)
    <div class="category-section" data-label="{{ $label }}">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
            <span>{{ $label }}</span>
            <span class="text-xs font-normal text-gray-400">({{ count($names) }})</span>
        </h2>
        <div class="grid grid-cols-5 sm:grid-cols-8 md:grid-cols-10 lg:grid-cols-12 xl:grid-cols-14 gap-2">
            @foreach($names as $iconName)
            <div
                class="icon-card group flex flex-col items-center gap-1.5 p-3 bg-white rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 cursor-pointer transition-all duration-150 select-none"
                data-name="{{ $iconName }}"
                onclick="copyIconCode('{{ $iconName }}')"
                title="{{ $iconName }}"
            >
                <x-core-ui::icon :name="$iconName" class="icon-preview w-5 h-5 text-gray-600 group-hover:text-indigo-600 transition-colors" />
                <span class="text-[10px] text-gray-400 group-hover:text-indigo-500 text-center leading-tight truncate w-full text-center ltr">{{ $iconName }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    {{-- Empty state --}}
    <div id="no-results" class="hidden text-center py-16 text-gray-400">
        <x-core-ui::icon name="search" class="w-12 h-12 mx-auto mb-3 text-gray-300" />
        <p class="text-sm">آیکنی با این نام یافت نشد</p>
    </div>

</div>

{{-- Toast notification --}}
<div id="copy-toast"
    class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-sm px-4 py-2 rounded-xl shadow-lg opacity-0 transition-opacity duration-200 pointer-events-none flex items-center gap-2 z-50"
>
    <x-core-ui::icon name="check-circle" class="w-4 h-4 text-green-400" />
    <span id="copy-toast-text">کد کپی شد</span>
</div>

@push('scripts')
<script>
let currentSize = '5';

function changeSizePreview(val) {
    currentSize = val;
    const sizeMap = { '4': 'w-4 h-4', '5': 'w-5 h-5', '6': 'w-6 h-6', '8': 'w-8 h-8', '10': 'w-10 h-10' };
    document.querySelectorAll('.icon-preview').forEach(el => {
        el.className = el.className.replace(/w-\d+\s+h-\d+/, sizeMap[val] || 'w-5 h-5');
    });
}

function filterIcons(query) {
    const q = query.trim().toLowerCase();
    let total = 0;
    document.querySelectorAll('.icon-card').forEach(card => {
        const name = card.dataset.name;
        const show = !q || name.includes(q);
        card.style.display = show ? '' : 'none';
        if (show) total++;
    });

    document.querySelectorAll('.category-section').forEach(section => {
        const visible = [...section.querySelectorAll('.icon-card')].some(c => c.style.display !== 'none');
        section.style.display = visible ? '' : 'none';
    });

    document.getElementById('no-results').classList.toggle('hidden', total > 0);
}

function copyIconCode(name) {
    const code = `<x-core-ui::icon name="${name}" class="w-5 h-5 text-current" />`;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code);
    } else {
        const el = document.createElement('textarea');
        el.value = code;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
    showToast(`کپی شد: ${name}`);
}

function showToast(msg) {
    const toast = document.getElementById('copy-toast');
    document.getElementById('copy-toast-text').textContent = msg;
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 2000);
}
</script>
@endpush
@endsection
