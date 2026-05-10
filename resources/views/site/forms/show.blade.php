@extends('layouts.app')

@section('content')
<div class="container py-5">

    <h4 class="font-bold mb-4 text-xl">{{ $form->title }}</h4>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('site.forms.submit_for_site', [$site, $form->slug]) }}">
        @csrf

        @foreach($form->fields as $field)
            <div class="mb-3">
                <label class="form-label">
                    {{ $field->label }}
                    @if($field->is_required)
                        <span class="text-danger">*</span>
                    @endif
                </label>

                @if(in_array($field->type, ['text','email','number']))
                    <input type="{{ $field->type }}" name="field_{{ $field->id }}" class="form-control" @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif>
                @elseif($field->type === 'textarea')
                    <textarea name="field_{{ $field->id }}" class="form-control" @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif></textarea>
                @elseif($field->type === 'date')
                    <x-persian-date-input
                        name="field_{{ $field->id }}"
                        value="{{ old('field_'.$field->id) }}"
                        placeholder="۱۴۰۳/۰۷/۱۵"
                        class="form-control persian-date-input"
                    />
                @elseif(in_array($field->type, ['select','radio','checkbox']))
                    @foreach($field->options as $opt)
                        <div class="form-check">
                            <input class="form-check-input" type="{{ $field->type === 'select' ? 'radio' : $field->type }}"
                                   name="field_{{ $field->id }}" value="{{ $opt }}">
                            <label class="form-check-label">{{ $opt }}</label>
                        </div>
                    @endforeach
                @endif

                @error('field_'.$field->id)
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        @endforeach

        @if(!empty($captcha['enabled'] ?? false))
            <div class="mb-3">
                <label class="form-label">کپچا</label>
                @if($captcha['type'] === 'persian_number' && !empty($captcha['persian_code']))
                    <div class="flex items-center gap-2 flex-wrap">
                        <x-persian-captcha-challenge :code="$captcha['persian_code']" id="form-captcha-persian" />
                        <input type="text" name="captcha_answer" class="form-control w-32" placeholder="عدد بالا را وارد کنید" maxlength="6" required autocomplete="off" inputmode="numeric">
                    </div>
                @elseif($captcha['type'] === 'normal' && !empty($captcha['normal_text']))
                    <div class="flex items-center gap-2 flex-wrap">
                        <x-persian-captcha-math :text="$captcha['normal_text']" />
                        <input type="text" name="captcha_answer" class="form-control w-24" placeholder="جواب" maxlength="4" required autocomplete="off" inputmode="numeric">
                    </div>
                @elseif($captcha['type'] === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
                    <div class="h-captcha" data-sitekey="{{ $captcha['hcaptcha_site_key'] }}" data-hl="fa"></div>
                @endif
                @error('captcha')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <button class="btn btn-success w-full rounded-xl">ارسال فرم</button>
    </form>
</div>

@if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
@push('scripts')
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
@endpush
@endif

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
