@extends('layouts.admin')

@section('header', 'پیش‌نمایش فرم: ' . $form->title)

@section('content')

<div class="max-w-2xl mx-auto">
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6 flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        <span>این پیش‌نمایش فرم نهایی است. ارسال غیرفعال است و فقط برای تست ظاهر فرم استفاده شود.</span>
    </div>

    <div class="bg-white rounded-2xl shadow p-6">
        <h4 class="font-bold mb-4 text-xl">{{ $form->title }}</h4>

        <form action="#" method="POST" class="preview-form-no-submit" onsubmit="event.preventDefault(); alert('این پیش‌نمایش است. در سایت مشتری ارسال فعال است.');">
            @csrf

            @if(!empty($captcha['enabled']))
                <div class="mb-3">
                    <label class="form-label">کپچا</label>
                    @if(($captcha['type'] ?? '') === 'persian_number' && !empty($captcha['persian_code']))
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-persian-captcha-challenge :code="$captcha['persian_code']" id="preview-captcha-persian" />
                            <input type="text" class="form-control w-32" placeholder="عدد بالا را وارد کنید" disabled>
                        </div>
                    @elseif(($captcha['type'] ?? '') === 'normal' && !empty($captcha['normal_text']))
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-persian-captcha-math :text="$captcha['normal_text']" />
                            <input type="text" class="form-control w-24" placeholder="جواب" disabled>
                        </div>
                    @elseif(($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
                        <div class="h-captcha" data-sitekey="{{ $captcha['hcaptcha_site_key'] }}" data-hl="fa"></div>
                    @endif
                </div>
            @endif

            @forelse($form->fields as $field)
                <div class="mb-3">
                    <label class="form-label">
                        {{ $field->label }}
                        @if($field->is_required)
                            <span class="text-red-500">*</span>
                        @endif
                    </label>

                    @if(in_array($field->type, ['text','email','number']))
                        <input type="{{ $field->type }}" name="field_{{ $field->id }}" class="form-control" @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif>
                    @elseif($field->type === 'textarea')
                        <textarea name="field_{{ $field->id }}" class="form-control" @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif></textarea>
                    @elseif($field->type === 'date')
                        <x-persian-date-input
                            name="field_{{ $field->id }}"
                            value=""
                            placeholder="۱۴۰۳/۰۷/۱۵"
                            class="form-control persian-date-input"
                        />
                    @elseif(in_array($field->type, ['select','radio','checkbox']))
                        @foreach($field->options ?? [] as $opt)
                            <div class="form-check">
                                <input class="form-check-input" type="{{ $field->type === 'select' ? 'radio' : $field->type }}"
                                       name="field_{{ $field->id }}" value="{{ $opt }}">
                                <label class="form-check-label">{{ $opt }}</label>
                            </div>
                        @endforeach
                    @endif
                </div>
            @empty
                <p class="text-gray-500">هنوز فیلدی به این فرم اضافه نشده است.</p>
            @endforelse

            <button type="submit" class="btn btn-primary w-full rounded-xl mt-4">ارسال فرم (غیرفعال در پیش‌نمایش)</button>
        </form>
    </div>

    <p class="mt-4 text-center">
        <a href="{{ route('admin.forms.edit', $form) }}" class="text-blue-600 hover:underline">بازگشت به ویرایش فرم</a>
        <span class="mx-2">|</span>
        <a href="{{ route('admin.forms.sites.edit', $form) }}" class="text-blue-600 hover:underline">اتصال به سایت‌ها</a>
        @if(isset($firstSite))
            <span class="mx-2">|</span>
            <a href="{{ route('site.forms.show_for_site', [$firstSite, $form->slug]) }}" class="text-blue-600 hover:underline" target="_blank">مشاهده در سایت (با کپچا)</a>
        @endif
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
