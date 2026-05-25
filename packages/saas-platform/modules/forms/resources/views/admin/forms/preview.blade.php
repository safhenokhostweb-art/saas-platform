@extends(config('forms.layouts.admin'))

@section('header', 'پیش‌نمایش فرم: ' . $form->title)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6 flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <span>این پیش‌نمایش فرم نهایی است. ارسال غیرفعال است و فقط برای تست ظاهر فرم استفاده شود.</span>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6">
        <h4 class="font-bold mb-4 text-xl">{{ $form->title }}</h4>

        @if(!empty($form->intro_text))
            <div class="mb-6 text-gray-700 leading-relaxed">
                {!! strip_tags($form->intro_text, '<p><br><strong><em><u><a><ul><ol><li><h2><h3><h4><blockquote>') !!}
            </div>
        @endif

        <form action="#" method="POST" class="preview-form-no-submit" onsubmit="event.preventDefault(); alert('این پیش‌نمایش است. در سایت مشتری ارسال فعال است.');">
            @csrf

            @if(!empty($captcha['enabled']))
                <div class="mb-4">
                    <label class="block mb-1 font-medium">کپچا</label>
                    @if(($captcha['type'] ?? '') === 'persian_number' && !empty($captcha['persian_code']))
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-persian-captcha-challenge :code="$captcha['persian_code']" id="preview-captcha-persian" />
                            <input type="text" class="border rounded-xl px-4 py-3 w-32" placeholder="عدد بالا را وارد کنید" disabled>
                        </div>
                    @elseif(($captcha['type'] ?? '') === 'normal' && !empty($captcha['normal_text']))
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-persian-captcha-math :text="$captcha['normal_text']" />
                            <input type="text" class="border rounded-xl px-4 py-3 w-24" placeholder="جواب" disabled>
                        </div>
                    @elseif(($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
                        <div class="h-captcha" data-sitekey="{{ $captcha['hcaptcha_site_key'] }}" data-hl="fa"></div>
                    @endif
                </div>
            @endif

            @forelse($form->fields as $field)
                <div class="mb-4">
                    <label class="block mb-1 font-medium">
                        {{ $field->label }}
                        @if($field->is_required)
                            <span class="text-red-500">*</span>
                        @endif
                    </label>

                    @if(in_array($field->type, ['text','email','number']))
                        <input type="{{ $field->type }}" name="field_{{ $field->id }}"
                               class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                               @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif disabled>
                    @elseif($field->type === 'textarea')
                        <textarea name="field_{{ $field->id }}"
                                  class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                                  @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif disabled></textarea>
                    @elseif($field->type === 'date')
                        <x-persian-date-input
                            name="field_{{ $field->id }}"
                            value=""
                            placeholder="۱۴۰۳/۰۷/۱۵"
                            class="w-full border rounded-xl px-4 py-3 persian-date-input"
                        />
                    @elseif(in_array($field->type, ['select','radio','checkbox']))
                        @foreach($field->options ?? [] as $opt)
                            <div class="flex items-center gap-2 mb-1">
                                <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded"
                                       type="{{ $field->type === 'select' ? 'radio' : $field->type }}"
                                       name="field_{{ $field->id }}" value="{{ $opt }}" disabled>
                                <label class="text-gray-700">{{ $opt }}</label>
                            </div>
                        @endforeach
                    @endif

                    @if($field->help_text)
                        <p class="text-gray-500 text-sm mt-1">{{ $field->help_text }}</p>
                    @endif
                </div>
            @empty
                <p class="text-gray-500">هنوز فیلدی به این فرم اضافه نشده است.</p>
            @endforelse

            <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white w-full py-3 rounded-xl mt-4 opacity-60 cursor-not-allowed">
                ارسال فرم (غیرفعال در پیش‌نمایش)
            </button>
        </form>
    </div>

    <p class="mt-4 text-center">
        <a href="{{ route('admin.forms.edit', $form) }}" class="text-indigo-600 hover:underline">بازگشت به ویرایش فرم</a>
        @platform
        <span class="mx-2">|</span>
        <a href="{{ route('admin.forms.sites.edit', $form) }}" class="text-indigo-600 hover:underline">اتصال به سایت‌ها</a>
        @if(isset($firstSite))
            <span class="mx-2">|</span>
            <a href="{{ route('site.forms.show_for_site', [$firstSite, $form->slug]) }}" class="text-indigo-600 hover:underline" target="_blank">مشاهده در سایت (با کپچا)</a>
        @endif
        @endplatform
    </p>
</div>

@if($form->fields->contains('type', 'date'))
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
        jQuery('.persian-date-input').persianDatepicker({
            format: 'YYYY/MM/DD',
            persianDigit: true,
            initialValueType: 'persian'
        });
    }
});
</script>
@endpush
@endif
@endsection
