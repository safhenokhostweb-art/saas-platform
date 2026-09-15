@extends('layouts.admin')

@section('header', 'ویرایش پیشرفته (JSON): ' . $theme->name)

@section('content')
<div class="flex flex-wrap gap-2 items-center mb-4">
    <a href="{{ route('admin.theme-builder.index') }}" class="btn btn-outline-secondary">← فهرست تم‌ها</a>
    <a href="{{ route('admin.theme-builder.canvas', $theme) }}" class="btn btn-primary">ورود به قالب‌ساز</a>
    <a href="{{ route('admin.theme-builder.export', $theme) }}" class="btn btn-outline-secondary">خروجی JSON</a>
    <a href="{{ route('admin.theme-builder.clone-form', $theme) }}" class="btn btn-outline-secondary">کپی تم</a>
    @if($theme->isPublished())
        <form action="{{ route('admin.theme-builder.unpublish', $theme) }}" method="post" class="inline">@csrf<button type="submit" class="btn btn-outline-secondary">خروج از انتشار</button></form>
    @else
        <form action="{{ route('admin.theme-builder.publish', $theme) }}" method="post" class="inline">@csrf<button type="submit" class="btn btn-primary">انتشار تم</button></form>
    @endif
</div>

{{-- UI-C3: this raw/JSON editor is a compatibility/advanced surface, not
     the normal Theme-authoring workflow. Every field here (pages, header,
     footer, global styles, checkpoints, clone, export/import, publish/
     unpublish) has full parity in the canonical Theme Canvas — see
     ADR-053/ADR-054. This page is intentionally no longer linked from the
     Theme list; it remains reachable only for advanced/manual use. --}}
<div class="mb-4 px-4 py-3 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm">
    این صفحه ویرایشگر پیشرفتهٔ JSON است و برای استفادهٔ روزمره در نظر گرفته نشده — همهٔ امکانات آن (صفحات، هدر، فوتر، استایل‌های سراسری، چک‌پوینت، کپی، خروجی/ورودی، انتشار) در
    <a href="{{ route('admin.theme-builder.canvas', $theme) }}" class="font-semibold underline">قالب‌ساز بصری (Canvas)</a>
    نیز به‌طور کامل در دسترس است. برای طراحی معمول از قالب‌ساز استفاده کنید.
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
        <div class="card">
            <div class="card-body">
                <ul class="flex border-b gap-2 mb-4" id="theme-editor-tabs" role="tablist">
                    <li><button type="button" class="px-4 py-2 border-b-2 border-indigo-600 text-indigo-600 font-medium" data-tab="header">هدر</button></li>
                    <li><button type="button" class="px-4 py-2 border-b-2 border-transparent text-gray-600 hover:text-indigo-600" data-tab="footer">فوتر</button></li>
                    <li><button type="button" class="px-4 py-2 border-b-2 border-transparent text-gray-600 hover:text-indigo-600" data-tab="styles">استایل سراسری</button></li>
                </ul>

                <form id="theme-form" action="{{ route('admin.theme-builder.update', $theme) }}" method="post">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="header_blocks" id="input-header-blocks" value="{{ json_encode($theme->header_blocks ?? []) }}">
                    <input type="hidden" name="footer_blocks" id="input-footer-blocks" value="{{ json_encode($theme->footer_blocks ?? []) }}">
                    <input type="hidden" name="global_styles" id="input-global-styles" value="{{ json_encode($theme->global_styles ?? $defaultStyles) }}">

                    <div id="panel-header" class="tab-panel">
                        <p class="text-sm text-gray-600 mb-2">بلوک‌های ناحیه هدر. از لیست بلوک اضافه کنید یا JSON را مستقیم ویرایش کنید.</p>
                        <div class="flex gap-2 mb-2">
                            <select id="add-block-header" class="form-select w-48">
                                <option value="">— افزودن بلوک —</option>
                                @foreach($blockRegistry['byCategory'] ?? [] as $cat => $blocks)
                                    <optgroup label="{{ $blockRegistry['categoryLabels'][$cat] ?? $cat }}">
                                        @foreach($blocks as $type => $def)
                                            <option value="{{ $type }}">{{ $def['name'] ?? $type }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <button type="button" id="btn-add-header" class="btn btn-primary">افزودن</button>
                        </div>
                        <textarea id="json-header" class="form-control font-mono text-sm" rows="12" dir="ltr" style="direction:ltr;text-align:left;"></textarea>
                    </div>

                    <div id="panel-footer" class="tab-panel hidden">
                        <p class="text-sm text-gray-600 mb-2">بلوک‌های ناحیه فوتر.</p>
                        <div class="flex gap-2 mb-2">
                            <select id="add-block-footer" class="form-select w-48">
                                <option value="">— افزودن بلوک —</option>
                                @foreach($blockRegistry['byCategory'] ?? [] as $cat => $blocks)
                                    <optgroup label="{{ $blockRegistry['categoryLabels'][$cat] ?? $cat }}">
                                        @foreach($blocks as $type => $def)
                                            <option value="{{ $type }}">{{ $def['name'] ?? $type }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <button type="button" id="btn-add-footer" class="btn btn-primary">افزودن</button>
                        </div>
                        <textarea id="json-footer" class="form-control font-mono text-sm" rows="12" dir="ltr" style="direction:ltr;text-align:left;"></textarea>
                    </div>

                    <div id="panel-styles" class="tab-panel hidden">
                        <p class="text-sm text-gray-600 mb-2">رنگ‌ها، فونت و فاصله‌ها (JSON). برای ویرایش پیشرفته می‌توانید مستقیم ویرایش کنید.</p>
                        <textarea id="json-styles" class="form-control font-mono text-sm" rows="18" dir="ltr" style="direction:ltr;text-align:left;"></textarea>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="submit" class="btn btn-primary">ذخیره</button>
                        <button type="button" id="btn-revision" class="btn btn-outline-secondary">ذخیره نسخه (Revision)</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div>
        <div class="card sticky top-4">
            <div class="card-body">
                <h3 class="font-semibold mb-2">پیش‌نمایش</h3>
                <div class="flex gap-2 mb-2">
                    <a href="{{ route('admin.theme-builder.preview', ['theme_builder' => $theme, 'zone' => 'header']) }}" target="preview-frame" class="text-sm text-indigo-600">هدر</a>
                    <a href="{{ route('admin.theme-builder.preview', ['theme_builder' => $theme, 'zone' => 'footer']) }}" target="preview-frame" class="text-sm text-indigo-600">فوتر</a>
                </div>
                <iframe name="preview-frame" src="{{ route('admin.theme-builder.preview', ['theme_builder' => $theme, 'zone' => 'header']) }}" class="w-full border rounded bg-white" style="height:320px;"></iframe>
                <p class="text-xs text-gray-500 mt-2">پس از ذخیره، لینک‌های بالا را دوباره بزنید تا پیش‌نمایش به‌روز شود.</p>
            </div>
        </div>
        @if($theme->revisions->count() > 0)
        <div class="card mt-4">
            <div class="card-body">
                <h3 class="font-semibold mb-2">نسخه‌ها</h3>
                <ul class="text-sm space-y-1">
                    @foreach($theme->revisions->take(5) as $rev)
                        <li class="flex justify-between items-center">
                            <span>{{ $rev->label }} (v{{ $rev->version }})</span>
                            <form action="{{ route('admin.theme-builder.restore-revision', $rev) }}" method="post" class="inline">@csrf<button type="submit" class="text-indigo-600">بازیابی</button></form>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif
    </div>
</div>

<script>
(function() {
    var blockRegistry = @json($blockRegistry['blocks'] ?? []);
    function makeBlock(type) {
        var def = blockRegistry[type];
        var props = def && def.defaultProps ? JSON.parse(JSON.stringify(def.defaultProps)) : {};
        return { type: type, id: 'b' + Date.now() + '-' + Math.random().toString(36).slice(2, 8), props: props };
    }
    function syncInput(taId, inputId) {
        var ta = document.getElementById(taId);
        var input = document.getElementById(inputId);
        if (!ta || !input) return;
        try {
            var val = JSON.parse(ta.value || '[]');
            input.value = JSON.stringify(Array.isArray(val) ? val : []);
        } catch (e) {
            input.value = ta.value ? ta.value : '[]';
        }
    }
    function syncToTextarea(inputId, taId) {
        var input = document.getElementById(inputId);
        var ta = document.getElementById(taId);
        if (!input || !ta) return;
        try {
            var data = JSON.parse(input.value || '[]');
            ta.value = JSON.stringify(data, null, 2);
        } catch (e) {
            ta.value = input.value || '[]';
        }
    }
    ['header', 'footer'].forEach(function(zone) {
        var inputId = 'input-' + zone + '-blocks';
        var taId = 'json-' + zone;
        syncToTextarea(inputId, taId);
        var addSelect = document.getElementById('add-block-' + zone);
        var btnAdd = document.getElementById('btn-add-' + zone);
        if (btnAdd && addSelect) {
            btnAdd.addEventListener('click', function() {
                var type = addSelect.value;
                if (!type) return;
                var ta = document.getElementById(taId);
                var arr = [];
                try { arr = JSON.parse(ta.value || '[]'); } catch (e) {}
                if (!Array.isArray(arr)) arr = [];
                arr.push(makeBlock(type));
                ta.value = JSON.stringify(arr, null, 2);
                syncInput(taId, inputId);
            });
        }
        document.getElementById(taId).addEventListener('change', function() { syncInput(taId, inputId); });
        document.getElementById(taId).addEventListener('blur', function() { syncInput(taId, inputId); });
    });
    var stylesInput = document.getElementById('input-global-styles');
    var stylesTa = document.getElementById('json-styles');
    if (stylesTa && stylesInput) {
        try { stylesTa.value = JSON.stringify(JSON.parse(stylesInput.value || '{}'), null, 2); } catch (e) { stylesTa.value = stylesInput.value || '{}'; }
        stylesTa.addEventListener('change', function() {
            try { stylesInput.value = JSON.stringify(JSON.parse(stylesTa.value)); } catch (e) { stylesInput.value = stylesTa.value; }
        });
        stylesTa.addEventListener('blur', function() {
            try { stylesInput.value = JSON.stringify(JSON.parse(stylesTa.value)); } catch (e) { stylesInput.value = stylesTa.value; }
        });
    }
    document.getElementById('theme-form').addEventListener('submit', function() {
        syncInput('json-header', 'input-header-blocks');
        syncInput('json-footer', 'input-footer-blocks');
        try { document.getElementById('input-global-styles').value = JSON.stringify(JSON.parse(document.getElementById('json-styles').value)); } catch (e) {}
    });
    document.querySelectorAll('#theme-editor-tabs [data-tab]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var t = this.getAttribute('data-tab');
            document.querySelectorAll('#theme-editor-tabs [data-tab]').forEach(function(b) {
                b.classList.remove('border-indigo-600', 'text-indigo-600');
                b.classList.add('border-transparent', 'text-gray-600');
            });
            this.classList.add('border-indigo-600', 'text-indigo-600');
            this.classList.remove('border-transparent', 'text-gray-600');
            document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.add('hidden'); });
            var panel = document.getElementById('panel-' + t);
            if (panel) panel.classList.remove('hidden');
        });
    });
    document.getElementById('btn-revision').addEventListener('click', function() {
        syncInput('json-header', 'input-header-blocks');
        syncInput('json-footer', 'input-footer-blocks');
        try { document.getElementById('input-global-styles').value = JSON.stringify(JSON.parse(document.getElementById('json-styles').value)); } catch (e) {}
        var form = document.getElementById('theme-form');
        var fd = new FormData(form);
        fd.append('_method', 'PUT');
        fetch(form.action, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function() {
                fetch('{{ route("admin.theme-builder.revision", $theme) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ label: 'نسخه ' + new Date().toLocaleDateString('fa-IR') })
                }).then(function() { alert('تم و نسخه ذخیره شد.'); location.reload(); });
            });
    });
})();
</script>
@endsection
