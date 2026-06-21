@extends('layouts.admin')
@section('title', 'موتور وارد کردن JSON قالب')

@section('content')
<div dir="rtl" class="max-w-4xl mx-auto space-y-6"
     x-data="{
       step: 'form',
       detecting: false,
       detectedSource: null,
       detectedLabel: '',
       analysis: null,
       suggestedName: '',
       errorMsg: '',
       jsonFile: null,
       jsonFileName: '',
       forceSource: '',

       sourceIcons: {
         elementor: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5z M4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z M16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z',
         bricks:    'M3 6h18M3 10h18M3 14h18M3 18h18',
         shopify:   'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
         webflow:   'M13 10V3L4 14h7v7l9-11h-7z',
         custom:    'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18',
       },

       sourceColors: {
         elementor: '#e74c3c',
         bricks:    '#f39c12',
         shopify:   '#96bf48',
         webflow:   '#4353ff',
         custom:    '#6366f1',
       },

       onFileChange(event) {
         const file = event.target.files[0];
         if (!file) return;
         this.jsonFile = file;
         this.jsonFileName = file.name;
         this.detectedSource = null;
         this.analysis = null;
         this.errorMsg = '';
         // Auto-detect on file select
         this.detectSource();
       },

       async detectSource() {
         if (!this.jsonFile) return;
         this.detecting = true;
         this.detectedSource = null;
         this.errorMsg = '';
         try {
           const fd = new FormData();
           fd.append('json_file', this.jsonFile);
           fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
           const res = await fetch('{{ route('admin.theme-builder.json-import.detect') }}', { method: 'POST', body: fd });
           const data = await res.json();
           if (!res.ok) { this.errorMsg = data.error || 'خطا در شناسایی فرمت'; return; }
           this.detectedSource = data.source;
           this.detectedLabel  = data.source_label;
           this.analysis       = data.analysis;
           if (data.suggested_name) this.suggestedName = data.suggested_name;
           this.step = 'detected';
         } catch(e) {
           this.errorMsg = 'خطا: ' + e.message;
         } finally {
           this.detecting = false;
         }
       },

       gradeColor(score) {
         if (score >= 80) return 'text-emerald-600';
         if (score >= 60) return 'text-amber-600';
         return 'text-red-500';
       }
     }">

{{-- ─── Page header ──────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center"
             style="background:linear-gradient(135deg,#6366f1,#06b6d4)">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
            </svg>
        </div>
        <div>
            <h1 class="text-xl font-bold text-gray-900">موتور وارد کردن JSON قالب</h1>
            <p class="text-sm text-gray-500">Elementor · Bricks · Shopify · Webflow · Custom</p>
        </div>
    </div>
    <a href="{{ route('admin.theme-builder.index') }}"
       class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        بازگشت به Theme Builder
    </a>
</div>

{{-- ─── Supported sources strip ─────────────────────────────────────────── --}}
<div class="grid grid-cols-5 gap-3">
    @foreach(['elementor' => ['Elementor', '#e74c3c'], 'bricks' => ['Bricks Builder', '#f39c12'], 'shopify' => ['Shopify', '#96bf48'], 'webflow' => ['Webflow', '#4353ff'], 'custom' => ['Custom / ThemePack v1', '#6366f1']] as $key => [$label, $color])
    <div class="rounded-xl p-3 text-center border transition-all hover:shadow-sm cursor-default"
         style="border-color:{{ $color }}22;background:{{ $color }}08">
        <div class="text-xs font-semibold" style="color:{{ $color }}">{{ $label }}</div>
    </div>
    @endforeach
</div>

{{-- ─── Upload card ──────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

    {{-- Detection banner --}}
    <div x-show="detecting" class="bg-indigo-50 border-b border-indigo-100 px-6 py-3 flex items-center gap-2 text-sm text-indigo-700">
        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        در حال شناسایی فرمت JSON...
    </div>

    {{-- Detected source badge --}}
    <div x-show="detectedSource && !detecting" x-cloak
         class="border-b px-6 py-3 flex items-center gap-3">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
            <span class="text-sm font-semibold text-gray-700">فرمت شناسایی شد:</span>
            <span x-text="detectedLabel" class="text-sm font-bold text-indigo-700"></span>
        </div>
        <div x-show="analysis" class="flex items-center gap-3 text-xs text-gray-500 mr-auto">
            <span x-show="analysis?.section_count > 0">
                <span x-text="analysis?.section_count"></span> بخش
            </span>
            <span x-show="analysis?.widget_count > 0">
                <span x-text="analysis?.widget_count"></span> ویجت
            </span>
            <span x-show="analysis?.page_count > 0">
                <span x-text="analysis?.page_count"></span> صفحه
            </span>
        </div>
    </div>

    <form action="{{ route('admin.theme-builder.json-import.import') }}" method="POST"
          enctype="multipart/form-data" class="p-6 space-y-5">
        @csrf

        {{-- Error --}}
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
            @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
        </div>
        @endif
        <div x-show="errorMsg" x-cloak class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700" x-text="errorMsg"></div>

        {{-- File upload --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                فایل JSON قالب
                <span class="text-red-500">*</span>
            </label>
            <div class="relative border-2 border-dashed rounded-xl p-6 text-center transition-all"
                 :class="jsonFile ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 hover:border-indigo-400'">
                <input type="file" name="json_file" accept=".json,.txt"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                       @change="onFileChange($event)" required>
                <div x-show="!jsonFile">
                    <svg class="w-10 h-10 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p class="text-sm text-gray-500">فایل JSON را بکشید و رها کنید یا کلیک کنید</p>
                    <p class="text-xs text-gray-400 mt-1">پشتیبانی: Elementor · Bricks · Shopify · Webflow · Custom (حداکثر ۲۰ مگابایت)</p>
                </div>
                <div x-show="jsonFile" x-cloak class="flex items-center justify-center gap-3">
                    <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <div class="text-right">
                        <div class="text-sm font-semibold text-indigo-700" x-text="jsonFileName"></div>
                        <div class="text-xs text-gray-500">فایل انتخاب شد — برای تغییر کلیک کنید</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Name + Category row --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">نام قالب <span class="text-red-500">*</span></label>
                <input type="text" name="name" required
                       :value="suggestedName"
                       x-model="suggestedName"
                       placeholder="مثال: فروشگاه پیشرفته"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">دسته‌بندی</label>
                <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                    @foreach($allCategories as $key => $label)
                    <option value="{{ $key }}" {{ $key === 'corporate' ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">توضیحات (اختیاری)</label>
            <textarea name="description" rows="2" placeholder="توضیح کوتاه درباره این قالب..."
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
        </div>

        {{-- Advanced options --}}
        <details class="border border-gray-200 rounded-xl">
            <summary class="px-4 py-3 text-sm font-semibold text-gray-600 cursor-pointer select-none">
                تنظیمات پیشرفته
            </summary>
            <div class="px-4 pb-4 pt-2 space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">اجبار فرمت (اگر شناسایی خودکار دقیق نیست)</label>
                    <select name="force_source" x-model="forceSource"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">شناسایی خودکار (توصیه‌شده)</option>
                        @foreach($supportedSources as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="publish_to_marketplace" value="1" id="pub_mkt"
                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded">
                    <label for="pub_mkt" class="text-sm text-gray-700">
                        انتشار مستقیم در Marketplace (پیش‌نویس)
                    </label>
                </div>
            </div>
        </details>

        {{-- Submit --}}
        <div class="flex items-center gap-3 pt-1">
            <button type="submit"
                    class="flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all"
                    style="background:linear-gradient(135deg,#6366f1,#06b6d4);box-shadow:0 2px 8px rgba(99,102,241,.35)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                شروع وارد کردن
            </button>
            <a href="{{ route('admin.theme-builder.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2.5 rounded-xl border border-gray-200 hover:border-gray-300 transition-all">
                انصراف
            </a>
        </div>
    </form>
</div>

{{-- ─── Recent imports ───────────────────────────────────────────────────── --}}
@if($recentImports->isNotEmpty())
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        آخرین واردسازی‌ها
    </h3>
    <div class="space-y-2">
        @foreach($recentImports as $imp)
        <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition-all">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-800">{{ $imp->name }}</div>
                    <div class="text-xs text-gray-400">{{ $imp->created_at->diffForHumans() }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.theme-builder.json-import.result', $imp->id) }}"
                   class="text-xs text-indigo-600 hover:underline">مشاهده نتیجه</a>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ─── How it works ─────────────────────────────────────────────────────── --}}
<div class="bg-gradient-to-r from-indigo-50 to-cyan-50 rounded-2xl border border-indigo-100 p-5">
    <h3 class="text-sm font-bold text-gray-700 mb-3">چگونه کار می‌کند؟</h3>
    <div class="grid grid-cols-4 gap-4 text-xs text-gray-600">
        @foreach([['شناسایی فرمت', 'تشخیص خودکار Elementor، Bricks، Shopify، Webflow یا Custom', '#6366f1'], ['تحلیل ساختار', 'استخراج صفحات، بخش‌ها، ویجت‌ها و استایل‌های کلی', '#06b6d4'], ['نگاشت ویجت‌ها', 'تبدیل هر نوع ویجت منبع به بلوک‌های Theme Builder', '#8b5cf6'], ['تولید ThemePack', 'ساخت پک قابل نصب در Canvas Editor با کیفیت‌سنجی', '#10b981']] as [$title, $desc, $color])
        <div class="flex flex-col gap-1">
            <div class="w-6 h-6 rounded-lg flex items-center justify-center mb-1" style="background:{{ $color }}">
                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            </div>
            <div class="font-semibold text-gray-800">{{ $title }}</div>
            <div class="text-gray-500 leading-relaxed">{{ $desc }}</div>
        </div>
        @endforeach
    </div>
</div>

</div>
@endsection
