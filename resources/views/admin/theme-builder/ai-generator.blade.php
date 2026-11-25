@extends('layouts.admin')
@section('title', 'مولد هوشمند قالب با AI')

@section('content')
<div dir="rtl" class="max-w-4xl mx-auto space-y-6"
     x-data="{
       /* ── state ── */
       inputType: 'url',
       url: '', notes: '', name: '', prompt: '',
       screenshotFile: null, screenshotPreview: null,
       htmlFile: null, htmlFileName: '',
       step: 'form',           // form | detecting | sections | generating | done | error
       errorMsg: '',
       progress: 0,
       progressMsg: '',
       progressTimer: null,

       /* ── job data ── */
       jobId: null,
       suggestedName: '',
       detectedSections: [],
       resultUrl: '',
       packSummary: null,

       /* ── helpers ── */
       inputTabs: [
         { key: 'url',        label: 'آدرس سایت',   icon: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1' },
         { key: 'screenshot', label: 'تصویر صفحه',   icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
         { key: 'html',       label: 'کد HTML',      icon: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' },
         { key: 'prompt',     label: 'توضیح متنی',   icon: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z' },
       ],

       sectionIcons: {
         header:       'M4 6h16M4 12h16M4 18h16',
         hero:         'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
         features:     'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
         services:     'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
         pricing:      'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
         faq:          'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
         testimonials: 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z',
         blog:         'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
         products:     'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
         cta:          'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5',
         footer:       'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
       },

       confidenceColor(c) {
         if (c >= 70) return 'bg-emerald-100 text-emerald-700';
         if (c >= 45) return 'bg-amber-100 text-amber-700';
         return 'bg-gray-100 text-gray-500';
       },

       onScreenshotChange(event) {
         const file = event.target.files[0];
         if (!file) return;
         this.screenshotFile = file;
         const reader = new FileReader();
         reader.onload = e => this.screenshotPreview = e.target.result;
         reader.readAsDataURL(file);
       },

       onHtmlChange(event) {
         const file = event.target.files[0];
         if (!file) return;
         this.htmlFile = file;
         this.htmlFileName = file.name;
       },

       isFormValid() {
         if (this.inputType === 'url')        return this.url.trim().length > 5;
         if (this.inputType === 'screenshot') return !!this.screenshotFile;
         if (this.inputType === 'html')       return !!this.htmlFile;
         if (this.inputType === 'prompt')     return this.prompt.trim().length > 10;
         return false;
       },

       enabledCount() {
         return this.detectedSections.filter(s => s.enabled).length;
       },

       toggleSection(key) {
         const s = this.detectedSections.find(x => x.key === key);
         if (s) s.enabled = !s.enabled;
       },

       /* ── Step 1: detect ── */
       async detect() {
         if (!this.isFormValid()) return;
         this.step = 'detecting';
         this.progress = 5;
         this.progressMsg = 'در حال آماده‌سازی...';

         this.progressTimer = setInterval(() => {
           if (this.progress < 85) {
             this.progress += Math.random() * 12;
             if      (this.progress < 25) this.progressMsg = 'در حال دریافت محتوا...';
             else if (this.progress < 50) this.progressMsg = 'استخراج ساختار صفحه...';
             else if (this.progress < 72) this.progressMsg = 'شناسایی بخش‌ها...';
             else                          this.progressMsg = 'تحلیل رنگ‌ها و طراحی...';
           }
         }, 700);

         try {
           const formData = new FormData();
           formData.append('input_type', this.inputType);
           formData.append('notes',      this.notes);
           formData.append('name',       this.name);
           formData.append('_token',     '{{ csrf_token() }}');

           if (this.inputType === 'url')        formData.append('url',        this.url);
           if (this.inputType === 'screenshot') formData.append('screenshot', this.screenshotFile);
           if (this.inputType === 'html')       formData.append('html_file',  this.htmlFile);
           if (this.inputType === 'prompt')     formData.append('prompt',     this.prompt);

           const resp = await fetch('{{ route('admin.theme-builder.ai-generator.detect') }}', {
             method: 'POST',
             body:   formData,
             headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
           });

           clearInterval(this.progressTimer);
           this.progress = 100;

           const data = await resp.json();
           if (!data.success) throw new Error(data.error || 'خطای ناشناخته');

           this.jobId            = data.job_id;
           this.suggestedName    = data.suggested_name || this.name || 'قالب جدید';
           this.detectedSections = data.detected_sections || [];
           this.step             = 'sections';

         } catch (err) {
           clearInterval(this.progressTimer);
           this.errorMsg = err.message;
           this.step     = 'error';
         }
       },

       /* ── Step 2: generate ── */
       async generate() {
         const selected = this.detectedSections.filter(s => s.enabled).map(s => s.key);
         if (selected.length === 0) { alert('حداقل یک بخش را فعال کنید.'); return; }
         if (!this.suggestedName.trim()) { alert('نام قالب را وارد کنید.'); return; }

         this.step     = 'generating';
         this.progress = 5;
         this.progressMsg = 'آماده‌سازی ساختار قالب...';

         this.progressTimer = setInterval(() => {
           if (this.progress < 88) {
             this.progress += Math.random() * 8;
             const msgs = [
               'تولید هدر و فوتر...',
               'ساخت بلوک‌های هیرو...',
               'طراحی بخش ویژگی‌ها...',
               'ساخت کارت‌های قیمت‌گذاری...',
               'تولید محتوای فارسی...',
               'نهایی‌سازی قالب‌ها...',
             ];
             this.progressMsg = msgs[Math.floor(this.progress / 15) % msgs.length];
           }
         }, 900);

         try {
           const resp = await fetch(`{{ url('admin/theme-builder/ai-generator') }}/${this.jobId}/generate`, {
             method: 'POST',
             headers: {
               'Content-Type': 'application/json',
               'X-CSRF-TOKEN': '{{ csrf_token() }}',
               'Accept':       'application/json',
             },
             body: JSON.stringify({
               name:     this.suggestedName,
               sections: selected,
             }),
           });

           clearInterval(this.progressTimer);
           this.progress = 100;

           const data = await resp.json();
           if (!data.success) throw new Error(data.error || 'خطای ناشناخته');

           this.resultUrl  = data.result_url;
           this.packSummary = data.summary;
           this.step       = 'done';

         } catch (err) {
           clearInterval(this.progressTimer);
           this.errorMsg = err.message;
           this.step     = 'error';
         }
       },

       reset() {
         this.step = 'form'; this.errorMsg = ''; this.progress = 0;
         this.jobId = null; this.detectedSections = []; this.resultUrl = '';
         this.screenshotFile = null; this.screenshotPreview = null;
         this.htmlFile = null; this.htmlFileName = '';
       }
     }">

  {{-- ── Header ── --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="w-9 h-9 bg-gradient-to-br from-violet-500 to-purple-600 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
          </svg>
        </span>
        مولد هوشمند قالب با {{ $providerLabel }}
      </h1>
      <p class="text-sm text-gray-500 mt-1">از URL، تصویر، HTML یا توضیح متنی — یک قالب حرفه‌ای کامل بسازید</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ route('admin.theme-builder.ai-form') }}"
         class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">تحلیل URL ساده</a>
      <span class="text-gray-300">|</span>
      <a href="{{ route('admin.theme-builder.index') }}"
         class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        بازگشت
      </a>
    </div>
  </div>

  @if(!$enabled)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-4">
      <svg class="w-6 h-6 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <div>
        <p class="font-semibold text-amber-800">{{ $envKeyName }} تنظیم نشده</p>
        <p class="text-sm text-amber-700 mt-1">متغیر <code class="bg-amber-100 px-1 rounded">{{ $envKeyName }}</code> را در <code class="bg-amber-100 px-1 rounded">.env</code> تنظیم کنید تا مرحله تولید قالب فعال شود. تحلیل URL، HTML و توضیح متنی بدون API key کار می‌کنند.</p>
      </div>
    </div>
  @endif

  {{-- ════════════════════════════════════════════════════════════ --}}
  {{-- STEP: FORM --}}
  {{-- ════════════════════════════════════════════════════════════ --}}
  <div x-show="step === 'form'" x-cloak>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

      {{-- Input type tabs --}}
      <div class="flex border-b border-gray-100">
        <template x-for="tab in inputTabs" :key="tab.key">
          <button
            @click="inputType = tab.key"
            class="flex-1 flex flex-col items-center gap-1.5 py-4 px-3 text-xs font-medium transition border-b-2"
            :class="inputType === tab.key
              ? 'border-violet-600 text-violet-700 bg-violet-50/50'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"/>
            </svg>
            <span x-text="tab.label"></span>
          </button>
        </template>
      </div>

      <div class="p-7 space-y-5">

        {{-- ── Tab: URL ── --}}
        <div x-show="inputType === 'url'" x-cloak class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">آدرس سایت مورد نظر *</label>
            <input type="url" x-model="url"
              placeholder="https://example.com"
              class="w-full border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent font-mono ltr"
              @keydown.enter.prevent="detect()">
            <p class="text-xs text-gray-400 mt-1">آدرس کامل سایت. AI ساختار صفحه را تحلیل می‌کند.</p>
          </div>
          <div class="bg-blue-50 rounded-xl p-4 text-sm text-blue-700">
            <p class="font-semibold mb-1">پایپ‌لاین تحلیل URL:</p>
            <p class="text-xs text-blue-600">دریافت صفحه → استخراج ساختار → شناسایی بخش‌ها → تولید قالب</p>
          </div>
        </div>

        {{-- ── Tab: Screenshot ── --}}
        <div x-show="inputType === 'screenshot'" x-cloak class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">تصویر از صفحه وب *</label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center cursor-pointer hover:border-violet-400 transition"
                 @click="$refs.screenshotInput.click()">
              <template x-if="!screenshotPreview">
                <div>
                  <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                  <p class="text-sm text-gray-500">کلیک کنید یا تصویر را اینجا بکشید</p>
                  <p class="text-xs text-gray-400 mt-1">JPG، PNG — حداکثر ۱۰ مگابایت</p>
                </div>
              </template>
              <template x-if="screenshotPreview">
                <img :src="screenshotPreview" class="max-h-48 mx-auto rounded-lg object-contain">
              </template>
            </div>
            <input type="file" accept="image/*" class="hidden" x-ref="screenshotInput"
                   @change="onScreenshotChange($event)">
          </div>
          <div class="bg-purple-50 rounded-xl p-4 text-xs text-purple-700">
            GapGPT AI تصویر را با Vision تحلیل می‌کند و بخش‌ها را بدون کپی محتوا شناسایی می‌کند.
          </div>
        </div>

        {{-- ── Tab: HTML ── --}}
        <div x-show="inputType === 'html'" x-cloak class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">فایل HTML *</label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center cursor-pointer hover:border-violet-400 transition"
                 @click="$refs.htmlInput.click()">
              <template x-if="!htmlFileName">
                <div>
                  <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                  <p class="text-sm text-gray-500">فایل index.html یا هر صفحه HTML را آپلود کنید</p>
                  <p class="text-xs text-gray-400 mt-1">HTML, HTM — حداکثر ۵ مگابایت</p>
                </div>
              </template>
              <template x-if="htmlFileName">
                <div class="flex items-center justify-center gap-3">
                  <svg class="w-8 h-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                  <span class="text-sm font-medium text-gray-700 font-mono" x-text="htmlFileName"></span>
                </div>
              </template>
            </div>
            <input type="file" accept=".html,.htm,.txt" class="hidden" x-ref="htmlInput"
                   @change="onHtmlChange($event)">
          </div>
          <div class="bg-green-50 rounded-xl p-4 text-xs text-green-700">
            فقط ساختار HTML تحلیل می‌شود — هیچ کد یا محتوای اختصاصی کپی نمی‌شود (حالت ایمن).
          </div>
        </div>

        {{-- ── Tab: Prompt ── --}}
        <div x-show="inputType === 'prompt'" x-cloak class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">توضیح قالب مورد نظر *</label>
            <textarea x-model="prompt" rows="5"
              placeholder="مثلاً: یک قالب برای فروشگاه آنلاین لوازم ورزشی با رنگ اصلی قرمز. می‌خواهم بخش‌های هیرو، ویژگی‌ها، محصولات ویژه، نظرات مشتریان، و فراخوان عمل داشته باشد. سبک مدرن و حرفه‌ای."
              class="w-full border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none"></textarea>
            <p class="text-xs text-gray-400 mt-1">هرچه جزئیات بیشتری بدهید، قالب دقیق‌تری تولید می‌شود.</p>
          </div>
        </div>

        {{-- Common fields --}}
        <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-50">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">نام قالب <span class="text-gray-400 font-normal">(اختیاری)</span></label>
            <input type="text" x-model="name"
              placeholder="مثلاً: قالب فروشگاه طلایی"
              class="w-full border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">توضیحات اضافه <span class="text-gray-400 font-normal">(اختیاری)</span></label>
            <input type="text" x-model="notes"
              placeholder="رنگ‌ها، سبک، بخش‌های خاص..."
              class="w-full border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent">
          </div>
        </div>

        <button @click="detect()" :disabled="!isFormValid()"
          class="w-full inline-flex items-center justify-center gap-3 bg-gradient-to-r from-violet-600 to-purple-700 hover:from-violet-700 hover:to-purple-800 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold py-4 rounded-xl text-base transition shadow-lg shadow-violet-200">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          تحلیل و شناسایی بخش‌ها
        </button>
      </div>
    </div>

    {{-- How it works --}}
    <div class="grid grid-cols-4 gap-3">
      @foreach([
        ['۱', 'ورودی', 'URL، تصویر، HTML یا توضیح متنی', 'from-violet-400 to-violet-600'],
        ['۲', 'شناسایی', 'تحلیل ساختار و شناسایی بخش‌ها', 'from-indigo-400 to-indigo-600'],
        ['۳', 'انتخاب', 'فعال/غیرفعال کردن هر بخش', 'from-blue-400 to-blue-600'],
        ['۴', 'تولید', 'پک قالب کامل با محتوای فارسی', 'from-emerald-400 to-emerald-600'],
      ] as [$num, $title, $desc, $grad])
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center shadow-sm">
        <div class="w-10 h-10 bg-gradient-to-br {{ $grad }} rounded-xl flex items-center justify-center mx-auto mb-2">
          <span class="text-white font-bold text-sm">{{ $num }}</span>
        </div>
        <p class="text-sm font-semibold text-gray-700">{{ $title }}</p>
        <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $desc }}</p>
      </div>
      @endforeach
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════ --}}
  {{-- STEP: DETECTING (loading) --}}
  {{-- ════════════════════════════════════════════════════════════ --}}
  <div x-show="step === 'detecting'" x-cloak>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-14 text-center">
      <div class="w-24 h-24 rounded-full bg-gradient-to-br from-violet-100 to-purple-100 flex items-center justify-center mx-auto mb-6 relative">
        <svg class="w-12 h-12 text-violet-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <div class="absolute inset-0 rounded-full border-4 border-violet-200 border-t-violet-600 animate-spin"></div>
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">در حال تحلیل</h2>
      <p class="text-gray-500 text-sm mb-8" x-text="progressMsg"></p>
      <div class="max-w-xs mx-auto bg-gray-100 rounded-full h-2.5 overflow-hidden">
        <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-purple-600 transition-all duration-500"
             :style="'width:' + Math.min(progress, 100) + '%'"></div>
      </div>
      <p class="text-xs text-gray-400 mt-3" x-text="Math.round(Math.min(progress, 100)) + '٪'"></p>
      <p class="mt-8 text-xs text-gray-400">معمولاً ۱۰ تا ۲۰ ثانیه طول می‌کشد</p>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════ --}}
  {{-- STEP: SECTION SELECTION --}}
  {{-- ════════════════════════════════════════════════════════════ --}}
  <div x-show="step === 'sections'" x-cloak class="space-y-4">

    <div class="bg-gradient-to-r from-violet-500 to-purple-600 rounded-2xl p-5 text-white flex items-center gap-4">
      <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </svg>
      </div>
      <div>
        <p class="font-bold text-lg">بخش‌های شناسایی‌شده</p>
        <p class="text-white/80 text-sm mt-0.5">بخش‌هایی که می‌خواهید در قالب باشند را فعال کنید</p>
      </div>
    </div>

    {{-- Name field --}}
    <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
      <label class="block text-sm font-semibold text-gray-700 mb-2">نام قالب *</label>
      <input type="text" x-model="suggestedName"
        class="w-full border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
        placeholder="نام نهایی قالب شما">
    </div>

    {{-- Sections grid --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">
          بخش‌های شناسایی‌شده
          <span class="mr-2 bg-violet-100 text-violet-700 text-xs px-2 py-0.5 rounded-full font-medium"
                x-text="detectedSections.length + ' بخش'"></span>
        </h3>
        <div class="flex gap-2 text-xs">
          <button @click="detectedSections.forEach(s => s.enabled = true)"
            class="text-violet-600 hover:text-violet-800 font-medium">انتخاب همه</button>
          <span class="text-gray-300">|</span>
          <button @click="detectedSections.forEach(s => { if (!['header','footer'].includes(s.key)) s.enabled = false; })"
            class="text-gray-500 hover:text-gray-700">پاک کردن</button>
        </div>
      </div>

      <div class="divide-y divide-gray-50">
        <template x-for="section in detectedSections" :key="section.key">
          <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50/50 transition cursor-pointer"
               @click="toggleSection(section.key)">

            {{-- Toggle --}}
            <div class="flex-shrink-0">
              <div class="w-10 h-6 rounded-full transition-colors duration-200 flex items-center px-1"
                   :class="section.enabled ? 'bg-violet-500' : 'bg-gray-200'">
                <div class="w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                     :class="section.enabled ? '-translate-x-4' : 'translate-x-0'"></div>
              </div>
            </div>

            {{-- Icon --}}
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors"
                 :class="section.enabled ? 'bg-violet-100' : 'bg-gray-100'">
              <svg class="w-4.5 h-4.5 transition-colors" :class="section.enabled ? 'text-violet-600' : 'text-gray-400'"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      :d="sectionIcons[section.key] || 'M4 6h16M4 12h16M4 18h16'"/>
              </svg>
            </div>

            {{-- Info --}}
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800" x-text="section.label"></p>
              <p class="text-xs text-gray-500 truncate" x-text="section.description || ''"></p>
            </div>

            {{-- Confidence --}}
            <div class="flex-shrink-0">
              <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                    :class="confidenceColor(section.confidence)"
                    x-text="section.confidence + '٪'"></span>
            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- Safe mode notice --}}
    <div class="bg-green-50 border border-green-100 rounded-xl p-4 flex items-start gap-3">
      <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
      </svg>
      <div class="text-xs text-green-700">
        <span class="font-semibold">حالت ایمن فعال: </span>
        هیچ کد یا تصویر اختصاصی از منبع کپی نمی‌شود. تمام محتوا به‌صورت اصیل فارسی تولید می‌شود.
      </div>
    </div>

    {{-- Actions --}}
    <div class="flex gap-3">
      <button @click="generate()" :disabled="enabledCount() === 0"
        class="flex-1 inline-flex items-center justify-center gap-2 bg-gradient-to-r from-violet-600 to-purple-700 hover:from-violet-700 hover:to-purple-800 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold py-4 rounded-xl transition shadow-lg shadow-violet-200">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
        </svg>
        تولید پک قالب کامل
        <span class="bg-white/20 text-xs px-2 py-0.5 rounded-full"
              x-text="enabledCount() + ' بخش'"></span>
      </button>
      <button @click="reset()"
        class="px-5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition text-sm">
        شروع مجدد
      </button>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════ --}}
  {{-- STEP: GENERATING --}}
  {{-- ════════════════════════════════════════════════════════════ --}}
  <div x-show="step === 'generating'" x-cloak>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-14 text-center">
      <div class="w-24 h-24 rounded-full bg-gradient-to-br from-emerald-100 to-teal-100 flex items-center justify-center mx-auto mb-6 relative">
        <svg class="w-12 h-12 text-emerald-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
        </svg>
        <div class="absolute inset-0 rounded-full border-4 border-emerald-200 border-t-emerald-600 animate-spin"></div>
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">در حال تولید قالب</h2>
      <p class="text-gray-500 text-sm mb-8" x-text="progressMsg"></p>
      <div class="max-w-xs mx-auto bg-gray-100 rounded-full h-2.5 overflow-hidden">
        <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-600 transition-all duration-500"
             :style="'width:' + Math.min(progress, 100) + '%'"></div>
      </div>
      <p class="text-xs text-gray-400 mt-3" x-text="Math.round(Math.min(progress, 100)) + '٪'"></p>
      <p class="mt-8 text-xs text-gray-400">معمولاً ۲۰ تا ۴۵ ثانیه — {{ $providerLabel }} در حال طراحی قالب شماست</p>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════ --}}
  {{-- STEP: DONE --}}
  {{-- ════════════════════════════════════════════════════════════ --}}
  <div x-show="step === 'done'" x-cloak class="space-y-4">

    {{-- Success banner --}}
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-2xl p-6 text-white flex items-center gap-5">
      <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center flex-shrink-0">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <div>
        <p class="font-bold text-xl" x-text="'قالب «' + suggestedName + '» آماده است!'"></p>
        <p class="text-white/80 text-sm mt-1">پک قالب کامل با بلوک‌ها، استایل‌ها و تمپلیت‌ها تولید شد</p>
      </div>
    </div>

    {{-- Summary cards --}}
    <template x-if="packSummary">
      <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
        <template x-for="[label, value] in [
          ['هدرها', packSummary.headers],
          ['فوترها', packSummary.footers],
          ['صفحات', packSummary.pages],
          ['تمپلیت‌ها', packSummary.templates],
          ['استایل جهانی', packSummary.global_styles ? '✓' : '—'],
          ['داده نمونه', packSummary.demo_data ? '✓' : '—'],
        ]" :key="label">
          <div class="bg-white rounded-xl border border-gray-100 p-3 text-center shadow-sm">
            <p class="text-xl font-bold text-violet-600" x-text="value"></p>
            <p class="text-xs text-gray-500 mt-0.5" x-text="label"></p>
          </div>
        </template>
      </div>
    </template>

    {{-- Action buttons --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
      <p class="text-sm font-semibold text-gray-700 mb-4">اقدامات بعدی</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <a :href="resultUrl"
           class="flex items-center justify-center gap-2 bg-violet-600 hover:bg-violet-700 text-white font-semibold py-3 rounded-xl transition text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
          مشاهده نتیجه
        </a>
        <a :href="`{{ url('admin/theme-builder/ai-generator') }}/${jobId}/install`"
           @click.prevent="if(confirm('این پک را به‌عنوان قالب نصب کنیم و ویرایشگر را باز کنیم؟')) window.location = $el.href"
           class="flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-xl transition text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
          </svg>
          نصب و ویرایش در کانواس
        </a>
        <a :href="`{{ url('admin/theme-builder/ai-generator') }}/${jobId}/export`"
           class="flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 rounded-xl transition text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          دانلود JSON پک
        </a>
      </div>
      <div class="mt-4 text-center">
        <button @click="reset()"
          class="text-sm text-violet-600 hover:text-violet-800 font-medium">
          + تولید قالب دیگری
        </button>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════ --}}
  {{-- STEP: ERROR --}}
  {{-- ════════════════════════════════════════════════════════════ --}}
  <div x-show="step === 'error'" x-cloak>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-8 text-center">
      <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <h3 class="text-lg font-bold text-red-800 mb-2">خطا در پردازش</h3>
      <p class="text-red-600 text-sm mb-6" x-text="errorMsg"></p>
      <button @click="reset()"
        class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-xl transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        تلاش مجدد
      </button>
    </div>
  </div>

</div>
@endsection
