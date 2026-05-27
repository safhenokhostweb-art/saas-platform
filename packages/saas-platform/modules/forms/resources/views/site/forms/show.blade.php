@extends(config('forms.layouts.site'))

@php
    $pageTitle = trim((string) ($form->meta_title ?: $form->title));
    $pageDesc  = trim((string) ($form->meta_description ?: ''));
    if ($pageDesc === '' && $form->intro_text) {
        $pageDesc = \Illuminate\Support\Str::limit(strip_tags($form->intro_text), 160);
    }
    if ($pageDesc === '') {
        $pageDesc = 'فرم «' . $form->title . '» — ارسال اطلاعات';
    }
    $canonicalOverride = trim((string) ($form->canonical_url ?: ''));
    $isMultiStep = $form->isMultiStep();
    $steps = $form->getStepsConfig();
    $visibleFields = $form->fields->where('is_active', true);
    $fieldsByStep = [];
    if ($isMultiStep && ! empty($steps)) {
        foreach ($steps as $step) {
            $stepFields = $visibleFields->whereIn('id', $step['fields'] ?? [])->values();
            $fieldsByStep[] = ['title' => $step['title'] ?? '', 'fields' => $stepFields];
        }
    } else {
        $fieldsByStep[] = ['title' => '', 'fields' => $visibleFields->values()];
    }
    $totalSteps = count($fieldsByStep);
    $formatter = config('forms.date_formatter');
@endphp

@section('title', $pageTitle)
@section('meta_description', $pageDesc)
@if($canonicalOverride !== '')@section('canonical', $canonicalOverride)@endif
@if(trim((string) ($form->meta_robots ?? '')) !== '')@section('meta_robots', trim((string) $form->meta_robots))@endif

@section('breadcrumb')
    <a href="{{ route('home') }}">خانه</a>
    <span class="mx-1 text-slate-400">/</span>
    <a href="{{ route('forms.index') }}">فرم‌ها</a>
    <span class="mx-1 text-slate-400">/</span>
    <span class="text-slate-700">{{ $form->title }}</span>
@endsection

@section('content')
<div class="module-form-page" x-data="formRenderer({{ $totalSteps }})" x-cloak>
<div class="container mx-auto py-8 px-4 max-w-2xl">

    {{-- ─────────────── SUCCESS STATE ─────────────── --}}
    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-8 text-center mb-6" role="status">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
        </div>
        <p class="text-lg font-bold text-emerald-800 mb-1">{{ $form->getSuccessMessage() }}</p>
        <a href="{{ route('forms.index') }}" class="text-emerald-600 text-sm hover:underline mt-3 inline-block">بازگشت به فرم‌ها</a>
    </div>
    @endif

    {{-- ─────────────── FORM HEADER ─────────────── --}}
    <header class="mb-8">
        <h1 class="font-bold text-2xl md:text-3xl text-slate-900 mb-3">{{ $form->title }}</h1>
        @if($form->intro_text)
        <div class="text-slate-600 leading-relaxed border-r-4 border-indigo-300 pr-4 py-1 text-sm md:text-base">
            {!! strip_tags($form->intro_text, '<p><br><strong><em><u><a><ul><ol><li><h2><h3><h4><span>') !!}
        </div>
        @endif
    </header>

    {{-- ─────────────── MULTI-STEP PROGRESS ─────────────── --}}
    @if($isMultiStep && $totalSteps > 1)
    <div class="mb-6" x-show="!formSubmitted">
        <div class="flex items-center gap-2 mb-3">
            @foreach($fieldsByStep as $stepIndex => $step)
            <div class="flex items-center gap-2 flex-1">
                <div class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold transition"
                     :class="{
                       'bg-indigo-600 text-white': currentStep >= {{ $stepIndex }},
                       'bg-slate-200 text-slate-500': currentStep < {{ $stepIndex }}
                     }">
                    <template x-if="currentStep > {{ $stepIndex }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    </template>
                    <template x-if="currentStep <= {{ $stepIndex }}">
                        <span>{{ $stepIndex + 1 }}</span>
                    </template>
                </div>
                @if(!empty($step['title']))
                <span class="text-xs text-slate-600 hidden sm:block">{{ $step['title'] }}</span>
                @endif
                @if($stepIndex < $totalSteps - 1)
                <div class="flex-1 h-0.5 transition" :class="currentStep > {{ $stepIndex }} ? 'bg-indigo-400' : 'bg-slate-200'"></div>
                @endif
            </div>
            @endforeach
        </div>
        <p class="text-xs text-slate-400 text-center" x-text="`مرحله ${currentStep + 1} از {{ $totalSteps }}`"></p>
    </div>
    @endif

    {{-- ─────────────── VALIDATION ERRORS ─────────────── --}}
    @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-200 p-4 mb-6" role="alert">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/>
            </svg>
            <ul class="text-sm text-red-700 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- ─────────────── FORM ─────────────── --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden"
         x-show="!formSubmitted">

        <form method="POST" action="{{ route('forms.submit', $form->slug) }}" novalidate
              x-ref="formEl"
              @submit.prevent="handleSubmit">
            @csrf

            @foreach($fieldsByStep as $stepIndex => $step)
            <div x-show="currentStep === {{ $stepIndex }}"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-4"
                 x-transition:enter-end="opacity-100 translate-x-0">

                @if($isMultiStep && !empty($step['title']))
                <div class="px-6 pt-6 pb-2 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-800">{{ $step['title'] }}</h3>
                </div>
                @endif

                <div class="p-6 space-y-5">
                    @foreach($step['fields'] as $field)
                    @include('forms::site.forms._field', compact('field'))
                    @endforeach

                    {{-- Captcha (last step only) --}}
                    @if($stepIndex === $totalSteps - 1 && !empty($captcha['enabled'] ?? false))
                    <div class="pt-2">
                        <label class="form-label">تأیید هویت</label>
                        @if($captcha['type'] === 'persian_number' && !empty($captcha['persian_code']))
                            <div class="flex items-center gap-3 flex-wrap mt-1">
                                <x-persian-captcha-challenge :code="$captcha['persian_code']"/>
                                <input type="text" name="captcha_answer"
                                       class="form-input w-40" placeholder="ارقام بالا را وارد کنید"
                                       maxlength="8" required autocomplete="off" aria-label="پاسخ کپچا">
                            </div>
                        @elseif($captcha['type'] === 'normal' && !empty($captcha['normal_text']))
                            <div class="flex items-center gap-3 flex-wrap mt-1">
                                <x-persian-captcha-math :text="$captcha['normal_text']"/>
                                <input type="text" name="captcha_answer"
                                       class="form-input w-28" placeholder="جواب" maxlength="4"
                                       required autocomplete="off" aria-label="جواب کپچا">
                            </div>
                        @elseif($captcha['type'] === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
                            <div class="h-captcha mt-1" data-sitekey="{{ $captcha['hcaptcha_site_key'] }}" data-hl="fa"></div>
                        @endif
                        @error('captcha')
                            <p class="form-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif
                </div>
            </div>
            @endforeach

            {{-- ─── NAVIGATION / SUBMIT ─── --}}
            <div class="px-6 pb-6 flex items-center gap-3" :class="{{ $totalSteps > 1 ? 'true' : 'false' }} ? 'justify-between' : 'justify-end'">
                @if($totalSteps > 1)
                <button type="button"
                        x-show="currentStep > 0"
                        @click="prevStep()"
                        class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    مرحله قبل
                </button>
                <div x-show="currentStep === 0" class="w-1"></div>

                <button type="button"
                        x-show="currentStep < {{ $totalSteps - 1 }}"
                        @click="nextStep()"
                        class="flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 active:scale-95 transition">
                    مرحله بعد
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                </button>
                @endif

                <button type="submit"
                        x-show="{{ $totalSteps > 1 ? 'currentStep === ' . ($totalSteps - 1) : 'true' }}"
                        :disabled="isSubmitting"
                        class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed transition shadow-sm shadow-indigo-200">
                    <svg x-show="!isSubmitting" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12zm0 0h7.5"/></svg>
                    <svg x-show="isSubmitting" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    {{ $form->getSubmitButtonText() }}
                </button>
            </div>
        </form>
    </div>

    {{-- Inline success (no page reload) --}}
    <div x-show="formSubmitted"
         x-transition:enter="transition ease-out duration-500"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl border border-emerald-200 shadow-sm p-10 text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
        </div>
        <p class="text-lg font-bold text-slate-800 mb-1">{{ $form->getSuccessMessage() }}</p>
        <a href="{{ route('forms.index') }}" class="text-indigo-600 text-sm hover:underline mt-3 inline-block">بازگشت به فرم‌ها</a>
    </div>

</div>
</div>

{{-- Captcha JS --}}
@if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
@push('scripts')
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
@endpush
@endif

{{-- Date picker for date fields --}}
@if($visibleFields->contains('type', 'date'))
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
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

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('formRenderer', (totalSteps) => ({
    currentStep: 0,
    isSubmitting: false,
    formSubmitted: false,

    nextStep() {
      if (this.currentStep < totalSteps - 1) {
        this.currentStep++;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    },

    prevStep() {
      if (this.currentStep > 0) {
        this.currentStep--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    },

    handleSubmit(e) {
      this.isSubmitting = true;
      this.$refs.formEl.submit();
    },
  }));
});
</script>
@endpush

@push('json-ld')
<script type="application/ld+json">@json([
    '@context'    => 'https://schema.org',
    '@type'       => 'WebPage',
    'name'        => $pageTitle,
    'description' => \Illuminate\Support\Str::limit(strip_tags($pageDesc), 300),
    'url'         => url()->current(),
    'inLanguage'  => 'fa-IR',
], JSON_UNESCAPED_UNICODE)</script>
@endpush
@endsection
