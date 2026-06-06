@extends('layouts.app')
@section('title', 'پروفایل من')

@section('head')
    @if($mapField)
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="">
    <style>
        .map-wrapper { direction:ltr;width:100%;position:relative;overflow:hidden;border-radius:.75rem;border:1px solid #e5e7eb;background:#e5e7eb; }
        #map { width:100%!important;height:320px!important;min-height:320px;position:relative!important;z-index:0; }
        .map-search-wrap { position:relative; }
        .map-suggestions { position:absolute;top:100%;right:0;left:0;margin-top:4px;background:#fff;border:1px solid #e5e7eb;border-radius:.5rem;box-shadow:0 10px 15px -3px rgba(0,0,0,.1);max-height:220px;overflow-y:auto;z-index:1000; }
        .map-suggestion-item { padding:10px 14px;cursor:pointer;text-align:right;direction:rtl;border-bottom:1px solid #f3f4f6;transition:background .15s; }
        .map-suggestion-item:hover,.map-suggestion-item.active { background:#eff6ff; }
        .map-suggestion-item:last-child { border-bottom:none; }
        .map-suggestion-loading { padding:12px;text-align:center;color:#6b7280; }
        .leaflet-container { font-family:inherit;width:100%!important;height:100%!important; }
    </style>
    @endif
@endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-5" x-data="{
    isSaving: false,
    showSuccess: false,
    avatarPreview: null,
    previewAvatar(e) {
        const f = e.target.files[0];
        if (!f) return;
        const r = new FileReader();
        r.onload = ev => { this.avatarPreview = ev.target.result; };
        r.readAsDataURL(f);
    }
}">


    @if($errors->any())
    <div class="flex flex-col gap-1 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-sm">
        <p class="font-semibold mb-1">خطاهایی رخ داد:</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @php
        $avatarUrl   = $profile->avatar ? url($profile->avatar) : null;
        $displayName = $profile->full_name ?: auth()->user()->mobile;
        $initial     = mb_substr($displayName, 0, 1);
    @endphp

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

            {{-- ─── Avatar Card ─────────────────────────────── --}}
            <div class="md:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col items-center gap-4">
                    <div class="relative group">
                        <template x-if="avatarPreview">
                            <img :src="avatarPreview" class="w-24 h-24 rounded-full object-cover ring-4 ring-white shadow-lg">
                        </template>
                        <template x-if="!avatarPreview">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" class="w-24 h-24 rounded-full object-cover ring-4 ring-white shadow-lg">
                            @else
                                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center ring-4 ring-white shadow-lg">
                                    <span class="text-3xl font-bold text-white">{{ $initial }}</span>
                                </div>
                            @endif
                        </template>
                        <label class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer" title="تغییر تصویر">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                            </svg>
                            <input type="file" name="avatar" accept="image/*" class="hidden" @change="previewAvatar($event)">
                        </label>
                    </div>
                    <div class="text-center">
                        <p class="font-semibold text-gray-800 text-sm">{{ $displayName }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ auth()->user()->mobile }}</p>
                    </div>
                    <p class="text-[10px] text-gray-400 text-center leading-relaxed">
                        روی تصویر کلیک کنید تا آن را تغییر دهید<br>
                        JPG، PNG — حداکثر ۲ مگابایت
                    </p>
                </div>
            </div>

            {{-- ─── Fields ──────────────────────────────────── --}}
            <div class="md:col-span-2 flex flex-col gap-4">

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        اطلاعات پروفایل
                    </h2>

                    <div class="space-y-4">
                        @foreach($fields as $field)
                            @if($field->type !== 'map')
                                @php
                                    $placeholders = [
                                        'full_name'   => 'نام و نام خانوادگی',
                                        'mobile'      => '۰۹۱۲۳۴۵۶۷۸۹',
                                        'postal_code' => '۱۰ رقم بدون فاصله',
                                        'address'     => 'آدرس کامل',
                                    ];
                                    $placeholder  = $placeholders[$field->key] ?? $field->label;
                                    $isPostalCode = $field->key === 'postal_code';
                                    $fieldVal     = $fieldValues[$field->id] ?? '';
                                @endphp
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">
                                        {{ $field->label }}
                                        @if($field->required)<span class="text-red-500 mr-0.5">*</span>@endif
                                    </label>

                                    @if($field->type === 'textarea')
                                        <textarea name="fields[{{ $field->id }}]" rows="3" dir="rtl"
                                                  class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition-all resize-none"
                                                  placeholder="{{ $placeholder }}"
                                                  {{ $field->required ? 'required' : '' }}>{{ $fieldVal }}</textarea>
                                    @else
                                        <input type="{{ $field->type === 'number' ? 'number' : 'text' }}"
                                               name="fields[{{ $field->id }}]"
                                               id="{{ $isPostalCode ? 'field-postal_code' : '' }}"
                                               value="{{ $fieldVal }}"
                                               dir="rtl"
                                               class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition-all"
                                               placeholder="{{ $placeholder }}"
                                               {{ $field->required ? 'required' : '' }}>
                                    @endif

                                    @if($isPostalCode)
                                        <p id="postal-code-region-hint" class="text-xs text-gray-400 mt-1 hidden" role="status"></p>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                @if($mapField)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        {{ $mapField->label }}
                        @if($mapField->required)<span class="text-red-500 mr-0.5">*</span>@endif
                    </h2>
                    <div class="mb-3 map-search-wrap">
                        <input type="text" id="map-search"
                               placeholder="جستجوی آدرس روی نقشه..."
                               class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition-all"
                               dir="rtl" autocomplete="off"
                               value="{{ $profile->map_address ?? '' }}">
                        <div id="map-suggestions" class="map-suggestions hidden"></div>
                    </div>
                    <div class="map-wrapper">
                        <div id="map"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">برای انتخاب مکان، روی نقشه کلیک کنید یا مارکر را بکشید.</p>
                    <input type="hidden" name="lat"         id="lat"         value="{{ $profile->lat ?? '' }}">
                    <input type="hidden" name="lng"         id="lng"         value="{{ $profile->lng ?? '' }}">
                    <input type="hidden" name="map_link"    id="map-link"    value="{{ $profile->map_link ?? '' }}">
                    <input type="hidden" name="map_address" id="map-address" value="{{ $profile->map_address ?? '' }}">
                </div>
                @endif

                {{-- Save --}}
                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-sm font-semibold shadow-md hover:shadow-lg transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 3H7a2 2 0 00-2 2v16l7-3 7 3V5a2 2 0 00-2-2z"/></svg>
                        ذخیره تغییرات
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
@if($mapField)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl:       'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl:     'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });

    const mapEl = document.getElementById('map');
    if (!mapEl) return;

    const NOMINATIM = { 'Accept': 'application/json', 'Accept-Language': 'fa, fa-IR, en;q=0.8' };
    let marker = null;

    function fmt(addr) {
        if (!addr || typeof addr !== 'string') return '';
        return addr.replace(/,/g, '،').replace(/\s+/g, ' ').trim();
    }
    function buildAddr(a) {
        if (!a || typeof a !== 'object') return '';
        return ['road','suburb','neighbourhood','village','city','town','state','county','country']
            .filter(k => a[k]).map(k => a[k]).join('، ');
    }
    function updateHidden(lat, lng, addr) {
        document.getElementById('lat').value       = lat;
        document.getElementById('lng').value       = lng;
        document.getElementById('map-link').value  = 'https://www.google.com/maps?q=' + lat + ',' + lng;
        if (addr !== undefined) document.getElementById('map-address').value = addr || '';
    }
    function reverseGeocode(lat, lng, cb) {
        fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng, { headers: NOMINATIM })
            .then(r => r.json())
            .then(d => cb(fmt(d.address ? buildAddr(d.address) : (d.display_name || ''))))
            .catch(() => cb(''));
    }

    const latVal = parseFloat(document.getElementById('lat').value) || 35.6892;
    const lngVal = parseFloat(document.getElementById('lng').value) || 51.3890;

    const map = L.map('map', { center: [latVal, lngVal], zoom: 13 });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    marker = L.marker([latVal, lngVal], { draggable: true }).addTo(map);
    updateHidden(latVal, lngVal, document.getElementById('map-address').value);

    map.on('click', e => {
        marker.setLatLng(e.latlng);
        updateHidden(e.latlng.lat, e.latlng.lng, '');
        reverseGeocode(e.latlng.lat, e.latlng.lng, addr => {
            document.getElementById('map-address').value = addr;
        });
    });
    marker.on('dragend', e => {
        const p = e.target.getLatLng();
        updateHidden(p.lat, p.lng, '');
        reverseGeocode(p.lat, p.lng, addr => {
            document.getElementById('map-address').value = addr;
        });
    });

    const searchInput = document.getElementById('map-search');
    const sugEl       = document.getElementById('map-suggestions');
    let suggestions   = [];
    let debounce      = null;

    function getDisp(loc) {
        return loc.address ? fmt(buildAddr(loc.address)) : fmt(loc.display_name || '');
    }
    function selectPlace(loc) {
        const lat = parseFloat(loc.lat), lng = parseFloat(loc.lon);
        const addr = getDisp(loc);
        marker.setLatLng([lat, lng]);
        map.setView([lat, lng], 16);
        updateHidden(lat, lng, addr);
        searchInput.value = addr;
        sugEl.classList.add('hidden');
    }
    function fetchSuggestions(q) {
        if (!q || q.length < 2) { sugEl.classList.add('hidden'); return; }
        sugEl.innerHTML = '<div class="map-suggestion-loading">در حال جستجو...</div>';
        sugEl.classList.remove('hidden');
        fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&limit=5&addressdetails=1', { headers: NOMINATIM })
            .then(r => r.json())
            .then(data => {
                suggestions = data || [];
                if (!suggestions.length) {
                    sugEl.innerHTML = '<div class="map-suggestion-loading">مکانی یافت نشد</div>';
                    return;
                }
                sugEl.innerHTML = suggestions.map((loc, i) =>
                    '<div class="map-suggestion-item" data-index="' + i + '">' +
                    getDisp(loc).replace(/[<>"]/g, c => ({'<':'&lt;','>':'&gt;','"':'&quot;'}[c])) +
                    '</div>'
                ).join('');
                sugEl.querySelectorAll('.map-suggestion-item').forEach(el => {
                    el.addEventListener('click', () => selectPlace(suggestions[+el.dataset.index]));
                });
            })
            .catch(() => { sugEl.innerHTML = '<div class="map-suggestion-loading">خطا در جستجو</div>'; });
    }
    searchInput.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(() => fetchSuggestions(this.value.trim()), 350);
    });
    searchInput.addEventListener('keydown', e => { if (e.key === 'Escape') sugEl.classList.add('hidden'); });
    document.addEventListener('click', e => { if (!e.target.closest('.map-search-wrap')) sugEl.classList.add('hidden'); });
    map.invalidateSize();
});
</script>
@endif

@php $postalCodeRegions = \App\Rules\IranianPostalCode::getRegionMap(); @endphp
<script>
(function () {
    var el   = document.getElementById('field-postal_code');
    var hint = document.getElementById('postal-code-region-hint');
    if (!el || !hint) return;
    var regions = @json($postalCodeRegions);
    function toEn(s) {
        var p = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'};
        return (s+'').replace(/[۰-۹]/g, c => p[c]||c).replace(/[^0-9]/g,'');
    }
    function update() {
        var raw = toEn(el.value);
        if (raw.length < 2) { hint.classList.add('hidden'); return; }
        var r = regions[raw.substring(0,2)];
        if (r) {
            hint.textContent = typeof r === 'object' && r.province
                ? 'منطقه: استان ' + r.province + '، شهر ' + (r.city||'')
                : 'منطقه: استان ' + r;
            hint.classList.remove('hidden');
        } else {
            hint.textContent = 'دو رقم اول شناسایی نشد.';
            hint.classList.remove('hidden');
        }
    }
    el.addEventListener('input', update);
    el.addEventListener('blur', update);
})();
</script>
@endsection
