@extends('layouts.app')

@section('head')
    {{-- Leaflet CSS --}}
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    />
    <style>
        /* کانتینر نقشه: راست‌چین نباید روی نقشه اعمال شود */
        .map-wrapper {
            direction: ltr;
            width: 100%;
            position: relative;
            overflow: hidden;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            background: #e5e7eb;
        }
        #map {
            width: 100% !important;
            height: 350px !important;
            min-height: 350px;
            position: relative !important;
            z-index: 0;
        }
        .map-search-wrap {
            position: relative;
        }
        .map-suggestions {
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            margin-top: 4px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            max-height: 220px;
            overflow-y: auto;
            z-index: 1000;
        }
        .map-suggestion-item {
            padding: 10px 14px;
            cursor: pointer;
            text-align: right;
            direction: rtl;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.15s;
        }
        .map-suggestion-item:hover, .map-suggestion-item.active {
            background: #eff6ff;
        }
        .map-suggestion-item:last-child {
            border-bottom: none;
        }
        .map-suggestion-loading {
            padding: 12px;
            text-align: center;
            color: #6b7280;
        }
        .leaflet-container {
            font-family: inherit;
            width: 100% !important;
            height: 100% !important;
        }
    </style>
@endsection

@section('content')
<div class="container py-5 max-w-2xl mx-auto" dir="rtl">
    <div class="card shadow-sm border-0 rounded-xl">
        <div class="card-body p-6">

            <h4 class="text-xl font-bold mb-3 text-right">تکمیل پروفایل</h4>

            <p class="text-gray-600 mb-6 text-right">
                برای ادامه استفاده از سیستم، اطلاعات زیر را تکمیل کن
            </p>

            <form method="POST" action="{{ route('profile.store') }}" class="text-right">
                @csrf

                @foreach($fields as $field)
                    @if($field->type !== 'map')
                        @php
                            $placeholders = [
                                'full_name' => 'نام و نام خانوادگی خود را وارد کنید',
                                'mobile' => 'مثال: ۰۹۱۲۳۴۵۶۷۸۹',
                                'postal_code' => '۱۰ رقم (مثال: ۱۲۳۴۵۶۷۸۹۰)',
                                'address' => 'آدرس کامل شامل خیابان، پلاک و واحد',
                            ];
                            $placeholder = $placeholders[$field->key] ?? $field->label . ' را وارد کنید';
                            $isPostalCode = $field->key === 'postal_code';
                        @endphp
                        <div class="mb-4">
                            <label class="form-label text-right">
                                {{ $field->label }}
                                @if($field->required)
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>

                            @if($field->type === 'textarea')
                                <textarea
                                    name="fields[{{ $field->id }}]"
                                    class="form-control rounded-lg text-right"
                                    dir="rtl"
                                    rows="4"
                                    placeholder="{{ $placeholder }}"
                                    {{ $field->required ? 'required' : '' }}
                                ></textarea>
                            @else
                                <input
                                    type="{{ $field->type === 'number' ? 'number' : 'text' }}"
                                    name="fields[{{ $field->id }}]"
                                    id="{{ $isPostalCode ? 'field-postal_code' : '' }}"
                                    class="form-control rounded-lg text-right"
                                    dir="rtl"
                                    placeholder="{{ $isPostalCode ? '۱۰ رقم، بدون ۰ و ۲ در پنج رقم اول (مثال: ۱۳۱۴۵۶۷۸۹۰)' : $placeholder }}"
                                    {{ $field->required ? 'required' : '' }}
                                >
                            @endif
                            @if($isPostalCode)
                                <p id="postal-code-region-hint" class="text-sm text-gray-500 mt-1 text-right hidden" role="status"></p>
                            @endif
                        </div>
                    @endif
                @endforeach

                @if($mapField)
                    <div class="mb-6">
                        <label class="form-label text-right">
                            {{ $mapField->label }}
                            @if($mapField->required)
                                <span class="text-red-500">*</span>
                            @endif
                        </label>

                        <div class="mb-3 map-search-wrap">
                            <input type="text" id="map-search" placeholder="جستجوی آدرس (تایپ کنید تا پیشنهادات نمایش داده شود)" class="form-control w-full rounded-lg text-right" dir="rtl" autocomplete="off">
                            <div id="map-suggestions" class="map-suggestions hidden"></div>
                        </div>

                        <div class="map-wrapper">
                            <div id="map"></div>
                        </div>

                        <div class="mt-2 text-sm text-gray-600 text-right">برای انتخاب مکان، روی نقشه کلیک کنید یا مارکر را بکشید</div>

                        <input type="hidden" name="lat" id="lat">
                        <input type="hidden" name="lng" id="lng">
                        <input type="hidden" name="map_link" id="map-link">
                        <input type="hidden" name="map_address" id="map-address">
                    </div>
                @endif

                <button type="submit" class="btn btn-primary w-full rounded-lg">
                    ذخیره و ادامه
                </button>

            </form>

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>

@if($mapField)
<script>
document.addEventListener('DOMContentLoaded', function () {

    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });

    const mapEl = document.getElementById('map');
    if (!mapEl) return;

    let marker = null;

    function updateMapLinks(lat, lng, address) {
        document.getElementById('lat').value = lat;
        document.getElementById('lng').value = lng;
        document.getElementById('map-link').value = 'https://www.google.com/maps?q=' + lat + ',' + lng;
        document.getElementById('map-address').value = address || '';
    }

    var NOMINATIM_HEADERS = { 'Accept': 'application/json', 'Accept-Language': 'fa, fa-IR, en;q=0.8' };

    function formatAddressFarsi(addr) {
        if (!addr || typeof addr !== 'string') return '';
        return addr
            .replace(/,/g, '،')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function buildAddressFromParts(address) {
        if (!address || typeof address !== 'object') return '';
        var parts = [];
        ['road', 'suburb', 'neighbourhood', 'village', 'city', 'town', 'state', 'county', 'country'].forEach(function(k) {
            if (address[k]) parts.push(address[k]);
        });
        return parts.join('، ');
    }

    function reverseGeocode(lat, lng, callback) {
        fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng, {
            headers: NOMINATIM_HEADERS
        })
        .then(r => r.json())
        .then(data => {
            var addr = '';
            if (data.address) addr = buildAddressFromParts(data.address);
            if (!addr) addr = data.display_name || '';
            if (callback) callback(formatAddressFarsi(addr));
        })
        .catch(() => { if (callback) callback(''); });
    }

    function initMap() {
        const defaultLat = 35.6892;
        const defaultLng = 51.3890;

        const latInput = document.getElementById('lat');
        const lngInput = document.getElementById('lng');

        const lat = latInput.value ? parseFloat(latInput.value) : defaultLat;
        const lng = lngInput.value ? parseFloat(lngInput.value) : defaultLng;

        if (window.leafletMap) {
            window.leafletMap.remove();
            window.leafletMap = null;
        }

        window.leafletMap = L.map('map', {
            center: [lat, lng],
            zoom: 13,
            zoomControl: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(window.leafletMap);

        marker = L.marker([lat, lng], { draggable: true }).addTo(window.leafletMap);

        updateMapLinks(lat, lng, document.getElementById('map-address').value);
        reverseGeocode(lat, lng, function(addr) {
            document.getElementById('map-address').value = addr;
        });

        window.leafletMap.on('click', function (e) {
            marker.setLatLng(e.latlng);
            updateMapLinks(e.latlng.lat, e.latlng.lng, '');
            reverseGeocode(e.latlng.lat, e.latlng.lng, function(addr) {
                document.getElementById('map-address').value = addr;
            });
        });

        marker.on('dragend', function (e) {
            var pos = e.target.getLatLng();
            updateMapLinks(pos.lat, pos.lng, '');
            reverseGeocode(pos.lat, pos.lng, function(addr) {
                document.getElementById('map-address').value = addr;
            });
        });

        var searchInput = document.getElementById('map-search');
        var suggestionsEl = document.getElementById('map-suggestions');
        var searchDebounce = null;
        var suggestionsData = [];

        function showSuggestions() {
            suggestionsEl.classList.remove('hidden');
        }
        function hideSuggestions() {
            suggestionsEl.classList.add('hidden');
        }
        function getDisplayAddress(loc) {
            if (loc.address) return formatAddressFarsi(buildAddressFromParts(loc.address));
            return formatAddressFarsi(loc.display_name || '');
        }

        function selectPlace(loc) {
            var newLat = parseFloat(loc.lat);
            var newLng = parseFloat(loc.lon);
            var addr = getDisplayAddress(loc);
            marker.setLatLng([newLat, newLng]);
            window.leafletMap.setView([newLat, newLng], 16);
            updateMapLinks(newLat, newLng, addr);
            searchInput.value = addr;
            hideSuggestions();
        }

        function fetchSuggestions(q) {
            if (!q || q.length < 2) {
                hideSuggestions();
                return;
            }
            suggestionsEl.innerHTML = '<div class="map-suggestion-loading">در حال جستجو...</div>';
            showSuggestions();
            fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&limit=5&addressdetails=1', {
                headers: NOMINATIM_HEADERS
            })
            .then(r => r.json())
            .then(data => {
                suggestionsData = data || [];
                if (suggestionsData.length === 0) {
                    suggestionsEl.innerHTML = '<div class="map-suggestion-loading">مکانی یافت نشد</div>';
                } else {
                    suggestionsEl.innerHTML = suggestionsData.map(function(loc, i) {
                        var text = getDisplayAddress(loc);
                        return '<div class="map-suggestion-item" data-index="' + i + '">' + text.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</div>';
                    }).join('');
                    suggestionsEl.querySelectorAll('.map-suggestion-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            selectPlace(suggestionsData[parseInt(el.dataset.index)]);
                        });
                    });
                }
            })
            .catch(function() {
                suggestionsEl.innerHTML = '<div class="map-suggestion-loading">خطا در جستجو</div>';
            });
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(searchDebounce);
            var q = this.value.trim();
            searchDebounce = setTimeout(function() {
                fetchSuggestions(q);
            }, 350);
        });

        searchInput.addEventListener('focus', function() {
            if (suggestionsData.length > 0) showSuggestions();
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideSuggestions();
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.map-search-wrap')) hideSuggestions();
        });

        window.leafletMap.invalidateSize();
    }

    setTimeout(initMap, 100);
});
</script>
@endif

@php $postalCodeRegions = \App\Rules\IranianPostalCode::getRegionMap(); @endphp
<script>
(function() {
    var el = document.getElementById('field-postal_code');
    if (!el) return;
    var hint = document.getElementById('postal-code-region-hint');
    var regions = @json($postalCodeRegions);

    function toEnDigits(str) {
        var p = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'};
        return (str + '').replace(/[۰-۹]/g, function(c) { return p[c] || c; }).replace(/[^0-9]/g, '');
    }

    function updateHint() {
        var raw = toEnDigits(el.value);
        if (raw.length < 2) {
            hint.classList.add('hidden');
            hint.textContent = '';
            return;
        }
        var prefix = raw.substring(0, 2);
        var region = regions[prefix];
        if (region) {
            var label = (typeof region === 'object' && region.province)
                ? 'منطقه: استان ' + region.province + '، شهر ' + (region.city || '')
                : 'منطقه: استان ' + region;
            hint.textContent = label;
            hint.classList.remove('hidden');
        } else {
            hint.textContent = 'دو رقم اول در لیست مناطق شناخته‌شده نیست.';
            hint.classList.remove('hidden');
        }
    }

    el.addEventListener('input', updateHint);
    el.addEventListener('blur', updateHint);
})();
</script>
@endsection

