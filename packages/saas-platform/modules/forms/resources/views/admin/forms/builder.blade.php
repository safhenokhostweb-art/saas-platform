@extends(config('forms.layouts.admin'))

@php
$paletteCategories = config('forms.field_type_categories', []);
$_iconSvgs = [
    'text'           => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>',
    'textarea'       => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z"/></svg>',
    'email'          => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>',
    'number'         => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5"/></svg>',
    'phone'          => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z"/></svg>',
    'date'           => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>',
    'radio'          => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>',
    'checkbox'       => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>',
    'select'         => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/></svg>',
    'rating'         => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z"/></svg>',
    'section_header' => '<svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12H12"/></svg>',
];
$paletteIconMap = [];
foreach (array_keys(config('forms.field_types', [])) as $_t) {
    $paletteIconMap[$_t] = $_iconSvgs[$_t] ?? $_iconSvgs['text'];
}
@endphp

@section('header', '')

{{--
  Phase 10.2 Frontend Governance:
  - Static toolbar controls: migrated to x-core-ui::button / x-core-ui::input
  - Alpine <template x-for> template controls: ARCHITECTURAL EXCEPTION — these controls
    are rendered by the JavaScript runtime (Alpine.js), not Blade. x-core-ui components
    are Blade-server-rendered and cannot be used inside client-side Alpine templates.
    This is the only accepted exception to the raw-control rule.
--}}

@section('content')
{{-- Inject form data into a JS global to avoid HTML-attribute double-quote encoding issues --}}
<script>window.__formBuilderInitialData = @json($formData);</script>
<div id="forms-builder" class="flex flex-col bg-slate-50" x-data="formBuilder(window.__formBuilderInitialData)" x-cloak>

  {{-- ═══════════════════ DRAFT RECOVERY BANNER (Phase 10.2.6) ════════════════════════════════════════════════════ --}}
  <div x-show="hasDraft"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 -translate-y-2"
       x-transition:enter-end="opacity-100 translate-y-0"
       class="bg-amber-50 border-b border-amber-200 px-4 py-2.5 flex items-center gap-3 shrink-0 z-20"
       role="alert" aria-live="polite"
       style="display:none">
    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
    <span class="text-sm text-amber-800 flex-1">
      پیش‌نویس ذخیره نشده از <span x-text="draftTimestamp" class="font-medium"></span> یافت شد.
    </span>
    <button @click="restoreDraft()"
            class="text-xs font-medium text-amber-700 border border-amber-300 bg-amber-100 hover:bg-amber-200 rounded-lg px-3 py-1 transition focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:outline-none">
      بازیابی پیش‌نویس
    </button>
    <button @click="discardDraft()"
            class="text-xs text-amber-600 hover:text-amber-800 px-2 py-1 transition focus-visible:outline-none"
            aria-label="نادیده گرفتن پیش‌نویس">
      نادیده بگیر
    </button>
  </div>

  {{-- ═══════════════════════════════════════════════ TOP TOOLBAR ═════════════════════════════════════════════════ --}}
  <div class="flex items-center gap-3 px-4 h-14 bg-white border-b border-slate-200 shadow-sm shrink-0 z-10">

    {{-- Back --}}
    <a href="{{ route('admin.forms.index') }}" class="flex items-center gap-1.5 text-slate-500 hover:text-slate-800 text-sm transition mr-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
      فرم‌ها
    </a>

    <div class="w-px h-5 bg-slate-200 mx-1"></div>

    {{-- Form Name (inline editable) — x-core-ui::input; Alpine x-model passes through $attributes --}}
    <div class="flex items-center gap-2 min-w-0">
      <x-core-ui::input
        name="form-title"
        placeholder="نام فرم..."
        aria-label="نام فرم"
        x-model="form.title"
        @change="autosave"
        class="font-semibold text-slate-800 text-sm min-w-[160px] max-w-[320px] !border-transparent !bg-transparent !shadow-none focus-visible:!border-[var(--ui-indigo)] focus-visible:!bg-white"
      />
    </div>

    {{--
      Status Toggle — x-bind:class used (NOT :class) because the value is an Alpine expression.
      Blade's :class is PHP-evaluated; form.is_active is an Alpine variable → would crash PHP 8.
      x-bind:class passes through $attributes untouched; Alpine handles it reactively.
    --}}
    <x-core-ui::button
      variant="ghost"
      size="sm"
      @click="toggleFormStatus"
      x-bind:class="form.is_active ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
      class="!rounded-full !px-3 !py-1 !text-xs"
    >
      <span class="w-1.5 h-1.5 rounded-full" :class="form.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
      <span x-text="form.is_active ? 'فعال' : 'غیرفعال'"></span>
    </x-core-ui::button>

    {{-- Autosave indicator --}}
    <div class="flex items-center gap-1.5 text-xs transition" :class="{
      'text-slate-400': saveStatus === 'saved',
      'text-indigo-500': saveStatus === 'saving',
      'text-amber-500': saveStatus === 'unsaved'
    }">
      <template x-if="saveStatus === 'saving'">
        <svg class="w-3 h-3 autosave-pulse" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
      </template>
      <template x-if="saveStatus === 'saved'">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
      </template>
      <template x-if="saveStatus === 'unsaved'">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
      </template>
      <span x-text="{saved: 'ذخیره شد', saving: 'در حال ذخیره...', unsaved: 'تغییرات ذخیره نشده'}[saveStatus]"></span>
    </div>

    <div class="flex-1"></div>

    {{-- Field count --}}
    <span class="text-xs text-slate-400" x-text="`${fields.length} فیلد`"></span>

    {{-- Undo/Redo --}}
    <div class="flex items-center gap-0.5">
      {{--
        Undo/Redo: x-bind:disabled + x-bind:class used (NOT :disabled/:class).
        Blade's :disabled is a PHP-evaluated prop on x-core-ui::button (declared in @props).
        historyIndex/history are Alpine variables — evaluating them as PHP throws
        "Undefined constant historyIndex" and crashes the builder on open.
        x-bind:* passes through $attributes to the rendered <button>; Alpine handles reactivity.
      --}}
      <x-core-ui::button variant="ghost" size="sm" :icon="true" @click="undo"
        x-bind:disabled="historyIndex <= 0"
        x-bind:class="{'opacity-50 cursor-not-allowed pointer-events-none': historyIndex <= 0}"
        aria-label="برگشت (Ctrl+Z)" title="برگشت (Ctrl+Z)">
        <svg class="w-4 h-4 text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
      </x-core-ui::button>
      <x-core-ui::button variant="ghost" size="sm" :icon="true" @click="redo"
        x-bind:disabled="historyIndex >= history.length - 1"
        x-bind:class="{'opacity-50 cursor-not-allowed pointer-events-none': historyIndex >= history.length - 1}"
        aria-label="انجام دوباره (Ctrl+Y)" title="انجام دوباره (Ctrl+Y)">
        <svg class="w-4 h-4 text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3"/></svg>
      </x-core-ui::button>
    </div>

    <div class="w-px h-5 bg-slate-200"></div>

    {{-- Keyboard shortcuts hint --}}
    <x-core-ui::button variant="ghost" size="sm" :icon="true" @click="showKeyboardHelp = !showKeyboardHelp" aria-label="کلیدهای میانبر" title="کلیدهای میانبر (?)">
      <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
    </x-core-ui::button>

    <div class="w-px h-5 bg-slate-200"></div>

    {{-- Responsive Preview Modes (Phase 10.2.6) --}}
    <div class="flex items-center gap-0.5 bg-slate-100 rounded-lg p-0.5" role="group" aria-label="حالت پیش‌نمایش">
      <button @click="setPreviewMode('desktop')"
              :class="previewMode === 'desktop' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-400 hover:text-slate-600'"
              class="p-1.5 rounded-md transition" aria-label="دسکتاپ" title="پیش‌نمایش دسکتاپ">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25A2.25 2.25 0 0 1 5.25 3h13.5A2.25 2.25 0 0 1 21 5.25z"/></svg>
      </button>
      <button @click="setPreviewMode('tablet')"
              :class="previewMode === 'tablet' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-400 hover:text-slate-600'"
              class="p-1.5 rounded-md transition" aria-label="تبلت" title="پیش‌نمایش تبلت">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
      </button>
      <button @click="setPreviewMode('mobile')"
              :class="previewMode === 'mobile' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-400 hover:text-slate-600'"
              class="p-1.5 rounded-md transition" aria-label="موبایل" title="پیش‌نمایش موبایل">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18h3"/></svg>
      </button>
    </div>

    <div class="w-px h-5 bg-slate-200"></div>

    {{-- Preview --}}
    <a href="{{ route('admin.forms.preview', $form) }}" target="_blank"
       class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg>
      پیش‌نمایش
    </a>

    {{-- Analytics --}}
    <a href="{{ route('admin.forms.analytics', $form) }}"
       class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/></svg>
      آمار
    </a>

    {{-- Submissions --}}
    <a href="{{ route('admin.forms.submissions.index', ['form_id' => $form->id]) }}"
       class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75z"/></svg>
      ارسال‌ها
    </a>

    {{-- Save Button --}}
    <x-core-ui::button variant="primary" size="sm" @click="saveAll">
      <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
      ذخیره
    </x-core-ui::button>
  </div>

  {{-- ═══════════════════════════════════════════ THREE-PANEL LAYOUT ══════════════════════════════════════════════ --}}
  <div class="flex flex-1 overflow-hidden">

    {{-- ────────────────── LEFT PANEL: FIELD PALETTE ────────────────── --}}
    <div class="builder-palette bg-slate-800 flex flex-col border-r border-slate-700 overflow-hidden">

      {{-- Palette header + search --}}
      <div class="p-3 border-b border-slate-700 shrink-0">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2 px-1">فیلدها</p>
        <div class="relative">
          <svg class="w-4 h-4 text-slate-500 absolute right-2.5 top-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z"/></svg>
          <input type="text" id="palette-search-input" x-model="paletteSearch" placeholder="جستجوی فیلد..."
                 class="w-full text-sm focus:outline-none">
        </div>
      </div>

      {{-- Field categories (rendered client-side via Alpine x-for to avoid Blade/HTML-attribute encoding issues) --}}
      <div class="flex-1 overflow-y-auto p-3 space-y-4" id="palette-list">
        <template x-for="cat in _paletteCategories" :key="cat.label">
          <div x-show="!paletteSearch || cat.types.some(t => fieldTypeVisible(t, paletteSearch))">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2 px-1" x-text="cat.label"></p>
            <div class="space-y-1">
              <template x-for="type in cat.types" :key="type">
                <div class="palette-item flex items-center gap-2.5 px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 border border-slate-600 hover:border-slate-500 transition group"
                     draggable="true"
                     :data-field-type="type"
                     @dragstart="onPaletteDragStart($event, type)"
                     @click="quickAddField(type)"
                     x-show="fieldTypeVisible(type, paletteSearch)">
                  <span class="text-slate-300 leading-none" x-html="_paletteIcons[type] || ''"></span>
                  <span class="text-sm text-slate-200 group-hover:text-white transition" x-text="getFieldTypeLabel(type)"></span>
                  <svg class="w-3.5 h-3.5 text-slate-500 mr-auto opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </div>
              </template>
            </div>
          </div>
        </template>
      </div>

      {{-- Template quick-apply --}}
      <div class="p-3 border-t border-slate-700 shrink-0">
        <x-core-ui::button variant="primary" size="sm" class="w-full justify-center" @click="showTemplateModal = true">
          <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25z"/></svg>
          از قالب شروع کنید
        </x-core-ui::button>
      </div>
    </div>

    {{-- ────────────────── CENTER: FORM CANVAS ────────────────── --}}
    <div class="builder-canvas bg-slate-100 overflow-y-auto canvas-grid"
         @dragover.prevent="onCanvasDragOver($event)"
         @dragleave="onCanvasDragLeave"
         @drop.prevent="onCanvasDrop($event)">

      {{-- Responsive preview width constraint (Phase 10.2.6) --}}
      <div class="mx-auto py-8 px-6 transition-all duration-300"
           :class="{
             'max-w-2xl': previewMode === 'desktop',
             'max-w-xl':  previewMode === 'tablet',
             'max-w-sm':  previewMode === 'mobile',
           }"
           :aria-label="'نمایش ' + {desktop: 'دسکتاپ', tablet: 'تبلت', mobile: 'موبایل'}[previewMode]">

        {{-- Form header on canvas --}}
        <div class="mb-6 text-center">
          <p class="text-xs text-slate-400 mb-1">عنوان فرم (در سایت نمایش داده می‌شود)</p>
          <h2 class="text-xl font-bold text-slate-700" x-text="form.title || 'نام فرم...'"></h2>
        </div>

        {{-- Fields canvas --}}
        <div id="fields-canvas" class="space-y-3">
          <template x-for="field in fields" :key="field.id">
            <div class="field-card bg-white rounded-xl border border-slate-200 p-4 cursor-pointer"
                 :class="{'selected': selectedFieldId === field.id, 'opacity-50': !field.is_active}"
                 :data-field-id="field.id"
                 @click="selectField(field.id)">

              {{-- Field card header --}}
              <div class="flex items-start gap-3">
                <span class="cursor-grab mt-0.5 text-slate-300 hover:text-slate-400 transition drag-handle" aria-hidden="true">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8.5 6a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm0 5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm0 5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm7-10a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm0 5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm0 5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>
                </span>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-medium text-slate-800 text-sm" x-text="field.label || '(بدون عنوان)'"></span>
                    <span x-show="field.is_required" class="text-red-500 text-xs font-bold">*الزامی</span>
                    <span x-show="field.conditions && field.conditions.rules && field.conditions.rules.length > 0"
                          class="text-xs px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded-full border border-purple-200">شرطی</span>
                  </div>

                  {{-- Field preview --}}
                  <div class="mt-1">
                    <template x-if="field.type === 'section_header'">
                      <div class="flex items-center gap-3">
                        <div class="flex-1 h-px bg-slate-200"></div>
                        <span class="text-xs text-slate-400 font-medium" x-text="field.label"></span>
                        <div class="flex-1 h-px bg-slate-200"></div>
                      </div>
                    </template>
                    <template x-if="field.type !== 'section_header'">
                      <div class="bg-slate-50 rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-400 pointer-events-none"
                           x-text="field.placeholder || getFieldTypeLabel(field.type)"></div>
                    </template>
                  </div>

                  <p x-show="field.help_text" class="text-xs text-slate-400 mt-1" x-text="field.help_text"></p>
                </div>

                {{-- Field type badge + actions --}}
                <div class="flex items-center gap-1.5 shrink-0">
                  <span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full border border-indigo-100"
                        x-text="getFieldTypeLabel(field.type)"></span>

                  <button @click.stop="duplicateField(field.id)"
                          class="p-1 rounded hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition" title="کپی">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
                  </button>

                  <button @click.stop="requestDeleteField(field.id)"
                          class="p-1 rounded hover:bg-red-50 text-slate-400 hover:text-red-500 transition" title="حذف" aria-label="حذف فیلد">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
                  </button>
                </div>
              </div>
            </div>
          </template>

          {{-- Drop zone --}}
          <div id="drop-zone"
               class="drop-hint p-6 text-center text-sm text-slate-400"
               :class="{'drag-over': isDraggingOver}"
               @dragover.prevent="isDraggingOver = true"
               @dragleave="isDraggingOver = false"
               @drop.prevent="onDropToCanvas($event)">
            <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <template x-if="fields.length === 0">
              <div>
                <p class="font-medium text-slate-500 mb-1">فیلدی وجود ندارد</p>
                <p>یک فیلد از پنل چپ بکشید یا کلیک کنید</p>
              </div>
            </template>
            <template x-if="fields.length > 0">
              <p>فیلد جدید اینجا رها کنید</p>
            </template>
          </div>
        </div>

        {{-- Submit button preview --}}
        <div class="mt-6 text-center">
          <div class="inline-flex items-center gap-2 bg-indigo-600 text-white px-6 py-2.5 rounded-xl text-sm font-medium cursor-default opacity-80">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12zm0 0h7.5"/></svg>
            <span x-text="form.settings && form.settings.submit_button_text ? form.settings.submit_button_text : 'ارسال فرم'"></span>
          </div>
          <p class="text-xs text-slate-400 mt-2">پیش‌نمایش دکمه ارسال</p>
        </div>
      </div>
    </div>

    {{-- ────────────────── RIGHT PANEL: INSPECTOR ────────────────── --}}
    <div class="builder-inspector bg-white border-r border-slate-200 flex flex-col overflow-hidden" dir="rtl">

      <template x-if="selectedField">
        <div class="flex flex-col h-full">
          {{-- Inspector header --}}
          <div class="px-4 py-3 border-b border-slate-100 shrink-0">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm font-semibold text-slate-700" x-text="selectedField.label || 'فیلد انتخابی'"></span>
              <button @click="selectedFieldId = null" class="p-1 rounded hover:bg-slate-100 text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <span class="text-xs text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full" x-text="getFieldTypeLabel(selectedField.type)"></span>
          </div>

          {{-- Inspector tabs --}}
          <div class="flex border-b border-slate-100 text-xs shrink-0 overflow-x-auto">
            @foreach([['general','عمومی'],['validation','اعتبارسنجی'],['appearance','ظاهر'],['logic','منطق'],['advanced','پیشرفته']] as [$tab, $label])
            <button @click="activeTab = '{{ $tab }}'"
                    :class="activeTab === '{{ $tab }}' ? 'inspector-tab active' : 'text-slate-500 hover:text-slate-700'"
                    class="px-3 py-2.5 whitespace-nowrap transition border-b-2 border-transparent">{{ $label }}</button>
            @endforeach
          </div>

          {{-- Inspector content --}}
          <div class="flex-1 overflow-y-auto p-4 space-y-4">

            {{-- GENERAL TAB --}}
            <div x-show="activeTab === 'general'" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">عنوان فیلد <span class="text-red-500">*</span></label>
                <input type="text" x-model="selectedField.label"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                  نام فنی (name)
                  <span class="text-slate-400 font-normal">— برای ارتباط با سیستم‌ها</span>
                </label>
                <input type="text" x-model="selectedField.name"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       dir="ltr" placeholder="field_name">
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">متن راهنما (Placeholder)</label>
                <input type="text" x-model="selectedField.placeholder"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">متن کمکی (Help Text)</label>
                <textarea x-model="selectedField.help_text"
                          @input="debouncedFieldSave" rows="2"
                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none resize-none"></textarea>
              </div>

              {{-- Options for select/radio/checkbox --}}
              <div x-show="['select','radio','checkbox'].includes(selectedField.type)">
                <label class="block text-xs font-medium text-slate-600 mb-1">گزینه‌ها <span class="text-slate-400">(هر خط یک گزینه)</span></label>
                <textarea :value="(selectedField.options || []).join('\n')"
                          @change="updateOptions($event)"
                          rows="5"
                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none resize-none"
                          placeholder="گزینه ۱&#10;گزینه ۲&#10;گزینه ۳"></textarea>
              </div>

              <label class="flex items-center gap-2 cursor-pointer">
                <div class="relative">
                  <input type="checkbox" x-model="selectedField.is_required" @change="debouncedFieldSave" class="sr-only">
                  <div class="w-9 h-5 rounded-full transition" :class="selectedField.is_required ? 'bg-indigo-600' : 'bg-slate-200'"></div>
                  <div class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow transition" :class="selectedField.is_required ? 'translate-x-[-16px]' : ''"></div>
                </div>
                <span class="text-sm text-slate-700">فیلد الزامی است</span>
              </label>

              <label class="flex items-center gap-2 cursor-pointer">
                <div class="relative">
                  <input type="checkbox" x-model="selectedField.is_active" @change="debouncedFieldSave" class="sr-only">
                  <div class="w-9 h-5 rounded-full transition" :class="selectedField.is_active ? 'bg-emerald-500' : 'bg-slate-200'"></div>
                  <div class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow transition" :class="selectedField.is_active ? 'translate-x-[-16px]' : ''"></div>
                </div>
                <span class="text-sm text-slate-700">فیلد فعال باشد</span>
              </label>
            </div>

            {{-- VALIDATION TAB --}}
            <div x-show="activeTab === 'validation'" class="space-y-4">
              <div x-show="['text','textarea','email','phone'].includes(selectedField.type)">
                <label class="block text-xs font-medium text-slate-600 mb-1">حداقل طول</label>
                <input type="number" min="0"
                       :value="selectedField.validation_rules && selectedField.validation_rules.min_length"
                       @input="updateValidationRule('min_length', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
              </div>
              <div x-show="['text','textarea','email','phone'].includes(selectedField.type)">
                <label class="block text-xs font-medium text-slate-600 mb-1">حداکثر طول</label>
                <input type="number" min="0"
                       :value="selectedField.validation_rules && selectedField.validation_rules.max_length"
                       @input="updateValidationRule('max_length', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
              </div>
              <div x-show="selectedField.type === 'number'">
                <label class="block text-xs font-medium text-slate-600 mb-1">حداقل مقدار</label>
                <input type="number"
                       :value="selectedField.validation_rules && selectedField.validation_rules.min"
                       @input="updateValidationRule('min', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
              </div>
              <div x-show="selectedField.type === 'number'">
                <label class="block text-xs font-medium text-slate-600 mb-1">حداکثر مقدار</label>
                <input type="number"
                       :value="selectedField.validation_rules && selectedField.validation_rules.max"
                       @input="updateValidationRule('max', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">پیام خطای سفارشی</label>
                <input type="text"
                       :value="selectedField.validation_rules && selectedField.validation_rules.custom_message"
                       @input="updateValidationRule('custom_message', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       placeholder="این فیلد اشتباه است...">
              </div>
            </div>

            {{-- APPEARANCE TAB --}}
            <div x-show="activeTab === 'appearance'" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-2">عرض فیلد</label>
                <div class="flex gap-2">
                  <button @click="selectedField.column_span = 4; debouncedFieldSave()"
                          :class="selectedField.column_span === 4 ? 'width-btn active' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="width-btn flex-1 px-2 py-2 rounded-lg border text-xs font-medium transition">
                    <div class="flex gap-0.5 justify-center mb-1">
                      <div class="w-2 h-3 bg-current rounded-sm"></div>
                    </div>
                    یک سوم
                  </button>
                  <button @click="selectedField.column_span = 6; debouncedFieldSave()"
                          :class="selectedField.column_span === 6 ? 'width-btn active' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="width-btn flex-1 px-2 py-2 rounded-lg border text-xs font-medium transition">
                    <div class="flex gap-0.5 justify-center mb-1">
                      <div class="w-3 h-3 bg-current rounded-sm"></div>
                    </div>
                    نیمه
                  </button>
                  <button @click="selectedField.column_span = 12; debouncedFieldSave()"
                          :class="selectedField.column_span === 12 ? 'width-btn active' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="width-btn flex-1 px-2 py-2 rounded-lg border text-xs font-medium transition">
                    <div class="flex gap-0.5 justify-center mb-1">
                      <div class="w-5 h-3 bg-current rounded-sm"></div>
                    </div>
                    کامل
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">کلاس CSS سفارشی</label>
                <input type="text" x-model="selectedField.css_class"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       dir="ltr" placeholder="my-custom-class">
              </div>
            </div>

            {{-- LOGIC TAB --}}
            <div x-show="activeTab === 'logic'" class="space-y-4">
              <div class="bg-purple-50 border border-purple-200 rounded-xl p-3 text-xs text-purple-700">
                <strong>منطق شرطی:</strong> این فیلد را بر اساس مقدار فیلدهای دیگر نمایش یا مخفی کنید.
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-2">نوع منطق</label>
                <div class="flex gap-2">
                  <button @click="ensureConditions(); selectedField.conditions.logic = 'AND'; debouncedFieldSave()"
                          :class="(!selectedField.conditions || selectedField.conditions.logic !== 'OR') ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-medium transition">AND (همه شرط‌ها)</button>
                  <button @click="ensureConditions(); selectedField.conditions.logic = 'OR'; debouncedFieldSave()"
                          :class="selectedField.conditions && selectedField.conditions.logic === 'OR' ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-medium transition">OR (هر شرطی)</button>
                </div>
              </div>

              {{-- Condition rules --}}
              <div x-show="selectedField.conditions && selectedField.conditions.rules && selectedField.conditions.rules.length > 0"
                   class="space-y-2">
                <template x-for="(rule, index) in (selectedField.conditions && selectedField.conditions.rules || [])" :key="index">
                  <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-2">
                    <select x-model="rule.field_id" @change="debouncedFieldSave"
                            class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500">
                      <option value="">انتخاب فیلد...</option>
                      <template x-for="f in fields.filter(f => f.id !== selectedField.id)" :key="f.id">
                        <option :value="f.id" x-text="f.label"></option>
                      </template>
                    </select>
                    <select x-model="rule.operator" @change="debouncedFieldSave"
                            class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500">
                      <option value="equals">برابر است با</option>
                      <option value="not_equals">برابر نیست با</option>
                      <option value="contains">شامل می‌شود</option>
                      <option value="is_empty">خالی است</option>
                      <option value="is_not_empty">خالی نیست</option>
                    </select>
                    <div class="flex gap-2">
                      <input x-show="!['is_empty','is_not_empty'].includes(rule.operator)"
                             type="text" x-model="rule.value" @input="debouncedFieldSave"
                             class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500"
                             placeholder="مقدار...">
                      <button @click="removeConditionRule(index)"
                              class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-500 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                      </button>
                    </div>
                  </div>
                </template>
              </div>

              <button @click="addConditionRule"
                      class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-dashed border-slate-300 text-slate-500 text-xs hover:border-indigo-300 hover:text-indigo-600 hover:bg-indigo-50 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                افزودن شرط
              </button>
            </div>

            {{-- ADVANCED TAB --}}
            <div x-show="activeTab === 'advanced'" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">مقدار پیش‌فرض</label>
                <input type="text" x-model="selectedField.default_value"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
              </div>
              <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">
                <strong>نام فیلد:</strong> <span class="font-mono" x-text="'field_' + selectedField.id"></span><br>
                <strong>ID فیلد:</strong> <span class="font-mono" x-text="selectedField.id"></span>
              </div>
            </div>

          </div>

          {{-- Inspector footer: delete field --}}
          <div class="p-4 border-t border-slate-100 shrink-0">
            <button @click="deleteField(selectedFieldId)"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-red-200 text-red-500 text-sm hover:bg-red-50 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
              حذف این فیلد
            </button>
          </div>
        </div>
      </template>

      {{-- No field selected: Form settings --}}
      <template x-if="!selectedField">
        <div class="flex flex-col h-full">
          <div class="px-4 py-3 border-b border-slate-100 shrink-0">
            <p class="text-sm font-semibold text-slate-700">تنظیمات فرم</p>
            <p class="text-xs text-slate-400 mt-0.5">یک فیلد را از canvas انتخاب کنید تا ویژگی‌های آن نمایش داده شود.</p>
          </div>
          <div class="flex-1 overflow-y-auto p-4 space-y-4">

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">عنوان فرم</label>
              <input type="text" x-model="form.title" @change="autosave"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">نامک (Slug)</label>
              <input type="text" x-model="form.slug" @change="autosave"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">پیام موفقیت</label>
              <textarea x-model="successMessage" @change="autosave" rows="2"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none resize-none"
                        placeholder="فرم با موفقیت ارسال شد."></textarea>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">متن دکمه ارسال</label>
              <input type="text" x-model="submitButtonText" @change="autosave"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                     placeholder="ارسال فرم">
            </div>

            {{-- Quick links --}}
            <div class="pt-2 border-t border-slate-100 space-y-2">
              <p class="text-xs font-medium text-slate-500 mb-2">لینک‌های سریع</p>
              <a href="{{ route('admin.forms.automation.index', $form) }}"
                 class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-indigo-600 transition text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5 10.5 7l3.75 3.75L21 4.5M3.75 19.5h16.5"/></svg>
                خودکارسازی و اعلان‌ها
              </a>
              @platform
              <a href="{{ route('admin.forms.sites.edit', $form) }}"
                 class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-indigo-600 transition text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.86-2.571a4.5 4.5 0 0 0-1.242-7.244l-4.5-4.5a4.5 4.5 0 0 0-6.364 6.364L4.34 8.688"/></svg>
                اتصال به سایت‌ها
              </a>
              @endplatform
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>

  {{-- ════════════════════════════════════════ TEMPLATE MODAL ═══════════════════════════════════════════════════════ --}}
  <div x-show="showTemplateModal"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @keydown.escape.window="showTemplateModal = false"
       class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       role="dialog" aria-modal="true" aria-labelledby="template-modal-title"
       @click.self="showTemplateModal = false"
       style="display:none">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 id="template-modal-title" class="font-bold text-slate-800">انتخاب قالب</h3>
        <button @click="showTemplateModal = false" class="p-1 rounded hover:bg-slate-100 text-slate-400" aria-label="بستن">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($templates as $tpl)
        <button @click="requestApplyTemplate(@js($tpl))"
                class="text-right flex flex-col gap-2 p-4 rounded-xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 transition group">
          <span class="font-semibold text-slate-800 group-hover:text-indigo-700">{{ $tpl['title'] }}</span>
          <span class="text-xs text-slate-500">{{ $tpl['description'] }}</span>
          <span class="text-xs text-indigo-500">{{ count($tpl['fields']) }} فیلد</span>
        </button>
        @endforeach
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════ DELETE CONFIRM MODAL ══════════════════════════════════════════════════ --}}
  <div x-show="showDeleteConfirm"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-100"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @keydown.escape.window="showDeleteConfirm = false; deleteTargetFieldId = null"
       class="fixed inset-0 z-[200] flex items-center justify-center p-4"
       role="alertdialog" aria-modal="true" aria-labelledby="delete-confirm-title"
       style="display:none">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteConfirm = false; deleteTargetFieldId = null" aria-hidden="true"></div>
    <div x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="relative w-full max-w-sm bg-white rounded-2xl shadow-xl ring-1 ring-black/5 z-10 p-6">
      <div class="flex items-start gap-4">
        <div class="shrink-0 w-10 h-10 rounded-full bg-red-50 flex items-center justify-center">
          <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
        </div>
        <div>
          <h3 id="delete-confirm-title" class="font-semibold text-slate-800 mb-1">حذف فیلد</h3>
          <p class="text-sm text-slate-500">آیا مطمئن هستید؟ این فیلد حذف می‌شود.</p>
        </div>
      </div>
      <div class="flex items-center justify-end gap-3 mt-6">
        <button type="button" @click="showDeleteConfirm = false; deleteTargetFieldId = null"
                class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none">
          انصراف
        </button>
        <button type="button" @click="deleteField(deleteTargetFieldId)"
                class="px-4 py-2 text-sm font-medium bg-red-600 hover:bg-red-700 text-white rounded-xl transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none">
          بله، حذف شود
        </button>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════ TEMPLATE APPLY CONFIRM MODAL ════════════════════════════════════════════════════ --}}
  <div x-show="showTemplateApplyConfirm"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-100"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @keydown.escape.window="showTemplateApplyConfirm = false; pendingTemplateData = null"
       class="fixed inset-0 z-[200] flex items-center justify-center p-4"
       role="alertdialog" aria-modal="true" aria-labelledby="template-confirm-title"
       style="display:none">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showTemplateApplyConfirm = false; pendingTemplateData = null" aria-hidden="true"></div>
    <div x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="relative w-full max-w-sm bg-white rounded-2xl shadow-xl ring-1 ring-black/5 z-10 p-6">
      <div class="flex items-start gap-4">
        <div class="shrink-0 w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center">
          <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
        </div>
        <div>
          <h3 id="template-confirm-title" class="font-semibold text-slate-800 mb-1">اعمال قالب</h3>
          <p class="text-sm text-slate-500">فیلدهای فعلی فرم جایگزین می‌شوند. این عملیات قابل بازگشت است (Ctrl+Z).</p>
        </div>
      </div>
      <div class="flex items-center justify-end gap-3 mt-6">
        <button type="button" @click="showTemplateApplyConfirm = false; pendingTemplateData = null"
                class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none">
          انصراف
        </button>
        <button type="button" @click="applyBuiltInTemplate(null)"
                class="px-4 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition active:scale-95 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none">
          بله، اعمال شود
        </button>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════ KEYBOARD SHORTCUTS HELP ══════════════════════════════════════════════ --}}
  <div x-show="showKeyboardHelp"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       @keydown.escape.window="showKeyboardHelp = false"
       class="fixed inset-0 z-[200] flex items-center justify-center p-4"
       role="dialog" aria-modal="true" aria-label="کلیدهای میانبر"
       style="display:none">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showKeyboardHelp = false" aria-hidden="true"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm z-10 p-6">
      <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
        کلیدهای میانبر سازنده
      </h3>
      <div class="space-y-2">
        @foreach([
          ['Ctrl + Z', 'برگشت (Undo)'],
          ['Ctrl + Y', 'انجام دوباره (Redo)'],
          ['Ctrl + S', 'ذخیره همه تغییرات'],
          ['Escape', 'بستن پانل‌ها و مودال‌ها'],
          ['Click روی فیلد', 'انتخاب فیلد برای ویرایش'],
          ['Drag & Drop', 'تغییر ترتیب فیلدها'],
        ] as [$shortcut, $desc])
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 last:border-0">
          <span class="text-sm text-slate-600">{{ $desc }}</span>
          <kbd class="px-2 py-0.5 text-xs font-mono bg-slate-100 text-slate-600 rounded border border-slate-200">{{ $shortcut }}</kbd>
        </div>
        @endforeach
      </div>
      <button @click="showKeyboardHelp = false"
              class="mt-5 w-full py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
        بستن
      </button>
    </div>
  </div>

  {{-- ════════════════════════════════════════════ TOAST ═══════════════════════════════════════════════════════════ --}}
  <div class="fixed bottom-6 left-6 z-50 space-y-2" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
      <div class="toast-enter flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-lg text-sm max-w-xs"
           :class="{
             'bg-emerald-600 text-white': toast.type === 'success',
             'bg-red-600 text-white': toast.type === 'error',
             'bg-slate-800 text-white': toast.type === 'info',
           }">
        <template x-if="toast.type === 'success'">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
        </template>
        <template x-if="toast.type === 'error'">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
        </template>
        <span x-text="toast.message"></span>
      </div>
    </template>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"
        integrity="sha256-jAyjsy6n6PV3/ABbID07EhWEcwpHNGBMiNlGgDsNLWA="
        crossorigin="anonymous"></script>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('formBuilder', (initialData) => ({
    form:             { ...initialData, settings: initialData.settings || {} },
    fields:           initialData.fields || [],
    selectedFieldId:  null,
    activeTab:        'general',
    saveStatus:       'saved',
    history:          [],
    historyIndex:     -1,
    paletteSearch:    '',
    isDraggingOver:   false,
    draggingType:     null,
    showTemplateModal:       false,
    showTemplateApplyConfirm: false,
    pendingTemplateData:     null,
    showVersionHistory:   false,
    showDeleteConfirm:    false,
    showKeyboardHelp:     false,
    deleteTargetFieldId:  null,
    toasts:               [],
    _saveTimer:           null,
    _fieldSaveTimer:      null,
    _saveController:      null,
    _fieldSaveController: null,
    _persistController:   null,
    _crashSaveTimer:      null,
    sortableInstance:     null,
    // Phase 10.2.6 — Enterprise UX
    previewMode:          'desktop',
    hasDraft:             false,
    draftTimestamp:       '',
    _paletteCategories:   @json($paletteCategories),
    _paletteIcons:        @json($paletteIconMap),

    get selectedField() {
      if (! this.selectedFieldId) return null;
      return this.fields.find(f => f.id === this.selectedFieldId) || null;
    },
    get successMessage() {
      return this.form.settings && this.form.settings.success_message ? this.form.settings.success_message : '';
    },
    set successMessage(v) {
      if (! this.form.settings) this.form.settings = {};
      this.form.settings.success_message = v;
    },
    get submitButtonText() {
      return this.form.settings && this.form.settings.submit_button_text ? this.form.settings.submit_button_text : '';
    },
    set submitButtonText(v) {
      if (! this.form.settings) this.form.settings = {};
      this.form.settings.submit_button_text = v;
    },

    init() {
      this.$nextTick(() => this.initSortable());
      this.pushHistory();
      this.checkDraftRecovery();
      document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); this.undo(); }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); this.redo(); }
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); this.saveAll(); }
        if (e.key === '?' && !e.ctrlKey && !e.metaKey && !['INPUT','TEXTAREA'].includes(document.activeElement?.tagName)) {
          this.showKeyboardHelp = !this.showKeyboardHelp;
        }
        if (e.key === 'Escape') {
          this.showKeyboardHelp = false;
          this.showTemplateModal = false;
          this.showTemplateApplyConfirm = false;
          this.pendingTemplateData = null;
        }
      });
      // Unsaved changes protection
      window.addEventListener('beforeunload', (e) => {
        if (this.saveStatus === 'unsaved') {
          e.preventDefault();
          e.returnValue = '';
        }
      });
    },

    initSortable() {
      const canvas = document.getElementById('fields-canvas');
      if (! canvas || typeof Sortable === 'undefined') return;
      if (this.sortableInstance) { this.sortableInstance.destroy(); }
      this.sortableInstance = new Sortable(canvas, {
        animation: 180,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        filter: '#drop-zone',
        onEnd: (evt) => {
          if (evt.oldIndex === undefined || evt.newIndex === undefined) return;
          // Re-index Alpine array to match DOM order (prevents positional desync)
          this.updateFieldOrderFromDom();
          this.persistSort();
          this.pushHistory();
          // Re-attach Sortable after Alpine's x-for reconciles the DOM.
          // Without this, adding/removing fields after a drag leaves Sortable
          // with stale element references, causing subsequent drags to fail silently.
          this.$nextTick(() => this.initSortable());
        },
      });
    },

    updateFieldOrderFromDom() {
      const canvas = document.getElementById('fields-canvas');
      if (! canvas) return;
      const domIds = Array.from(canvas.querySelectorAll('[data-field-id]'))
        .map(el => Number(el.getAttribute('data-field-id')));
      const fieldMap = Object.fromEntries(this.fields.map(f => [f.id, f]));
      // Rebuild Alpine array matching DOM order, preserve fields not in DOM
      const reordered = domIds.map(id => fieldMap[id]).filter(Boolean);
      const remaining = this.fields.filter(f => !domIds.includes(f.id));
      this.fields = [...reordered, ...remaining];
    },

    getFieldTypeLabel(type) {
      const labels = @json(config('forms.field_types'));
      return labels[type] || type;
    },

    fieldTypeVisible(type, query) {
      if (! query) return true;
      const label = this.getFieldTypeLabel(type).toLowerCase();
      return label.includes(query.toLowerCase()) || type.includes(query.toLowerCase());
    },

    categoryVisible(types, query) {
      if (! query) return true;
      return types.some(t => this.fieldTypeVisible(t, query));
    },

    selectField(id) {
      this.selectedFieldId = id;
      this.activeTab = 'general';
    },

    async quickAddField(type) {
      await this.addField({ type });
    },

    onPaletteDragStart(event, type) {
      this.draggingType = type;
      event.dataTransfer.setData('text/plain', type);
      event.dataTransfer.effectAllowed = 'copy';
    },

    onCanvasDragOver(event) {
      if (this.draggingType) { this.isDraggingOver = true; }
    },

    onCanvasDragLeave() {
      this.isDraggingOver = false;
    },

    async onDropToCanvas(event) {
      this.isDraggingOver = false;
      const type = event.dataTransfer.getData('text/plain') || this.draggingType;
      if (type) { await this.addField({ type }); }
      this.draggingType = null;
    },

    onCanvasDrop(event) { this.onDropToCanvas(event); },

    async addField(data) {
      this.saveStatus = 'saving';
      try {
        const resp = await fetch(`{{ route('admin.forms.builder.fields.store', $form) }}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ ...data, position: this.fields.length }),
        });
        const json = await resp.json();
        if (! resp.ok) throw new Error(json.message || 'Error');
        this.fields.push(json.field);
        this.selectField(json.field.id);
        this.saveStatus = 'saved';
        this.pushHistory();
        this.$nextTick(() => this.initSortable());
        this.showToast('فیلد اضافه شد', 'success');
      } catch (e) {
        this.saveStatus = 'unsaved';
        this.showToast('خطا در افزودن فیلد', 'error');
      }
    },

    debouncedFieldSave() {
      this.saveStatus = 'unsaved';
      clearTimeout(this._fieldSaveTimer);
      this._fieldSaveTimer = setTimeout(() => this.saveCurrentField(), 800);
    },

    async saveCurrentField() {
      const field = this.selectedField;
      if (! field) return;
      // Cancel any in-flight field save to prevent stale overwrites
      if (this._fieldSaveController) { this._fieldSaveController.abort(); }
      this._fieldSaveController = new AbortController();
      this.saveStatus = 'saving';
      try {
        const resp = await fetch(`/admin/forms/{{ $form->id }}/builder/fields/${field.id}`, {
          method: 'PUT',
          signal: this._fieldSaveController.signal,
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify(field),
        });
        const json = await resp.json();
        if (! resp.ok) throw new Error(json.message || 'Error');
        // Sync only server-generated fields (sort_order, updated_at).
        // Do NOT replace the entire object: a concurrent user edit would be overwritten
        // if the server response arrives after the user has already changed a property.
        const idx = this.fields.findIndex(f => f.id === field.id);
        if (idx >= 0 && json.field) {
          if (json.field.sort_order !== undefined) this.fields[idx].sort_order = json.field.sort_order;
          if (json.field.updated_at !== undefined) this.fields[idx].updated_at = json.field.updated_at;
        }
        this.saveStatus = 'saved';
        this._fieldSaveController = null;
        this.pushHistory();
      } catch (e) {
        if (e.name !== 'AbortError') { this.saveStatus = 'unsaved'; }
      }
    },

    autosave() {
      this.saveStatus = 'unsaved';
      clearTimeout(this._saveTimer);
      this._saveTimer = setTimeout(() => this.saveFormSettings(), 1200);
    },

    async saveFormSettings() {
      // Cancel any in-flight settings save to prevent race conditions
      if (this._saveController) { this._saveController.abort(); }
      this._saveController = new AbortController();
      this.saveStatus = 'saving';
      try {
        const resp = await fetch(`{{ route('admin.forms.builder.settings', $form) }}`, {
          method: 'PUT',
          signal: this._saveController.signal,
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            title: this.form.title,
            slug: this.form.slug,
            is_active: this.form.is_active,
            success_message: this.successMessage,
            submit_button_text: this.submitButtonText,
          }),
        });
        if (! resp.ok) throw new Error();
        this.saveStatus = 'saved';
        this._saveController = null;
      } catch (e) {
        if (e.name !== 'AbortError') { this.saveStatus = 'unsaved'; }
      }
    },

    async saveAll() {
      if (this.selectedField) await this.saveCurrentField();
      await this.saveFormSettings();
      this.clearDraftFromStorage();
      this.showToast('همه تغییرات ذخیره شد', 'success');
    },

    requestDeleteField(id) {
      this.deleteTargetFieldId = id;
      this.showDeleteConfirm = true;
    },

    async deleteField(id) {
      this.showDeleteConfirm = false;
      const targetId = id || this.deleteTargetFieldId;
      if (! targetId) return;
      const idx = this.fields.findIndex(f => f.id === targetId);
      if (idx < 0) return;
      // Optimistic removal
      this.fields.splice(idx, 1);
      if (this.selectedFieldId === targetId) this.selectedFieldId = null;
      this.deleteTargetFieldId = null;
      try {
        await fetch(`/admin/forms/{{ $form->id }}/builder/fields/${targetId}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        });
        this.pushHistory();
        this.showToast('فیلد حذف شد', 'info');
      } catch (e) {
        this.showToast('خطا در حذف فیلد', 'error');
      }
    },

    async duplicateField(id) {
      try {
        const resp = await fetch(`/admin/forms/{{ $form->id }}/builder/fields/${id}/duplicate`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        });
        const json = await resp.json();
        if (! resp.ok) throw new Error();
        this.fields.push(json.field);
        this.selectField(json.field.id);
        this.pushHistory();
        this.$nextTick(() => this.initSortable());
        this.showToast('فیلد کپی شد', 'success');
      } catch (e) {
        this.showToast('خطا در کپی فیلد', 'error');
      }
    },

    async persistSort() {
      // Cancel any in-flight sort request — only the latest order is authoritative.
      if (this._persistController) { this._persistController.abort(); }
      this._persistController = new AbortController();
      const order = this.fields.map(f => f.id);
      try {
        await fetch(`{{ route('admin.forms.builder.sort', $form) }}`, {
          method: 'POST',
          signal: this._persistController.signal,
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ order }),
        });
        this._persistController = null;
      } catch (e) {
        if (e.name !== 'AbortError') { this.showToast('خطا در ذخیره ترتیب', 'error'); }
      }
    },

    toggleFormStatus() {
      this.form.is_active = ! this.form.is_active;
      this.autosave();
    },

    updateOptions(event) {
      const lines = event.target.value.split('\n').map(l => l.trim()).filter(l => l.length > 0);
      if (this.selectedField) {
        this.selectedField.options = lines;
        this.debouncedFieldSave();
      }
    },

    updateValidationRule(key, value) {
      if (! this.selectedField) return;
      if (! this.selectedField.validation_rules) this.selectedField.validation_rules = {};
      this.selectedField.validation_rules[key] = value;
      this.debouncedFieldSave();
    },

    ensureConditions() {
      if (! this.selectedField) return;
      if (! this.selectedField.conditions) this.selectedField.conditions = { logic: 'AND', rules: [] };
      if (! this.selectedField.conditions.rules) this.selectedField.conditions.rules = [];
    },

    addConditionRule() {
      this.ensureConditions();
      this.selectedField.conditions.rules.push({ field_id: '', operator: 'equals', value: '' });
    },

    removeConditionRule(index) {
      if (! this.selectedField || ! this.selectedField.conditions) return;
      this.selectedField.conditions.rules.splice(index, 1);
      this.debouncedFieldSave();
    },

    requestApplyTemplate(template) {
      this.pendingTemplateData = template;
      this.showTemplateModal = false;
      this.showTemplateApplyConfirm = true;
    },

    applyBuiltInTemplate(template) {
      this.showTemplateApplyConfirm = false;
      const tpl = template || this.pendingTemplateData;
      this.pendingTemplateData = null;
      if (!tpl) return;
      fetch(`{{ route('admin.forms.templates.apply', $form) }}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ template_id: 'builtin', template_data: template }),
      }).then(() => {
        this.showToast('قالب اعمال شد — صفحه بارگذاری مجدد می‌شود...', 'info');
        setTimeout(() => window.location.reload(), 1500);
      });
    },

    // Phase 10.2.6 — Responsive preview
    setPreviewMode(mode) {
      this.previewMode = mode;
    },

    // Phase 10.2.6 — Crash recovery via localStorage
    _draftKey() {
      return `forms_builder_draft_{{ $form->id }}`;
    },

    checkDraftRecovery() {
      try {
        const raw = localStorage.getItem(this._draftKey());
        if (! raw) return;
        const draft = JSON.parse(raw);
        if (! draft || ! draft.fields || ! draft.savedAt) return;
        // Only show recovery banner if draft is newer than 10 seconds (skip trivial cases)
        const age = Date.now() - new Date(draft.savedAt).getTime();
        if (age < 10000) return;
        const d = new Date(draft.savedAt);
        this.draftTimestamp = d.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
        this.hasDraft = true;
      } catch (_) {}
    },

    restoreDraft() {
      try {
        const raw = localStorage.getItem(this._draftKey());
        if (! raw) { this.hasDraft = false; return; }
        const draft = JSON.parse(raw);
        if (draft && draft.fields) {
          this.fields = draft.fields;
          this.pushHistory();
          this.$nextTick(() => this.initSortable());
          this.showToast('پیش‌نویس بازیابی شد', 'success');
        }
      } catch (_) {
        this.showToast('خطا در بازیابی پیش‌نویس', 'error');
      }
      this.hasDraft = false;
    },

    discardDraft() {
      try { localStorage.removeItem(this._draftKey()); } catch (_) {}
      this.hasDraft = false;
    },

    saveDraftToStorage() {
      try {
        localStorage.setItem(this._draftKey(), JSON.stringify({
          fields:  JSON.parse(JSON.stringify(this.fields)),
          savedAt: new Date().toISOString(),
        }));
      } catch (_) {}
    },

    clearDraftFromStorage() {
      try { localStorage.removeItem(this._draftKey()); } catch (_) {}
    },

    pushHistory() {
      if (this.historyIndex < this.history.length - 1) {
        this.history = this.history.slice(0, this.historyIndex + 1);
      }
      this.history.push(JSON.parse(JSON.stringify(this.fields)));
      if (this.history.length > 50) this.history.shift();
      this.historyIndex = this.history.length - 1;
      // Crash-save to localStorage on every history push
      clearTimeout(this._crashSaveTimer);
      this._crashSaveTimer = setTimeout(() => this.saveDraftToStorage(), 500);
    },

    undo() {
      if (this.historyIndex <= 0) return;
      this.historyIndex--;
      this.fields = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
      this.showToast('بازگشت انجام شد', 'info');
    },

    redo() {
      if (this.historyIndex >= this.history.length - 1) return;
      this.historyIndex++;
      this.fields = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
      this.showToast('انجام دوباره', 'info');
    },

    showToast(message, type = 'info') {
      // Deduplicate: skip if identical message+type toast is already visible
      if (this.toasts.some(t => t.message === message && t.type === type)) return;
      const id = Date.now();
      this.toasts.push({ id, message, type });
      setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3000);
    },
  }));
});
</script>
@endpush
