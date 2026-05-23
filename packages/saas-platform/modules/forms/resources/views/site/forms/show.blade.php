@php
    $pageTitle = trim((string) ($form->meta_title ?: $form->title));
    $pageDesc = trim((string) ($form->meta_description ?: ''));
    if ($pageDesc === '' && $form->intro_text) {
        $pageDesc = \Illuminate\Support\Str::limit(strip_tags($form->intro_text), 160);
    }
    if ($pageDesc === '') {
        $pageDesc = 'فرم «' . $form->title . '» — ارسال اطلاعات به ' . config('app.name');
    }
    $canonicalOverride = trim((string) ($form->canonical_url ?: ''));
@endphp
@section('title', $pageTitle)
@section('meta_description', $pageDesc)
@if($canonicalOverride !== '')
@section('canonical', $canonicalOverride)
@endif
@if(trim((string) ($form->meta_robots ?? '')) !== '')
@section('meta_robots', trim((string) $form->meta_robots))
@endif

@section('breadcrumb')
    <a href="{{ route('home') }}">خانه</a>
    <span>/</span>
    <a href="{{ route('forms.index') }}">فرم‌ها</a>
    <span>/</span>
    <span>{{ $form->title }}</span>
@endsection

@section('content')
<div class="module-form-page container mx-auto py-8 px-4 max-w-2xl">
    <header class="mb-8">
        <h1 class="font-bold text-2xl mb-2" style="color: var(--platform-heading-color, #111827);">{{ $form->title }}</h1>
        @if($form->intro_text)
            <div class="form-intro-text text-slate-600 leading-relaxed text-sm md:text-base border-r-4 border-indigo-200 pr-4 py-1">
                {!! strip_tags($form->intro_text, '<p><br><strong><em><u><a><ul><ol><li><h2><h3><h4><blockquote><span><img><table><thead><tbody><tr><td><th>') !!}
            </div>
        @endif
    </header>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 mb-6" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5 md:p-8">
        <form method="POST" action="{{ route('forms.submit', $form->slug) }}" class="space-y-5" novalidate>
            @csrf

            @foreach($form->fields as $field)
                <div>
                    <label class="form-label" for="field-{{ $field->id }}">
                        {{ $field->label }}
                        @if($field->is_required)
                            <span class="text-red-500" aria-hidden="true">*</span>
                        @endif
                    </label>

                    @if(in_array($field->type, ['text','email','number'], true))
                        <input id="field-{{ $field->id }}" type="{{ $field->type }}" name="field_{{ $field->id }}" class="form-input" value="{{ old('field_'.$field->id) }}" placeholder="{{ $field->placeholder }}" @if($field->is_required) required @endif>
                    @elseif($field->type === 'textarea')
                        <textarea id="field-{{ $field->id }}" name="field_{{ $field->id }}" class="form-input min-h-[8rem]" placeholder="{{ $field->placeholder }}" @if($field->is_required) required @endif>{{ old('field_'.$field->id) }}</textarea>
                    @elseif($field->type === 'date')
                        <x-persian-date-input id="field-{{ $field->id }}" name="field_{{ $field->id }}" value="{{ old('field_'.$field->id) }}" placeholder="۱۴۰۴/۰۱/۱۵" class="form-input persian-date-input" />
                    @elseif($field->type === 'select')
                        <select id="field-{{ $field->id }}" name="field_{{ $field->id }}" class="form-input" @if($field->is_required) required @endif>
                            <option value="">— انتخاب کنید —</option>
                            @foreach($field->options ?? [] as $opt)
                                <option value="{{ $opt }}" @selected(old('field_'.$field->id) == $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    @elseif($field->type === 'radio')
                        <fieldset class="space-y-2 border-0 p-0 m-0">
                            <legend class="sr-only">{{ $field->label }}</legend>
                            @foreach($field->options ?? [] as $opt)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="field_{{ $field->id }}" value="{{ $opt }}" @checked(old('field_'.$field->id) == $opt) @if($field->is_required && $loop->first) required @endif>
                                    <span>{{ $opt }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @elseif($field->type === 'checkbox')
                        <fieldset class="space-y-2 border-0 p-0 m-0">
                            <legend class="sr-only">{{ $field->label }}</legend>
                            @php $oldCb = old('field_'.$field->id, []); @endphp
                            @if(! is_array($oldCb))
                                @php $oldCb = $oldCb ? [$oldCb] : []; @endphp
                            @endif
                            @foreach($field->options ?? [] as $opt)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="field_{{ $field->id }}[]" value="{{ $opt }}" @checked(in_array($opt, $oldCb, true))>
                                    <span>{{ $opt }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif

                    @if($field->help_text)
                        <p class="text-xs text-slate-500 mt-1">{{ $field->help_text }}</p>
                    @endif

                    @error('field_'.$field->id)
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach

            @if(!empty($captcha['enabled'] ?? false))
                <div>
                    <label class="form-label">کپچا</label>
                    @if($captcha['type'] === 'persian_number' && !empty($captcha['persian_code']))
                        <div class="flex items-center gap-3 flex-wrap">
                            <x-persian-captcha-challenge :code="$captcha['persian_code']" />
                            <input type="text" name="captcha_answer" class="form-input w-36" placeholder="همان ارقام (فارسی یا انگلیسی)" maxlength="8" required autocomplete="off" aria-label="پاسخ کپچا">
                        </div>
                        <p class="text-xs text-slate-500 mt-1">ارقام را دقیقاً به همان ترتیب از چپ به راست وارد کنید.</p>
                    @elseif($captcha['type'] === 'normal' && !empty($captcha['normal_text']))
                        <div class="flex items-center gap-3 flex-wrap">
                            <x-persian-captcha-math :text="$captcha['normal_text']" />
                            <input type="text" name="captcha_answer" class="form-input w-24" placeholder="جواب" maxlength="4" required autocomplete="off" aria-label="جواب کپچا">
                        </div>
                    @elseif($captcha['type'] === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
                        <div class="h-captcha" data-sitekey="{{ $captcha['hcaptcha_site_key'] }}" data-hl="fa"></div>
                    @endif
                    @error('captcha')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
            @endif

            <button type="submit" class="btn btn-primary w-full md:w-auto min-h-[2.75rem] px-8 font-semibold">ارسال فرم</button>
        </form>
    </div>
</div>

@if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
@push('scripts')
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
@endpush
@endif

@if($form->fields->contains('type', 'date'))
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}?v={{ @filemtime(public_path('vendor/persian-datepicker/persian-datepicker.min.css')) ?: time() }}">
@endpush
@push('scripts')
<script src="{{ asset('vendor/jquery/jquery.min.js') }}" defer></script>
<script src="{{ asset('vendor/persian-date/persian-date.min.js') }}" defer></script>
<script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.persian-date-input') && typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({ format: 'YYYY/MM/DD', persianDigit: true, initialValueType: 'persian', autoClose: true });
    }
});
</script>
@endpush
@endif

@push('json-ld')
<script type="application/ld+json"><?php echo json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $pageTitle,
    'description' => \Illuminate\Support\Str::limit(strip_tags($pageDesc), 300),
    'url' => url()->current(),
    'inLanguage' => 'fa-IR',
], JSON_UNESCAPED_UNICODE) ?></script>
@endpush
@endsection
