@extends('layouts.admin')
@section('title', 'وارد کردن قالب از ZIP')

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
  <div class="max-w-4xl mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="mb-8">
      <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center shadow">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8M10 12v4m4-4v4"/>
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900" style="font-family: Vazirmatn, sans-serif;">وارد کردن قالب از ZIP</h1>
          <p class="text-sm text-gray-500">TemplateGoat · ThemeForest · Envato · Elementor Library · Bricks Library</p>
        </div>
      </div>
      <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('admin.theme-builder.index') }}" class="hover:text-indigo-600">ویرایشگر قالب</a>
        <span>›</span>
        <span class="text-gray-900">ورود از ZIP</span>
      </div>
    </div>

    {{-- Errors --}}
    @if ($errors->any())
      <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex gap-2">
          <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <ul class="text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
          </ul>
        </div>
      </div>
    @endif

    {{-- Supported Sources info strip --}}
    <div class="mb-6 bg-indigo-50 border border-indigo-100 rounded-xl p-4">
      <p class="text-sm font-semibold text-indigo-800 mb-3">منابع پشتیبانی‌شده:</p>
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach([
          ['icon'=>'🏪','label'=>'TemplateGoat','desc'=>'قالب‌های Elementor'],
          ['icon'=>'🌿','label'=>'ThemeForest / Envato','desc'=>'WordPress + Page Builders'],
          ['icon'=>'⚡','label'=>'Elementor Library','desc'=>'JSON اکسپورت Elementor'],
          ['icon'=>'🧱','label'=>'Bricks Builder','desc'=>'JSON اکسپورت Bricks'],
          ['icon'=>'🛒','label'=>'Shopify','desc'=>'sections.json / theme export'],
          ['icon'=>'🌊','label'=>'Webflow','desc'=>'CMS Export ZIP'],
        ] as $src)
          <div class="flex items-start gap-2 bg-white rounded-lg p-3 border border-indigo-100">
            <span class="text-lg">{{ $src['icon'] }}</span>
            <div>
              <p class="text-xs font-bold text-gray-800">{{ $src['label'] }}</p>
              <p class="text-xs text-gray-500">{{ $src['desc'] }}</p>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Upload Form --}}
    <div x-data="zipImport()" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

      {{-- Step indicator --}}
      <div class="flex border-b border-gray-100">
        <div class="flex-1 py-3 px-4 flex items-center gap-2 text-sm"
             :class="step >= 1 ? 'text-indigo-700 font-semibold' : 'text-gray-400'">
          <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                :class="step >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500'">۱</span>
          آپلود فایل
        </div>
        <div class="flex-1 py-3 px-4 flex items-center gap-2 text-sm border-r border-gray-100"
             :class="step >= 2 ? 'text-indigo-700 font-semibold' : 'text-gray-400'">
          <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                :class="step >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500'">۲</span>
          شناسایی منبع
        </div>
        <div class="flex-1 py-3 px-4 flex items-center gap-2 text-sm border-r border-gray-100"
             :class="step >= 3 ? 'text-indigo-700 font-semibold' : 'text-gray-400'">
          <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                :class="step >= 3 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500'">۳</span>
          پردازش و تبدیل
        </div>
      </div>

      <div class="p-6">
        <form action="{{ route('theme-builder.zip-import.import') }}" method="POST" enctype="multipart/form-data" id="zipImportForm">
          @csrf

          {{-- Drop Zone --}}
          <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">فایل ZIP قالب</label>
            <div class="relative border-2 border-dashed rounded-xl transition-colors"
                 :class="dragOver ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-indigo-400'"
                 x-on:dragover.prevent="dragOver=true"
                 x-on:dragleave.prevent="dragOver=false"
                 x-on:drop.prevent="handleDrop($event)">

              <input type="file" name="zip_file" id="zip_file" accept=".zip" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" x-ref="fileInput"
                     x-on:change="handleFile($event.target.files[0])">

              <div class="p-8 text-center" x-show="!selectedFile">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-gray-600 font-medium">فایل ZIP را اینجا بکشید یا کلیک کنید</p>
                <p class="text-sm text-gray-400 mt-1">حداکثر ۱۰۰ مگابایت · فقط فرمت ZIP</p>
              </div>

              <div class="p-6" x-show="selectedFile" x-cloak>
                <div class="flex items-center gap-4">
                  <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8"/>
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate" x-text="selectedFile?.name"></p>
                    <p class="text-xs text-gray-500" x-text="formatSize(selectedFile?.size)"></p>
                  </div>
                  <button type="button" class="text-gray-400 hover:text-red-500"
                          x-on:click.stop="clearFile()">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          {{-- Detection Banner --}}
          <div x-show="detection" x-cloak class="mb-5 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm font-bold text-indigo-800">منبع شناسایی شد</span>
              </div>
              <div class="flex gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-600 text-white" x-text="detection?.origin_label"></span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800" x-text="detection?.source_label"></span>
              </div>
            </div>
            <div class="grid grid-cols-4 gap-3 text-center text-xs">
              <div class="bg-white rounded-lg p-2">
                <p class="text-xl font-bold text-indigo-700" x-text="detection?.json_count || 0"></p>
                <p class="text-gray-500">فایل JSON</p>
              </div>
              <div class="bg-white rounded-lg p-2">
                <p class="text-xl font-bold text-purple-700" x-text="detection?.screenshot_count || 0"></p>
                <p class="text-gray-500">اسکرین‌شات</p>
              </div>
              <div class="bg-white rounded-lg p-2">
                <p class="text-xl font-bold text-blue-700" x-text="detection?.asset_count || 0"></p>
                <p class="text-gray-500">فایل asset</p>
              </div>
              <div class="bg-white rounded-lg p-2">
                <p class="text-xl font-bold text-gray-700" x-text="detection?.total_files || 0"></p>
                <p class="text-gray-500">کل فایل‌ها</p>
              </div>
            </div>
          </div>

          {{-- Detecting spinner --}}
          <div x-show="detecting" x-cloak class="mb-5 flex items-center gap-2 text-sm text-indigo-600">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            در حال آنالیز فایل ZIP...
          </div>

          {{-- Theme Options --}}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">نام قالب</label>
              <input type="text" name="name" placeholder="نام قالب وارد کنید"
                     x-bind:value="detection?.suggested_name || ''"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                     style="font-family: Vazirmatn, sans-serif;">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی</label>
              <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                      style="font-family: Vazirmatn, sans-serif;">
                @foreach ($allCategories as $key => $label)
                  <option value="{{ $key }}" {{ $key === 'corporate' ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات <span class="text-gray-400">(اختیاری)</span></label>
            <textarea name="description" rows="2"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                      style="font-family: Vazirmatn, sans-serif;" placeholder="توضیحات قالب..."></textarea>
          </div>

          {{-- Advanced Options --}}
          <details class="mb-5 border border-gray-200 rounded-xl overflow-hidden">
            <summary class="px-4 py-3 bg-gray-50 text-sm font-medium text-gray-600 cursor-pointer select-none">
              تنظیمات پیشرفته
            </summary>
            <div class="p-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">فرمت را دستی مشخص کنید <span class="text-gray-400">(اختیاری — برای override شناسایی خودکار)</span></label>
                <select name="force_source" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                  <option value="">— شناسایی خودکار —</option>
                  <option value="elementor">Elementor</option>
                  <option value="bricks">Bricks Builder</option>
                  <option value="shopify">Shopify</option>
                  <option value="webflow">Webflow</option>
                  <option value="custom">Custom / ThemePack v1</option>
                </select>
              </div>
              <div class="flex items-center gap-3">
                <input type="checkbox" name="publish_to_marketplace" id="publish_mp" value="1"
                       class="w-4 h-4 text-indigo-600 rounded border-gray-300">
                <label for="publish_mp" class="text-sm text-gray-700">انتشار خودکار در Marketplace پس از ورود</label>
              </div>
            </div>
          </details>

          {{-- Submit --}}
          <button type="submit" id="submitBtn"
                  x-bind:disabled="!selectedFile || importing"
                  class="w-full py-3 px-6 rounded-xl font-semibold text-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-2"
                  style="font-family: Vazirmatn, sans-serif;"
                  x-on:click="importing=true">
            <template x-if="!importing">
              <span class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3 3-3m-3 3V10"/>
                </svg>
                وارد کردن و پردازش قالب
              </span>
            </template>
            <template x-if="importing">
              <span class="flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                در حال پردازش ZIP...
              </span>
            </template>
          </button>
        </form>
      </div>
    </div>

    {{-- Recent imports --}}
    @if ($recentImports->isNotEmpty())
    <div class="mt-8 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-base font-bold text-gray-900" style="font-family: Vazirmatn, sans-serif;">واردهای اخیر</h2>
      </div>
      <ul class="divide-y divide-gray-50">
        @foreach ($recentImports as $pack)
          <li class="flex items-center justify-between px-6 py-3">
            <div>
              <p class="text-sm font-medium text-gray-900">{{ $pack->name }}</p>
              <p class="text-xs text-gray-400">{{ $pack->created_at?->diffForHumans() }}</p>
            </div>
            <div class="flex gap-2">
              <a href="{{ route('theme-builder.zip-import.result', $pack->id) }}"
                 class="text-xs px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 font-medium">نتیجه</a>
              <a href="{{ route('admin.theme-builder.canvas', ['theme' => 0, 'pack' => $pack->id]) }}"
                 class="text-xs px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">Canvas</a>
            </div>
          </li>
        @endforeach
      </ul>
    </div>
    @endif

  </div>
</div>
@endsection

@push('scripts')
<script>
function zipImport() {
  return {
    step: 1,
    selectedFile: null,
    dragOver: false,
    detecting: false,
    detection: null,
    importing: false,

    handleFile(file) {
      if (!file || !file.name.endsWith('.zip')) {
        alert('لطفاً یک فایل ZIP انتخاب کنید.');
        return;
      }
      this.selectedFile = file;
      this.step = 2;
      this.runDetect(file);
    },

    handleDrop(e) {
      this.dragOver = false;
      const file = e.dataTransfer.files[0];
      if (file) this.handleFile(file);
    },

    clearFile() {
      this.selectedFile = null;
      this.detection = null;
      this.step = 1;
      this.$refs.fileInput.value = '';
    },

    formatSize(bytes) {
      if (!bytes) return '';
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
      return (bytes/1024/1024).toFixed(1) + ' MB';
    },

    async runDetect(file) {
      this.detecting = true;
      this.detection = null;

      const form = new FormData();
      form.append('zip_file', file);
      form.append('_token', document.querySelector('meta[name="csrf-token"]').content);

      try {
        const res = await fetch('{{ route("theme-builder.zip-import.detect") }}', {
          method: 'POST',
          body: form,
        });
        const data = await res.json();
        if (data.error) {
          console.warn('ZIP detect error:', data.error);
        } else {
          this.detection = data;
          this.step = 3;
          // Auto-fill name if suggested
          if (data.suggested_name) {
            const nameInput = document.querySelector('input[name="name"]');
            if (nameInput && !nameInput.value) nameInput.value = data.suggested_name;
          }
        }
      } catch (err) {
        console.warn('Detect failed:', err);
      } finally {
        this.detecting = false;
      }
    },
  };
}
</script>
@endpush
