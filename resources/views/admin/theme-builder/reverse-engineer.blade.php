@extends('layouts.admin')

@section('title', 'موتور مهندسی معکوس بصری')

@section('content')
<div class="min-h-screen bg-gray-50 py-8" dir="rtl">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="mb-8">
      <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-600 to-indigo-600 flex items-center justify-center shadow-lg">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">موتور مهندسی معکوس بصری</h1>
          <p class="text-sm text-gray-500">TB3 Sprint 9.5 — Visual Reverse Engineering Engine</p>
        </div>
      </div>
      <p class="text-gray-600 mt-2">
        آدرس، تصویر، HTML یا توضیح یک سایت را وارد کنید. موتور ساختار، رنگ، چیدمان و الگوهای تجاری آن را تشخیص داده و یک ThemePack فارسی مشابه تولید می‌کند.
      </p>
    </div>

    @if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
      {{ session('error') }}
    </div>
    @endif

    {{-- Reference Sites Banner --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6 shadow-sm">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">سایت‌های مرجع شباهت‌سنجی</p>
      <div class="flex flex-wrap gap-2">
        @foreach([
          ['Apple','from-gray-800 to-gray-900','🍎'],
          ['Amazon','from-orange-500 to-orange-600','📦'],
          ['Malltina','from-red-500 to-rose-600','🛒'],
          ['Digikala','from-red-600 to-red-700','🛍️'],
          ['Stripe','from-indigo-500 to-violet-600','💳'],
          ['Shopify','from-green-500 to-emerald-600','🏪'],
        ] as [$site, $grad, $emoji])
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gradient-to-r {{ $grad }} text-white text-xs font-medium shadow-sm">
          {{ $emoji }} {{ $site }}
        </span>
        @endforeach
      </div>
    </div>

    {{-- 7-Phase Pipeline Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-6">
      @foreach([
        ['1','استخراج DOM','violet'],
        ['2','بازسازی چیدمان','indigo'],
        ['3','تحلیل بصری','blue'],
        ['4','تحلیل تجاری','cyan'],
        ['5','شباهت‌سنجی','teal'],
        ['6','بازسازی قالب','emerald'],
        ['7','اعتبارسنجی','amber'],
      ] as [$num, $label, $color])
      <div class="bg-white rounded-xl border border-gray-200 p-3 text-center shadow-sm">
        <div class="w-7 h-7 rounded-lg bg-{{ $color }}-100 text-{{ $color }}-700 text-xs font-bold flex items-center justify-center mx-auto mb-1.5">{{ $num }}</div>
        <p class="text-xs text-gray-600 leading-tight">{{ $label }}</p>
      </div>
      @endforeach
    </div>

    {{-- Main Form --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden" x-data="reverseEngineerForm()">

      {{-- Input Type Tabs --}}
      <div class="border-b border-gray-100 p-5">
        <div class="grid grid-cols-4 gap-2">
          <button type="button" @click="inputType='url'"
            :class="inputType==='url' ? 'bg-violet-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
            class="flex flex-col items-center gap-1.5 py-3 rounded-xl text-sm font-medium transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
            <span>آدرس URL</span>
          </button>
          <button type="button" @click="inputType='screenshot'"
            :class="inputType==='screenshot' ? 'bg-violet-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
            class="flex flex-col items-center gap-1.5 py-3 rounded-xl text-sm font-medium transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span>تصویر</span>
          </button>
          <button type="button" @click="inputType='html'"
            :class="inputType==='html' ? 'bg-violet-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
            class="flex flex-col items-center gap-1.5 py-3 rounded-xl text-sm font-medium transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
            </svg>
            <span>فایل HTML</span>
          </button>
          <button type="button" @click="inputType='prompt'"
            :class="inputType==='prompt' ? 'bg-violet-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
            class="flex flex-col items-center gap-1.5 py-3 rounded-xl text-sm font-medium transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <span>توضیح متنی</span>
          </button>
        </div>
      </div>

      <form @submit.prevent="runAnalysis" class="p-6 space-y-5">

        {{-- URL Input --}}
        <div x-show="inputType==='url'" x-cloak>
          <label class="block text-sm font-medium text-gray-700 mb-2">آدرس وب‌سایت</label>
          <input type="url" x-model="url" placeholder="https://example.com"
            class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors" dir="ltr">
          <p class="mt-1.5 text-xs text-gray-400">موتور صفحه را دریافت کرده و ساختار DOM آن را استخراج می‌کند.</p>
        </div>

        {{-- Screenshot Input --}}
        <div x-show="inputType==='screenshot'" x-cloak>
          <label class="block text-sm font-medium text-gray-700 mb-2">تصویر صفحه‌نمایش</label>
          <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-violet-400 transition-colors cursor-pointer" @click="$refs.screenshotInput.click()">
            <template x-if="!screenshotPreview">
              <div>
                <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm text-gray-500">تصویر PNG یا JPG را اینجا رها کنید</p>
                <p class="text-xs text-gray-400 mt-1">یا کلیک کنید (حداکثر ۱۰ مگابایت)</p>
              </div>
            </template>
            <template x-if="screenshotPreview">
              <div>
                <img :src="screenshotPreview" class="max-h-48 mx-auto rounded-lg shadow-sm object-contain">
                <p class="text-xs text-gray-400 mt-2" x-text="screenshotName"></p>
              </div>
            </template>
          </div>
          <input type="file" accept="image/*" x-ref="screenshotInput" class="hidden"
            @change="handleScreenshot($event)">
          @if(!$hasAiKey)
          <p class="mt-2 text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2">
            ⚠ بدون <code>ANTHROPIC_API_KEY</code> تصویر تحلیل نمی‌شود. از URL یا HTML استفاده کنید.
          </p>
          @endif
        </div>

        {{-- HTML Input --}}
        <div x-show="inputType==='html'" x-cloak>
          <label class="block text-sm font-medium text-gray-700 mb-2">فایل HTML</label>
          <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-violet-400 transition-colors cursor-pointer" @click="$refs.htmlInput.click()">
            <template x-if="!htmlFileName">
              <div>
                <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                <p class="text-sm text-gray-500">فایل HTML را اینجا رها کنید</p>
                <p class="text-xs text-gray-400 mt-1">فرمت: .html .htm .txt (حداکثر ۵ مگابایت)</p>
              </div>
            </template>
            <template x-if="htmlFileName">
              <div class="flex items-center justify-center gap-3">
                <svg class="w-8 h-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <div class="text-right">
                  <p class="text-sm font-medium text-gray-800" x-text="htmlFileName"></p>
                  <p class="text-xs text-gray-400">آماده برای تحلیل</p>
                </div>
              </div>
            </template>
          </div>
          <input type="file" accept=".html,.htm,.txt" x-ref="htmlInput" class="hidden"
            @change="htmlFileName = $event.target.files[0]?.name || ''">
        </div>

        {{-- Prompt Input --}}
        <div x-show="inputType==='prompt'" x-cloak>
          <label class="block text-sm font-medium text-gray-700 mb-2">توضیح سایت مورد نظر</label>
          <textarea x-model="prompt" rows="4" placeholder="مثال: یک فروشگاه آنلاین شبیه دیجی‌کالا با رنگ قرمز، گرید ۴ ستونه محصولات، هدر چسبنده و فیلتر پیشرفته..."
            class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors resize-none"></textarea>
          <p class="mt-1.5 text-xs text-gray-400">موتور از توضیح متنی شما ساختار مناسب را استنتاج می‌کند.</p>
        </div>

        {{-- Name + Notes (always visible) --}}
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">نام قالب (اختیاری)</label>
            <input type="text" x-model="themeName" placeholder="مثال: فروشگاه پیشرفته"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">یادداشت (اختیاری)</label>
            <input type="text" x-model="notes" placeholder="توضیحات بیشتر..."
              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          </div>
        </div>

        {{-- Error --}}
        <div x-show="errorMsg" x-cloak class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
          <p class="text-sm text-red-700" x-text="errorMsg"></p>
        </div>

        {{-- Progress Steps --}}
        <div x-show="loading" x-cloak class="bg-violet-50 border border-violet-100 rounded-xl p-4">
          <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-violet-600 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <p class="text-sm font-medium text-violet-700" x-text="currentStep"></p>
          </div>
          <div class="flex gap-1">
            <template x-for="(step, i) in phases" :key="i">
              <div class="flex-1 h-1.5 rounded-full transition-all duration-500"
                :class="phaseIndex > i ? 'bg-violet-600' : (phaseIndex === i ? 'bg-violet-400 animate-pulse' : 'bg-violet-100')"></div>
            </template>
          </div>
          <p class="text-xs text-violet-500 mt-1.5 text-center" x-text="'مرحله ' + (phaseIndex+1) + ' از ' + phases.length"></p>
        </div>

        {{-- Submit Button --}}
        <button type="submit" :disabled="loading"
          class="w-full py-3.5 rounded-xl bg-gradient-to-l from-violet-600 to-indigo-600 text-white font-semibold text-sm shadow-lg hover:shadow-violet-200 hover:from-violet-700 hover:to-indigo-700 transition-all disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
          <template x-if="!loading">
            <span class="flex items-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
              </svg>
              اجرای موتور مهندسی معکوس (۷ مرحله)
            </span>
          </template>
          <template x-if="loading">
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              در حال پردازش...
            </span>
          </template>
        </button>

      </form>
    </div>

    {{-- Recent Jobs --}}
    @if($recentJobs->count() > 0)
    <div class="mt-8">
      <h2 class="text-base font-semibold text-gray-800 mb-4">آخرین جلسات مهندسی معکوس</h2>
      <div class="grid gap-3">
        @foreach($recentJobs as $job)
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3 shadow-sm hover:border-violet-200 transition-colors">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 {{ $job->isComplete() ? 'bg-emerald-100 text-emerald-600' : ($job->isFailed() ? 'bg-red-100 text-red-500' : 'bg-amber-100 text-amber-600') }}">
            @if($job->isComplete())
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            @elseif($job->isFailed())
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            @else
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            @endif
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-800 truncate">{{ $job->suggested_name ?: $job->input_source }}</p>
            <p class="text-xs text-gray-400">{{ $job->inputTypeLabel() }} — {{ $job->created_at->diffForHumans() }}</p>
          </div>
          @if($job->isComplete())
          <div class="text-left shrink-0">
            <div class="text-xs text-gray-500 mb-0.5">شباهت</div>
            <div class="text-sm font-bold text-violet-600">{{ $job->similarity_scores['best_match_score'] ?? 0 }}%</div>
          </div>
          <a href="{{ route('admin.theme-builder.reverse-engineer.result', $job) }}"
            class="px-3 py-1.5 bg-violet-600 text-white text-xs rounded-lg hover:bg-violet-700 transition-colors">
            مشاهده
          </a>
          @endif
        </div>
        @endforeach
      </div>
    </div>
    @endif

  </div>
</div>

@push('scripts')
<script>
function reverseEngineerForm() {
  return {
    inputType: 'url',
    url: '',
    prompt: '',
    themeName: '',
    notes: '',
    screenshotFile: null,
    screenshotPreview: null,
    screenshotName: '',
    htmlFileName: '',
    loading: false,
    errorMsg: '',
    phaseIndex: 0,
    phases: ['استخراج DOM','بازسازی چیدمان','تحلیل بصری','تحلیل تجاری','شباهت‌سنجی','بازسازی قالب','اعتبارسنجی'],
    currentStep: '',

    handleScreenshot(event) {
      const file = event.target.files[0];
      if (!file) return;
      this.screenshotFile = file;
      this.screenshotName = file.name;
      const reader = new FileReader();
      reader.onload = (e) => { this.screenshotPreview = e.target.result; };
      reader.readAsDataURL(file);
    },

    startPhaseAnimation() {
      this.phaseIndex = 0;
      const interval = setInterval(() => {
        if (this.phaseIndex < this.phases.length - 1) {
          this.phaseIndex++;
          this.currentStep = 'مرحله ' + (this.phaseIndex + 1) + ': ' + this.phases[this.phaseIndex];
        } else {
          clearInterval(interval);
        }
      }, 1800);
    },

    async runAnalysis() {
      this.errorMsg = '';

      if (this.inputType === 'url' && !this.url) { this.errorMsg = 'آدرس URL وارد کنید.'; return; }
      if (this.inputType === 'screenshot' && !this.screenshotFile) { this.errorMsg = 'تصویر انتخاب کنید.'; return; }
      if (this.inputType === 'html' && !this.$refs.htmlInput.files[0]) { this.errorMsg = 'فایل HTML انتخاب کنید.'; return; }
      if (this.inputType === 'prompt' && !this.prompt.trim()) { this.errorMsg = 'توضیح متنی وارد کنید.'; return; }

      this.loading = true;
      this.currentStep = 'مرحله ۱: ' + this.phases[0];
      this.startPhaseAnimation();

      const fd = new FormData();
      fd.append('input_type', this.inputType);
      fd.append('name', this.themeName);
      fd.append('notes', this.notes);
      fd.append('_token', '{{ csrf_token() }}');

      if (this.inputType === 'url') fd.append('url', this.url);
      if (this.inputType === 'screenshot') fd.append('screenshot', this.screenshotFile);
      if (this.inputType === 'html') fd.append('html_file', this.$refs.htmlInput.files[0]);
      if (this.inputType === 'prompt') fd.append('prompt', this.prompt);

      try {
        const resp = await fetch('{{ route("theme-builder.reverse-engineer.analyze") }}', {
          method: 'POST',
          body: fd,
        });
        const data = await resp.json();

        if (data.success) {
          window.location.href = data.result_url;
        } else {
          this.errorMsg = data.error || 'خطایی رخ داد. دوباره تلاش کنید.';
          this.loading = false;
        }
      } catch (e) {
        this.errorMsg = 'خطای شبکه: ' + e.message;
        this.loading = false;
      }
    }
  };
}
</script>
@endpush
@endsection
