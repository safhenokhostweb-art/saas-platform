@extends(config('forms.layouts.admin'))

@section('header', 'فرم‌ها')

{{-- Phase 10.2: .form-card styles migrated to app.css with --ui-* design tokens --}}

@section('content')
{{-- Phase 10.0: Inject canonical design tokens once per page --}}
<x-core-ui::design-tokens />
<x-core-ui::toast />

{{-- Breadcrumb --}}
<nav class="admin-breadcrumb" aria-label="مسیر صفحه" style="margin:0 0 .85rem;font-size:.78rem;color:#64748b;font-family:Vazirmatn,Tahoma,sans-serif">
    <ol class="admin-breadcrumb-list" style="list-style:none;padding:0;margin:0;display:-webkit-box;display:-ms-flexbox;display:flex;-ms-flex-wrap:wrap;flex-wrap:wrap;-webkit-box-align:center;-ms-flex-align:center;align-items:center;gap:.35rem">
        <li class="admin-breadcrumb-item" style="display:-webkit-inline-box;display:-ms-inline-flexbox;display:inline-flex;-webkit-box-align:center;-ms-flex-align:center;align-items:center;gap:.35rem">
            <a href="{{ route('admin.dashboard') }}" class="admin-breadcrumb-link" style="color:#4f46e5;text-decoration:none">داشبورد</a>
            <span class="admin-breadcrumb-sep" aria-hidden="true">›</span>
        </li>
        <li class="admin-breadcrumb-item" style="display:-webkit-inline-box;display:-ms-inline-flexbox;display:inline-flex;-webkit-box-align:center;-ms-flex-align:center;align-items:center;gap:.35rem">
            <span class="admin-breadcrumb-current" aria-current="page">فرم‌ساز</span>
        </li>
    </ol>
</nav>

{{-- Guide --}}
<details class="mb-6 bg-white border border-slate-200 rounded-xl" style="margin-bottom:1.25rem">
    <summary style="padding:.75rem 1rem;cursor:pointer;display:-webkit-box;display:-ms-flexbox;display:flex;-webkit-box-align:center;-ms-flex-align:center;align-items:center;-webkit-box-pack:justify;-ms-flex-pack:justify;justify-content:space-between;font-family:Vazirmatn,Tahoma,sans-serif">
        <div style="display:-webkit-box;display:-ms-flexbox;display:flex;-webkit-box-align:center;-ms-flex-align:center;align-items:center;gap:.5rem">
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="width:1rem;height:1rem;color:#4f46e5">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span style="font-weight:600;font-size:.875rem">راهنمای ماژول فرم‌ساز (آخرین بروزرسانی: ۱۴۰۵/۰۲/۲۳)</span>
        </div>
        <span style="font-size:.75rem;color:#64748b">کلیک برای باز و بسته کردن</span>
    </summary>
    <div style="padding:.75rem 1rem;border-top:1px solid #e2e8f0;font-size:.875rem;color:#334155;font-family:Vazirmatn,Tahoma,sans-serif">
        <ul style="list-style:disc;padding-right:1.25rem;margin:0;line-height:2">
            <li>فرم‌های حرفه‌ای بسازید و فیلدها، پیام تأیید، و تنظیمات را سفارشی کنید.</li>
            <li>هر فرم را به یک یا چند سایت اختصاص دهید.</li>
            <li>گزارش ارسال‌ها از منوی «همه ارسال‌ها» قابل مشاهده است.</li>
            <li>متا سئو (عنوان، توضیح، canonical، robots) هر فرم را در بخش ویرایش تنظیم کنید.</li>
        </ul>
    </div>
</details>

{{-- ═══ PAGE HEADER ═══ --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-6" role="banner" aria-label="مدیریت فرم‌ها">
  <div>
    <p class="text-slate-500 text-sm">ساخت و مدیریت فرم‌های حرفه‌ای برای سایت</p>
  </div>
  <div class="flex items-center gap-2">
    @platform
    <a href="{{ route('admin.forms.templates.index') }}"
       class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25z"/></svg>
      قالب‌ها
    </a>
    @endplatform
    <a href="{{ route('admin.forms.submissions.index') }}"
       class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75z"/></svg>
      همه ارسال‌ها
    </a>
    <a href="{{ route('admin.forms.create') }}"
       style="color:#fff"
       class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-sm hover:opacity-90 active:scale-95 transition shadow-sm shadow-indigo-200 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      فرم جدید
    </a>
  </div>
</div>

@platformfeature('forms')
<div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4 mb-6 flex items-center gap-3">
  <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09z"/></svg>
  <span class="text-sm text-indigo-700 font-medium">ماژول فرم‌ها فعال — سازنده حرفه‌ای، تحلیل، خودکارسازی، و قالب‌ها در دسترس هستند</span>
</div>
@endplatformfeature

{{-- ═══ FORM GRID ═══ --}}
@if($forms->count())
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    @foreach($forms as $form)
    <div class="form-card bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-200 group flex flex-col">

      {{-- Card header --}}
      <div class="p-5 flex-1">
        <div class="flex items-start justify-between mb-3">
          <div class="flex-1 min-w-0">
            <h3 class="font-bold text-slate-800 truncate group-hover:text-indigo-700 transition">
              {{ $form->title }}
            </h3>
            <p class="text-xs text-slate-400 mt-0.5 font-mono truncate">/forms/{{ $form->slug }}</p>
          </div>
          <div class="ml-2 shrink-0 flex items-center gap-2">
            @if(($form->settings['is_draft'] ?? false))
            <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700 font-medium">پیش‌نویس</span>
            @else
            <x-core-ui::form-status-pill :active="$form->is_active" />
            @endif
          </div>
        </div>

        {{-- Stats --}}
        <div class="flex items-center gap-4 text-xs text-slate-500 mb-4">
          <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>
            {{ $form->fields_count ?? 0 }} فیلد
          </span>
          <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12zm0 0h7.5"/></svg>
            {{ $form->submissions_count ?? 0 }} ارسال
          </span>
          @if(($form->automations_count ?? 0) > 0)
          <span class="flex items-center gap-1 text-indigo-500">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
            {{ $form->automations_count }} قانون
          </span>
          @endif
        </div>

        {{-- Primary action: Builder --}}
        <a href="{{ route('admin.forms.builder', $form) }}"
           class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-50 text-indigo-700 text-sm font-medium hover:bg-indigo-100 transition focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>
          باز کردن در سازنده
        </a>
      </div>

      {{-- Card footer --}}
      <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-1">
          <a href="{{ route('admin.forms.preview', $form) }}" target="_blank"
             class="form-card-action p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none" title="پیش‌نمایش">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg>
          </a>
          <a href="{{ route('admin.forms.analytics', $form) }}"
             class="form-card-action p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none" title="آمار">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/></svg>
          </a>
          <a href="{{ route('admin.forms.submissions.index', ['form_id' => $form->id]) }}"
             class="form-card-action p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none" title="ارسال‌ها">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75z"/></svg>
          </a>

          {{-- Duplicate form (Phase 9.1) --}}
          <form method="POST" action="{{ route('admin.forms.duplicate', $form) }}" class="inline">
            @csrf
            <button type="submit"
                    class="form-card-action p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none" title="کپی فرم">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
            </button>
          </form>

          @platform
          <a href="{{ route('admin.forms.sites.edit', $form) }}"
             class="form-card-action p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none" title="سایت‌ها">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757"/></svg>
          </a>
          @endplatform
        </div>

        <form method="POST" action="{{ route('admin.forms.destroy', $form) }}"
              onsubmit="return confirm('فرم «{{ addslashes($form->title) }}» و تمام داده‌هایش حذف شود؟')">
          @csrf @method('DELETE')
          <button type="submit"
                  class="form-card-action p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-500 focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:outline-none" title="حذف">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
          </button>
        </form>
      </div>
    </div>
    @endforeach
  </div>

  <div class="mt-6">
    {{ $forms->links() }}
  </div>

@else
  {{-- ═══ EMPTY STATE ═══ --}}
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
    <x-core-ui::empty-state
      title="هنوز فرمی ساخته نشده"
      description="اولین فرم حرفه‌ای خود را با سازنده پیشرفته بسازید."
      icon='<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z"/>'
    >
      <a href="{{ route('admin.forms.create') }}"
         class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl text-sm font-medium hover:opacity-90 transition inline-flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        ساخت اولین فرم
      </a>
      @platform
      <a href="{{ route('admin.forms.templates.index') }}"
         class="px-5 py-2.5 border border-slate-200 text-slate-600 rounded-xl text-sm hover:bg-slate-50 transition focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:outline-none">
        مشاهده قالب‌ها
      </a>
      @endplatform
    </x-core-ui::empty-state>
  </div>
@endif

@include('forms::admin.forms.guide')
@endsection
