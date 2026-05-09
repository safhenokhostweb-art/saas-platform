{{-- فرم مشترک ایجاد/ویرایش صفحه - شامل صفحه‌ساز بلوکی --}}
@push('head')
<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
@endpush
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" id="page-form" data-upload-image-url="{{ route('admin.pages.upload-image') }}">
    @csrf
    @if(isset($page) && $page->id) @method('PUT') @endif

    <div class="flex flex-col lg:flex-row gap-6">
        {{-- در دسکتاپ: صفحه‌ساز راست (RTL)، تنظیمات چپ. در موبایل: اول صفحه‌ساز، بعد تنظیمات. --}}
        <div class="flex-1 min-w-0 lg:min-w-[60%] order-1 lg:order-1">
            <div class="card p-6 shadow-sm">
                <h2 class="text-lg font-bold text-gray-900 mb-1 border-b border-gray-200 pb-2">صفحه‌ساز</h2>
                <p class="text-sm text-gray-500 mb-4">بلوک‌ها را اضافه کنید، با کشیدن جابجا کنید و محتوا را ویرایش کنید. محتوا قبل از افزودن بلوک جدید یا جابجایی ذخیره می‌شود.</p>

                <input type="hidden" name="content" id="page-content-json" value="{{ old('content', isset($page) && $page->content ? json_encode($page->content) : '{"blocks":[]}') }}">

                <div id="page-builder-toolbar" class="flex flex-wrap gap-3 mb-4 p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase">متن</span>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 text-sm font-medium transition" data-type="heading">عنوان</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 text-sm font-medium transition" data-type="paragraph">پاراگراف</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 text-sm font-medium transition" data-type="rich_text">متن غنی</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase">صفحه یک‌پارچه</span>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 text-sm font-medium transition" data-type="hero">Hero</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 text-sm font-medium transition" data-type="features">ویژگی‌ها</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 text-sm font-medium transition" data-type="cta">CTA</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 text-sm font-medium transition" data-type="columns">دو ستون</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase">رسانه و سایر</span>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 text-sm font-medium transition" data-type="image">تصویر</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 hover:bg-amber-100 text-sm font-medium transition" data-type="gallery">گالری تصویر</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 text-sm font-medium transition" data-type="media_text">تصویر و متن</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 text-sm font-medium transition" data-type="list">لیست</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 text-sm font-medium transition" data-type="quote">نقل‌قول</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 text-sm font-medium transition" data-type="code">کد</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 text-sm font-medium transition" data-type="divider">جداکننده</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 text-sm font-medium transition" data-type="spacer">فاصله</button>
                        <button type="button" class="page-builder-add px-3 py-2 rounded-lg bg-white border border-gray-200 hover:border-indigo-300 text-sm font-medium transition" data-type="html">HTML</button>
                    </div>
                </div>

                <div id="page-builder-blocks" class="space-y-3 min-h-[160px] rounded-xl border-2 border-dashed border-gray-200 p-4 bg-gray-50/50">
                    {{-- بلوک‌ها با JS پر می‌شوند --}}
                </div>

                @include('admin.pages.partials.page-builder-guide')
            </div>
        </div>

        {{-- ستون چپ (در RTL): تنظیمات صفحه و دکمه‌ها --}}
        <div class="lg:w-[340px] shrink-0 order-2 lg:order-2 space-y-6">
            <div class="card p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">تنظیمات صفحه</h2>
                <div class="space-y-4">
                    <div>
                        <label class="form-label">عنوان صفحه *</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title', $page->title ?? '') }}" required placeholder="مثال: درباره ما">
                    </div>
                    <div>
                        <label class="form-label">اسلاگ (آدرس)</label>
                        <input type="text" name="slug" class="form-control" value="{{ old('slug', $page->slug ?? '') }}" placeholder="about-us">
                        <p class="text-xs text-gray-500 mt-1">خالی = از روی عنوان ساخته می‌شود</p>
                    </div>
                    <div>
                        <label class="form-label">آیکن پیش‌فرض</label>
                        <select name="icon_default" class="form-select">
                            @foreach(\App\Helpers\Icons::groups() as $groupTitle => $icons)
                                <optgroup label="{{ $groupTitle }}">
                                    @foreach($icons as $key => $label)
                                        <option value="{{ $key }}" {{ old('icon_default', $page->icon_default ?? 'document') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">آیکن سفارشی (بارگذاری یا مسیر از پروژه)</label>
                        <input type="file" name="icon" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                        <input type="text" name="icon_path" class="form-control mt-1" placeholder="یا مسیر پروژه (pages/icon.png) یا آدرس کامل هاست (https://...)" value="{{ old('icon_path', isset($page) && $page->icon ? $page->icon : '') }}">
                        <p class="text-xs text-gray-500 mt-1">می‌توانید مسیر نسبی (مثلاً <code class="bg-gray-100 px-1 rounded">pages/icon.png</code>) یا آدرس کامل تصویر روی هاست (مثلاً <code class="bg-gray-100 px-1 rounded">https://سایت شما/storage/icon.png</code>) را وارد کنید.</p>
                        @php $hasCustomIcon = isset($page) && ($page->getRawOriginal('icon') ?? $page->icon ?? null); @endphp
                        @if($hasCustomIcon)
                            <div class="mt-2 flex items-center gap-2 flex-wrap">
                                @if($page->icon_url ?? null)
                                    @if(str_ends_with(strtolower($page->icon ?? ''), '.svg'))
                                        <img src="{{ $page->icon_url }}" alt="" class="w-8 h-8 object-contain border rounded" onerror="this.style.display='none'">
                                    @else
                                        <img src="{{ $page->icon_url }}" alt="" class="w-8 h-8 object-contain border rounded">
                                    @endif
                                @endif
                                <label class="flex items-center gap-1 text-sm text-gray-600">
                                    <input type="checkbox" name="remove_icon" value="1"> حذف آیکن سفارشی
                                </label>
                            </div>
                        @endif
                    </div>
                    <div>
                        <label class="form-label">موقعیت در فهرست</label>
                        <select name="menu_position" class="form-select">
                            @foreach(\App\Models\Page::menuPositions() as $key => $label)
                                <option value="{{ $key }}" {{ old('menu_position', $page->menu_position ?? 'top') == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">ترتیب در منو</label>
                        <input type="number" name="menu_order" class="form-control" value="{{ old('menu_order', $page->menu_order ?? 0) }}" min="0">
                    </div>
                    <div>
                        <label class="form-label">توضیح متا (SEO)</label>
                        <textarea name="meta_description" id="page-meta-description" class="form-control" rows="2" placeholder="۱۵۰–۱۶۰ کاراکتر برای موتورهای جستجو (خالی = استفاده از عنوان)">{{ old('meta_description', $page->meta_description ?? '') }}</textarea>
                        <div id="seo-suggestions" class="mt-2 p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800 hidden">
                            <strong>پیشنهادات SEO:</strong>
                            <ul id="seo-suggestions-list" class="list-disc list-inside mt-1 space-y-0.5"></ul>
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', isset($page) ? $page->is_active : true) ? 'checked' : '' }}>
                            <span>صفحه فعال باشد</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <button type="submit" class="btn btn-primary">ذخیره صفحه</button>
                <a href="{{ route('admin.pages.index') }}" class="btn btn-secondary">انصراف</a>
            </div>
        </div>
    </div>
</form>

@include('admin.pages.partials.page-builder-script')
<script>
(function() {
    var titleInp = document.querySelector('input[name="title"]');
    var metaTa = document.getElementById('page-meta-description');
    var box = document.getElementById('seo-suggestions');
    var list = document.getElementById('seo-suggestions-list');
    function updateSeoSuggestions() {
        if (!list || !box) return;
        var suggestions = [];
        var title = (titleInp && titleInp.value) ? titleInp.value.trim() : '';
        var meta = (metaTa && metaTa.value) ? metaTa.value.trim() : '';
        if (title.length > 60) suggestions.push('عنوان طولانی است؛ بهتر است زیر ۶۰ کاراکتر باشد.');
        if (!meta && title) suggestions.push('پر کردن «توضیح متا» برای سئو بهتر توصیه می‌شود.');
        if (meta && meta.length < 120) suggestions.push('توضیح متا کوتاه است؛ ۱۵۰–۱۶۰ کاراکتر ایده‌ال است.');
        if (meta && meta.length > 165) suggestions.push('توضیح متا طولانی است؛ ممکن است در نتایج جستجو قطع شود.');
        list.innerHTML = suggestions.length ? suggestions.map(function(s) { return '<li>' + s + '</li>'; }).join('') : '<li class="text-green-700">وضعیت فعلی برای سئو مناسب است.</li>';
        box.classList.toggle('hidden', suggestions.length === 0 && !meta);
    }
    if (titleInp) titleInp.addEventListener('input', updateSeoSuggestions);
    if (metaTa) metaTa.addEventListener('input', updateSeoSuggestions);
    updateSeoSuggestions();
})();
</script>
