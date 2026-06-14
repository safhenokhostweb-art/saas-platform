@extends('layouts.admin')

@section('title', 'ساخت قالب با هوش مصنوعی')

@section('content')
<div dir="rtl" class="max-w-3xl mx-auto space-y-6"
     x-data="{
       url: '',
       notes: '',
       name: '',
       loading: false,
       step: 'form',
       error: '',
       themeJson: null,
       progress: 0,
       progressMsg: '',
       progressTimer: null,

       async analyze() {
         if (!this.url) return;
         this.loading = true;
         this.error   = '';
         this.step    = 'loading';
         this.progress = 5;
         this.progressMsg = 'در حال دریافت صفحه...';

         this.progressTimer = setInterval(() => {
           if (this.progress < 80) {
             this.progress += Math.random() * 8;
             if      (this.progress < 30) this.progressMsg = 'در حال دریافت صفحه...';
             else if (this.progress < 55) this.progressMsg = 'استخراج اطلاعات طراحی...';
             else if (this.progress < 72) this.progressMsg = 'ارسال به Claude AI...';
             else                          this.progressMsg = 'در حال تولید قالب...';
           }
         }, 600);

         try {
           const resp = await fetch('{{ route('admin.theme-builder.ai-analyze-ajax') }}', {
             method: 'POST',
             headers: {
               'Content-Type': 'application/json',
               'X-CSRF-TOKEN': '{{ csrf_token() }}',
               'Accept': 'application/json',
             },
             body: JSON.stringify({ url: this.url, notes: this.notes, name: this.name }),
           });

           clearInterval(this.progressTimer);
           this.progress = 100;
           this.progressMsg = 'تمام!';

           const data = await resp.json();

           if (!data.success) {
             this.error = data.error || 'خطای ناشناخته';
             this.step  = 'form';
           } else {
             this.themeJson = data.theme_json;
             if (!this.name && data.theme_json.suggested_name) {
               this.name = data.theme_json.suggested_name;
             }
             this.step = 'preview';
           }
         } catch (err) {
           clearInterval(this.progressTimer);
           this.error = 'خطا در ارتباط با سرور: ' + err.message;
           this.step  = 'form';
         } finally {
           this.loading = false;
         }
       },

       countBlocks() {
         if (!this.themeJson) return 0;
         let n = (this.themeJson.header_blocks || []).length +
                 (this.themeJson.footer_blocks || []).length;
         const pages = this.themeJson.pages || {};
         for (const p of Object.values(pages)) n += (p.blocks || []).length;
         return n;
       }
     }">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">ساخت قالب با هوش مصنوعی</h1>
      <p class="text-sm text-gray-500 mt-1">آدرس سایت دلخواه را وارد کنید — {{ $providerLabel }} آن را تحلیل و یک قالب کامل می‌سازد</p>
    </div>
    <a href="{{ route('admin.theme-builder.index') }}"
       class="text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1.5">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      بازگشت
    </a>
  </div>

  @if(!$enabled)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-4">
      <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      </div>
      <div>
        <p class="font-semibold text-amber-800">{{ $envKeyName }} تنظیم نشده</p>
        <p class="text-amber-700 text-sm mt-1">برای استفاده از این قابلیت، متغیر <code class="bg-amber-100 px-1 rounded font-mono">{{ $envKeyName }}</code> را در <code class="bg-amber-100 px-1 rounded font-mono">.env</code> تنظیم کنید.</p>
      </div>
    </div>
  @endif

  @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
  @endif

  {{-- ── STEP 1: FORM ── --}}
  <div x-show="step === 'form'" x-cloak>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">

      <div x-show="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm mb-6" x-text="error"></div>

      <div class="space-y-5">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">آدرس سایت مورد نظر *</label>
          <div class="flex gap-2">
            <input type="url" x-model="url"
              placeholder="https://example.com"
              class="flex-1 border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent font-mono ltr"
              @keydown.enter.prevent="analyze()"
              @input.debounce="error = ''">
          </div>
          <p class="text-xs text-gray-400 mt-1.5">آدرس کامل با https یا http وارد کنید</p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">نام قالب <span class="text-gray-400 font-normal">(اختیاری — Claude پیشنهاد می‌دهد)</span></label>
          <input type="text" x-model="name"
            placeholder="مثلاً: قالب طلایی فروشگاه"
            class="w-full border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">توضیحات اضافه <span class="text-gray-400 font-normal">(اختیاری)</span></label>
          <textarea x-model="notes" rows="3"
            placeholder="مثلاً: این یک فروشگاه لوازم ورزشی است. می‌خواهم رنگ اصلی قرمز باشد و بخش courses حتماً داشته باشد."
            class="w-full border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"></textarea>
        </div>
      </div>

      <div class="mt-7">
        <button @click="analyze()" :disabled="!url || loading"
          class="w-full inline-flex items-center justify-center gap-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-4 rounded-xl text-base transition shadow-lg shadow-indigo-200">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
          تحلیل با {{ $providerLabel }} و ساخت قالب
        </button>
      </div>

      {{-- Info cards --}}
      <div class="mt-6 grid grid-cols-3 gap-3">
        <div class="bg-indigo-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-indigo-600">۱</div>
          <div class="text-xs text-indigo-700 mt-1">URL وارد کنید</div>
        </div>
        <div class="bg-purple-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-purple-600">۲</div>
          <div class="text-xs text-purple-700 mt-1">{{ $providerLabel }} تحلیل می‌کند</div>
        </div>
        <div class="bg-pink-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-pink-600">۳</div>
          <div class="text-xs text-pink-700 mt-1">قالب آماده می‌شود</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── STEP 2: LOADING ── --}}
  <div x-show="step === 'loading'" x-cloak>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
      <div class="w-20 h-20 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center mx-auto mb-6 relative">
        <svg class="w-10 h-10 text-indigo-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
        <div class="absolute inset-0 rounded-full border-4 border-indigo-200 border-t-indigo-600 animate-spin"></div>
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">در حال پردازش با {{ $providerLabel }}</h2>
      <p class="text-gray-500 text-sm mb-8" x-text="progressMsg">در حال دریافت صفحه...</p>

      {{-- Progress bar --}}
      <div class="max-w-sm mx-auto bg-gray-100 rounded-full h-2.5 overflow-hidden">
        <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 transition-all duration-500"
             :style="'width:' + Math.min(progress, 100) + '%'"></div>
      </div>
      <p class="text-xs text-gray-400 mt-2" x-text="Math.round(Math.min(progress, 100)) + '%'"></p>

      <p class="mt-8 text-xs text-gray-400">معمولاً ۱۵ تا ۳۰ ثانیه طول می‌کشد</p>
    </div>
  </div>

  {{-- ── STEP 3: PREVIEW ── --}}
  <div x-show="step === 'preview'" x-cloak>
    {{-- Success banner --}}
    <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl p-5 text-white flex items-center gap-4 mb-4">
      <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-bold text-lg" x-text="'قالب «' + (themeJson?.suggested_name || '') + '» آماده است!'"></p>
        <p class="text-white/80 text-sm mt-0.5" x-text="countBlocks() + ' بلوک تولید شد — اکنون می‌توانید ذخیره و ویرایش کنید'"></p>
      </div>
    </div>

    {{-- Theme summary cards --}}
    <template x-if="themeJson">
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
          <p class="text-2xl font-bold text-indigo-600" x-text="(themeJson.header_blocks || []).length"></p>
          <p class="text-xs text-gray-500 mt-1">بلوک هدر</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
          <p class="text-2xl font-bold text-purple-600" x-text="(themeJson.pages?.home?.blocks || []).length"></p>
          <p class="text-xs text-gray-500 mt-1">بلوک صفحه اصلی</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
          <p class="text-2xl font-bold text-pink-600" x-text="(themeJson.footer_blocks || []).length"></p>
          <p class="text-xs text-gray-500 mt-1">بلوک فوتر</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
          <div class="w-6 h-6 rounded-full mx-auto mb-1" :style="'background:' + (themeJson.global_styles?.primary_color || '#3b82f6')"></div>
          <p class="text-xs text-gray-500 font-mono" x-text="themeJson.global_styles?.primary_color || '#3b82f6'"></p>
        </div>
      </div>
    </template>

    {{-- Block list preview --}}
    <template x-if="themeJson">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-700">پیش‌نمایش بلوک‌ها</h3>
          <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-medium" x-text="countBlocks() + ' بلوک'"></span>
        </div>
        <div class="divide-y divide-gray-50 max-h-64 overflow-y-auto">
          {{-- Header blocks --}}
          <template x-if="(themeJson.header_blocks || []).length">
            <div class="px-6 py-3 bg-blue-50/50">
              <p class="text-xs font-semibold text-blue-700 mb-2">هدر</p>
              <template x-for="b in themeJson.header_blocks" :key="b.id">
                <div class="flex items-center gap-2 py-1">
                  <span class="w-2 h-2 bg-blue-400 rounded-full flex-shrink-0"></span>
                  <span class="text-xs font-mono text-gray-600" x-text="b.type"></span>
                  <span class="text-xs text-gray-400 truncate" x-text="b.props?.title || b.props?.logo_text || ''"></span>
                </div>
              </template>
            </div>
          </template>
          {{-- Home page blocks --}}
          <template x-if="(themeJson.pages?.home?.blocks || []).length">
            <div class="px-6 py-3 bg-purple-50/50">
              <p class="text-xs font-semibold text-purple-700 mb-2">صفحه اصلی</p>
              <template x-for="b in themeJson.pages.home.blocks" :key="b.id">
                <div class="flex items-center gap-2 py-1">
                  <span class="w-2 h-2 bg-purple-400 rounded-full flex-shrink-0"></span>
                  <span class="text-xs font-mono text-gray-600" x-text="b.type"></span>
                  <span class="text-xs text-gray-400 truncate" x-text="b.props?.title || b.props?.text || ''"></span>
                </div>
              </template>
            </div>
          </template>
          {{-- Footer blocks --}}
          <template x-if="(themeJson.footer_blocks || []).length">
            <div class="px-6 py-3 bg-gray-50/50">
              <p class="text-xs font-semibold text-gray-600 mb-2">فوتر</p>
              <template x-for="b in themeJson.footer_blocks" :key="b.id">
                <div class="flex items-center gap-2 py-1">
                  <span class="w-2 h-2 bg-gray-400 rounded-full flex-shrink-0"></span>
                  <span class="text-xs font-mono text-gray-600" x-text="b.type"></span>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>
    </template>

    {{-- Action buttons --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <p class="text-sm font-semibold text-gray-700 mb-4">نام نهایی قالب</p>
      <input type="text" x-model="name"
        class="w-full border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent mb-5">

      <form method="POST" action="{{ route('admin.theme-builder.ai-generate') }}" class="contents">
        @csrf
        <input type="hidden" name="name" :value="name">

        {{-- Store themeJson in hidden field via JS --}}
        <script>
          document.addEventListener('alpine:initialized', () => {
            // will be set dynamically on submit
          });
        </script>

        <div class="flex gap-3">
          <button type="submit"
            :disabled="!name"
            @click.prevent="
              const form = $el.closest('form');
              // Persist JSON to session via separate POST, then submit
              fetch('{{ route('admin.theme-builder.ai-generate-prepare') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ theme_json: themeJson, name: name }),
              }).then(r => r.json()).then(d => {
                if (d.success) form.submit();
                else alert(d.error || 'خطا');
              }).catch(e => alert('خطا: ' + e.message));
            "
            class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold py-3 rounded-xl transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            ذخیره و باز کردن ویرایشگر
          </button>
          <button type="button" @click="step = 'form'; themeJson = null; progress = 0;"
            class="px-5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition">
            تحلیل مجدد
          </button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection
