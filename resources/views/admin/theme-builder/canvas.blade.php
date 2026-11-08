@extends('layouts.canvas')

@section('title', 'قالب‌ساز — ' . $theme->name)

{{-- No SortableJS here, deliberately.
     This page used to pull SortableJS from cdn.jsdelivr.net, but the Canvas
     editor never referenced it: dragging blocks is implemented with native
     HTML5 drag events (draggable / @dragstart / @dragover) plus the
     pointerdown handling in canvas-live-preview.blade.php. The script tag was
     therefore dead weight, and because
     app/Http/Middleware/SecurityHeaders.php does not allow that origin in
     script-src, the browser blocked it on every Canvas load and logged a CSP
     violation.

     Removing the tag is the correct resolution rather than widening script-src:
     no third-party origin is needed for a dependency this page does not use,
     and sortablejs stays available through resources/js/app.js (window.Sortable)
     for the admin pages that actually rely on it. --}}

@section('content')
@php
$blockLabelsJson = collect($registry)->mapWithKeys(fn($d, $t) => [$t => $d['name']])->toJson();
$blockSchemaJson = collect($registry)->mapWithKeys(fn($d, $t) => [$t => ['schema' => $d['schema'] ?? [], 'defaultProps' => $d['defaultProps'] ?? [], 'requires' => $d['requires'] ?? null]])->toJson();
$categoryOrderedKeys = ['layout','content','dynamic','ecommerce','marketing','utility','module'];
@endphp

<div
    x-data="themeCanvas()"
    x-init="init()"
    x-cloak
    class="flex flex-col h-screen bg-slate-100 overflow-hidden select-none"
    @keydown.ctrl.s.window.prevent="saveActiveContext()"
    @keydown.ctrl.z.window.prevent="onUndoShortcut($event)"
    @keydown.ctrl.shift.z.window.prevent="onRedoShortcut($event)"
    @keydown.ctrl.y.window.prevent="onRedoShortcut($event)"
    {{-- Phase 2C: undo/redo keyboard shortcuts; handlers exclude keys typed
         into form controls so Inspector editing never triggers undo. --}}
>

    {{-- ═══════════════════════════════════════════════════════
         TOOLBAR
    ═══════════════════════════════════════════════════════ --}}
    {{-- Phase 3H-B: overflow-x-auto on the toolbar itself is the fix for the
         ~768px defect — page tabs already shrink via flex-1 min-w-0, but
         once (back+name)+the shrink-0 actions group alone exceeds the
         viewport, this lets the WHOLE toolbar scroll horizontally (Save/
         Publish reachable via scroll) instead of silently overflowing
         past the edge. Native scrollbar is left visible so the overflow is
         discoverable, unlike the page-tabs' own always-scrollable strip. --}}
    <div class="min-h-14 bg-white border-b border-slate-200 flex flex-wrap items-center px-4 py-1 gap-3 shrink-0 shadow-sm z-30 overflow-x-visible">

        {{-- Back --}}
        <a href="{{ route('admin.theme-builder.index') }}"
           class="flex items-center gap-1.5 text-slate-500 hover:text-slate-800 text-sm font-medium transition ml-1 shrink-0"
           title="بازگشت به لیست قالب‌ها">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            بازگشت
        </a>
        <div class="h-6 w-px bg-slate-200 shrink-0"></div>

        {{-- Theme Name --}}
        <div class="flex items-center gap-2 shrink-0">
            <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                {{ mb_substr($theme->name, 0, 1) }}
            </div>
            <span class="text-sm font-semibold text-slate-800 max-w-[160px] truncate">{{ $theme->name }}</span>
        </div>
        <div class="h-6 w-px bg-slate-200 shrink-0"></div>

        {{-- Page Tabs. ADR-076: the target switches stay fixed and the page
             tabs scroll inside their own strip (theme-runtime::canvas.tab-strip:
             chevrons, wheel, active tab kept in view), so adding pages never
             pushes the toolbar past the screen or forces a browser zoom-out. --}}
        <div class="tbts-group" data-testid="tb-page-tabs-group">
            {{-- Header/footer are existing Theme block arrays, selected through
                 this one Canvas state path rather than represented as pages. --}}
            <button type="button" @click="switchTarget('page')" :disabled="switchingTarget"
                :class="activeTarget === 'page' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition whitespace-nowrap shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                :aria-pressed="activeTarget === 'page' ? 'true' : 'false'"
                aria-label="صفحات">صفحات</button>
            <button type="button" @click="switchTarget('header')" :disabled="switchingTarget"
                :class="activeTarget === 'header' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition whitespace-nowrap shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                data-canvas-page-tab="__header__" :aria-pressed="activeTarget === 'header' ? 'true' : 'false'"
                aria-label="ویرایش هدر">هدر</button>
            <button type="button" @click="switchTarget('footer')" :disabled="switchingTarget"
                :class="activeTarget === 'footer' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition whitespace-nowrap shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                data-canvas-page-tab="__footer__" :aria-pressed="activeTarget === 'footer' ? 'true' : 'false'"
                aria-label="ویرایش فوتر">فوتر</button>
            <button type="button" @click="openGlobalStylesPanel()"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition whitespace-nowrap shrink-0 bg-violet-50 text-violet-700 hover:bg-violet-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                aria-label="استایل‌های سراسری"
                data-testid="global-styles-entry">استایل‌های سراسری</button>
            <div class="h-6 w-px bg-slate-200 shrink-0"></div>
            <div class="tbts" data-tb-tabstrip data-testid="tb-page-tabs">
                @include('theme-runtime::canvas.tab-strip-nav', ['dir' => 'start'])
                <div class="tbts-viewport" data-tbts-viewport role="group" aria-label="صفحات قالب">
                    <template x-for="(page, key) in pages" :key="key">
                        {{-- Phase 3H-B: delete-page control was a plain hover-only
                             <span> nested inside this switch-page <button> (invalid
                             HTML — buttons can't nest — and unreachable by keyboard).
                             Split into two sibling native <button>s inside a
                             non-interactive wrapper so delete gets real keyboard
                             semantics (Tab-focusable, Enter/Space via the browser)
                             without changing switchPage()'s click target or
                             deletePage()'s existing confirm/logic. --}}
                        <div
                            :data-canvas-page-tab="key"
                            :data-testid="'tb-canvas-page-tab-' + key"
                            :class="activeTarget === 'page' && currentPage === key
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            class="rounded-lg text-xs font-medium transition whitespace-nowrap flex items-center group relative"
                        >
                            <button type="button" @click="switchPage(key)" class="px-3 py-1.5" :aria-current="activeTarget === 'page' && currentPage === key ? 'page' : null">
                                <span x-text="page.title"></span>
                            </button>
                            <button type="button"
                                x-show="key !== 'home'"
                                @click="deletePage(key)"
                                :class="currentPage === key ? 'text-indigo-200 hover:text-white focus-visible:text-white' : 'text-slate-400 hover:text-red-500 focus-visible:text-red-500'"
                                class="text-xs leading-none transition opacity-0 group-hover:opacity-100 focus-visible:opacity-100 pl-0.5 pr-2.5 py-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 focus-visible:ring-red-400 rounded"
                                :aria-label="'حذف صفحه ' + page.title"
                                title="حذف صفحه"
                            >×</button>
                        </div>
                    </template>
                </div>
                @include('theme-runtime::canvas.tab-strip-nav', ['dir' => 'end'])
            </div>
            <button
                @click="showAddPage = true"
                data-testid="tb-add-page-open"
                class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-indigo-600 transition whitespace-nowrap shrink-0"
                title="افزودن صفحه جدید"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                صفحه جدید
            </button>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2 shrink-0">
            {{-- WU-1: Inspect Mode (theme-runtime::canvas.inspect) — outlines,
                 widget ids and the copy action bar inside the preview. --}}
            <button type="button" @click="toggleInspect()" data-tb-inspect-toggle
                :aria-pressed="inspectOn ? 'true' : 'false'"
                title="حالت بازرسی (Ctrl+I)"
                class="flex items-center gap-1 h-7 px-2 rounded-lg border text-xs font-medium transition"
                :class="inspectOn ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-300'">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V5a1 1 0 011-1h3m8 0h3a1 1 0 011 1v3m0 8v3a1 1 0 01-1 1h-3m-8 0H5a1 1 0 01-1-1v-3M9.5 9.5l5 5m0-5l-5 5"/></svg>
                بازرسی
            </button>
            {{-- WU-2: Design HUD summary from the preview (TbAudit, Sentinel codes). --}}
            <span x-show="inspectOn && auditSummary" x-cloak data-tb-audit-chip
                :data-tone="auditSummary?.tone"
                role="status" aria-live="polite"
                :title="auditSummary ? `سلامت بوم در عرض ${auditSummary.width}px` : ''"
                class="flex items-center gap-1.5 h-7 px-2 rounded-lg border text-xs font-medium"
                :class="{ 'border-rose-200 bg-rose-50 text-rose-700': auditSummary?.tone === 'error', 'border-amber-200 bg-amber-50 text-amber-800': auditSummary?.tone === 'warn', 'border-emerald-200 bg-emerald-50 text-emerald-700': auditSummary?.tone === 'ok' }">
                <span class="w-2 h-2 rounded-full" aria-hidden="true"
                    :class="{ 'bg-rose-500': auditSummary?.tone === 'error', 'bg-amber-500': auditSummary?.tone === 'warn', 'bg-emerald-500': auditSummary?.tone === 'ok' }"></span>
                <span x-text="!auditSummary ? '' : (auditSummary.tone === 'ok' ? 'سالم' : [auditSummary.counts.blocker ? `${auditSummary.counts.blocker} خطا` : '', auditSummary.counts.warning ? `${auditSummary.counts.warning} هشدار` : ''].filter(Boolean).join(' · '))"></span>
            </span>
            {{-- Undo / Redo --}}
            <button @click="undo()" :disabled="!canUndo"
                title="واگرد (Ctrl+Z)"
                class="flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-300 disabled:opacity-30 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6M3 10l6-6"/></svg>
            </button>
            <button @click="redo()" :disabled="!canRedo"
                title="از نو (Ctrl+Y)"
                class="flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-300 disabled:opacity-30 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2M21 10l-6 6M21 10l-6-6"/></svg>
            </button>
            {{-- Phase 2C: history trail — the last 5 entries, newest first;
                 hover reveals the operation type and target. --}}
            <div class="relative" x-data="{ historyOpen: false }">
                <button @click="historyOpen = !historyOpen"
                    class="flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-300 disabled:opacity-30 transition"
                    :disabled="recentHistory.length === 0" title="تاریخچه عملیات">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
                <div x-show="historyOpen" @click.outside="historyOpen = false" x-transition
                    class="absolute right-0 top-9 z-50 w-56 rounded-xl border border-slate-200 bg-white shadow-xl p-2 text-xs">
                    <p class="px-2 py-1 font-semibold text-slate-500">تاریخچه (آخرین به اول)</p>
                    <template x-for="(item, i) in recentHistory.slice(0, 5)" :key="historyVersion + '-' + i">
                        <div class="px-2 py-1 rounded-md flex items-center justify-between"
                            :class="i === 0 ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600'">
                            <span x-text="item.type === 'move' ? 'جابه‌جایی' : item.type === 'insert' ? 'افزودن' : item.type === 'delete' ? 'حذف' : item.type === 'duplicate' ? 'تکثیر' : 'ویرایش مقدار'"></span>
                            <span class="text-slate-400 font-mono" x-text="item.targetId ?? ''"></span>
                        </div>
                    </template>
                </div>
            </div>
            <div class="h-6 w-px bg-slate-200 shrink-0"></div>

            {{-- Dirty indicator --}}
            <span x-show="isDirty" class="text-amber-500 text-xs font-medium flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse inline-block"></span>
                ذخیره نشده
            </span>

            {{-- TB6: Template Library button --}}
            <button type="button" data-testid="tb-open-templates" @click="openTemplateLibrary()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-purple-200 bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-medium transition"
                title="کتابخانه قالب‌ها">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zm12 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                قالب‌ها
            </button>
            {{-- Phase 3A — Section Library (sections / header / footer) --}}
            <button @click="openSectionLibrary(sectionLibraryZone())"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-medium transition"
                title="کتابخانه بخش‌ها و ترکیب‌ها">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                بخش‌ها
            </button>
            {{-- Phase 3G-B-6: Extensions button --}}
            <button @click="openExtensionsModal()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-teal-200 bg-teal-50 text-teal-700 hover:bg-teal-100 text-xs font-medium transition relative"
                title="افزونه‌های قالب">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1h2a2 2 0 012 2v2h-1a2 2 0 100 4h1v2a2 2 0 01-2 2h-2v-1a2 2 0 10-4 0v1H7a2 2 0 01-2-2v-2h1a2 2 0 100-4H5V7a2 2 0 012-2h2V4z"/></svg>
                افزونه‌ها
                <span x-show="Object.keys(extensionSelectionErrors).length > 0"
                    class="absolute -top-1 -left-1 w-2.5 h-2.5 rounded-full bg-red-500 border border-white"
                    title="یک یا چند انتخاب افزونه نامعتبر است"></span>
            </button>
            {{-- TB6: Save as Template button (only when block selected) --}}
            <button x-show="selectedBlock" @click="showSaveAsTemplate = true"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 text-xs font-medium transition"
                title="ذخیره به‌عنوان قالب">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                ذخیره قالب
            </button>
            <div class="h-6 w-px bg-slate-200 shrink-0"></div>

            {{-- Preview in new tab --}}
            <button
                @click="openPreview()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600 text-xs font-medium transition"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                پیش‌نمایش
            </button>

            {{-- Save --}}
            {{-- P1: disabled only while this user's save is outstanding (a second
                 click would join the same queued save anyway). Editing stays
                 fully available; "در صف" means it waits behind a save already
                 on the wire and will include every edit made until it starts. --}}
            <button
                @click="saveCurrentPage()"
                :disabled="saving"
                :aria-busy="saving ? 'true' : 'false'"
                data-testid="canvas-save-button"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-xs font-medium transition disabled:opacity-60 disabled:cursor-progress"
            >
                <svg x-show="!saving" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <svg x-show="saving" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                <span x-text="saveWaiting ? 'در صف ذخیره…' : (saving ? 'در حال ذخیره...' : 'ذخیره')"></span>
            </button>
            <span class="sr-only" role="status" aria-live="polite"
                x-text="saveWaiting ? 'ذخیره در صف است' : (saving || quickSaving ? 'در حال ذخیره' : '')"></span>

            {{-- Publish --}}
            <button
                @click="publishTheme()"
                :disabled="publishing"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition disabled:opacity-60"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                <span x-text="publishing ? 'انتشار...' : 'انتشار'"></span>
            </button>

            {{-- UI-C2D/C2E: checkpoint history, unpublish, clone, export,
                 import — the remaining raw-editor-only operational actions,
                 absorbed here behind one compact menu rather than five new
                 toolbar buttons. --}}
            <div class="relative shrink-0" @click.outside="showMoreMenu = false">
                <button
                    type="button"
                    @click="showMoreMenu = !showMoreMenu"
                    class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                    aria-haspopup="true" :aria-expanded="showMoreMenu ? 'true' : 'false'"
                    title="عملیات بیشتر"
                    data-testid="tb-more-actions-toggle"
                >
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                    بیشتر
                </button>
                <div x-show="showMoreMenu" x-cloak x-transition.opacity
                    class="absolute left-0 top-full mt-1 w-56 max-w-[calc(100vw-1.5rem)] bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-40 text-sm"
                    data-testid="tb-more-actions-menu"
                >
                    <button type="button" @click="showMoreMenu = false; openCheckpointsModal()"
                        class="w-full text-right px-3 py-2 hover:bg-slate-50 text-slate-700 transition focus:outline-none focus-visible:bg-slate-50"
                        data-testid="tb-action-checkpoints">تاریخچه / چک‌پوینت</button>
                    <button type="button" @click="showMoreMenu = false; openCloneModal()"
                        class="w-full text-right px-3 py-2 hover:bg-slate-50 text-slate-700 transition focus:outline-none focus-visible:bg-slate-50"
                        data-testid="tb-action-clone">کپی تم</button>
                    <a href="{{ route('admin.theme-builder.export', $theme) }}" @click="showMoreMenu = false"
                        class="block w-full text-right px-3 py-2 hover:bg-slate-50 text-slate-700 transition focus:outline-none focus-visible:bg-slate-50"
                        data-testid="tb-action-export">خروجی JSON</a>
                    <button type="button" @click="showMoreMenu = false; $refs.tbImportFileInput.click()"
                        class="w-full text-right px-3 py-2 hover:bg-slate-50 text-slate-700 transition focus:outline-none focus-visible:bg-slate-50"
                        data-testid="tb-action-import">درون‌ریزی JSON</button>
                    <input type="file" x-ref="tbImportFileInput" accept="application/json" class="hidden" @change="importThemeFile($event)">
                    <div class="h-px bg-slate-100 my-1"></div>
                    <button type="button" x-show="themeStatus === 'published'" @click="showMoreMenu = false; unpublishTheme()"
                        :disabled="unpublishing"
                        class="w-full text-right px-3 py-2 hover:bg-red-50 text-red-600 transition disabled:opacity-60 focus:outline-none focus-visible:bg-red-50"
                        data-testid="tb-action-unpublish">
                        <span x-text="unpublishing ? 'در حال خروج از انتشار...' : 'خروج از انتشار'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         MAIN 3-PANEL
    ═══════════════════════════════════════════════════════ --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- ── PANEL A: Block Library + Structure Tree (RTL = right side) ─────── --}}
        <div class="w-64 bg-white border-l border-slate-200 flex flex-col overflow-hidden shrink-0 z-20">

            {{-- TB4: Panel Tab Toggle --}}
            <div class="flex border-b border-slate-100 shrink-0">
                <button @click="leftPanelTab='blocks'"
                    :class="leftPanelTab==='blocks' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2 text-xs text-center transition">
                    <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    بلوک‌ها
                </button>
                <button @click="leftPanelTab='tree'"
                    :class="leftPanelTab==='tree' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2 text-xs text-center transition">
                    <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    ساختار
                </button>
                <button @click="leftPanelTab='health'" data-testid="tb-canvas-health-tab"
                    :class="leftPanelTab==='health' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-2 text-xs text-center whitespace-nowrap transition">
                    <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    سلامت ویجت
                </button>
            </div>

            {{-- WU-3: «سلامت ویجت» — the Design HUD as a per-widget list (theme-runtime::canvas.health). --}}
            <div x-show="leftPanelTab==='health'" x-cloak class="flex-1 overflow-y-auto">
                @include('theme-runtime::canvas.health', ['active' => "leftPanelTab === 'health'"])
            </div>

            {{-- TB4: Structure Tree --}}
            <div x-show="leftPanelTab==='tree'" class="flex-1 overflow-y-auto">
                <div x-show="flattenTree().length === 0" class="flex flex-col items-center justify-center py-10 text-center text-slate-400">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>
                    <p class="text-xs">هیچ بلوکی وجود ندارد</p>
                </div>
                <div class="py-1">
                    <template x-for="node in flattenTree()" :key="node.block.id + node.depth">
                        <div
                            @click="selectBlock(node.path)"
                            @keydown.enter.prevent="selectBlock(node.path)"
                            @keydown.space.prevent="selectBlock(node.path)"
                            tabindex="0"
                            role="treeitem"
                            :aria-selected="isSelectedPath(node.path) ? 'true' : 'false'"
                            :aria-label="navigatorLabel(node.block)"
                            :data-block-type="node.block.type"
                            :style="{ paddingRight: (node.depth * 14 + 8) + 'px' }"
                            :class="isSelectedPath(node.path)
                                ? 'bg-indigo-50 text-indigo-700 font-medium border-r-2 border-indigo-500'
                                : 'text-slate-600 hover:bg-slate-50 border-r-2 border-transparent'"
                            class="flex items-center gap-1.5 py-1.5 pl-2 cursor-pointer transition text-xs"
                        >
                            {{-- Connector indent --}}
                            <span x-show="node.depth > 0" class="text-slate-300 text-xs shrink-0 leading-none">└</span>

                            {{-- Expand/collapse toggle for container types --}}
                            <button x-show="isContainerType(node.block.type) && (node.block.children?.length > 0)"
                                @click.stop="toggleTreeNode(node.block.id)"
                                :class="treeExpanded[node.block.id] === false ? '-rotate-90' : ''"
                                class="w-3 h-3 flex items-center justify-center text-slate-400 transition-transform shrink-0">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <span x-show="!isContainerType(node.block.type) || !(node.block.children?.length > 0)"
                                  class="w-3 h-3 shrink-0 flex items-center justify-center">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                            </span>

                            {{-- Color dot --}}
                            <span :class="blockCategoryColor(node.block.type)" class="w-1.5 h-1.5 rounded-full shrink-0"></span>

                            {{-- Block name + optional custom label --}}
                            <span class="truncate flex-1" x-text="navigatorLabel(node.block)"></span>
                            <span x-show="nodeLocked(node.block)" class="text-[10px] text-amber-600 shrink-0" title="ساختار قفل است" aria-label="ساختار قفل است">🔒</span>
                            {{-- TB5: hide-on-device badges --}}
                            <span x-show="node.block.style?.hideDesktop" class="text-[9px] bg-slate-200 text-slate-500 px-1 rounded shrink-0">D</span>
                            <span x-show="node.block.style?.hideTablet"  class="text-[9px] bg-slate-200 text-slate-500 px-1 rounded shrink-0">T</span>
                            <span x-show="node.block.style?.hideMobile"  class="text-[9px] bg-slate-200 text-slate-500 px-1 rounded shrink-0">M</span>
                            {{-- TB5: responsive override dot --}}
                            <span x-show="node.block.responsive && Object.keys(node.block.responsive).length > 0"
                                class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" title="دارای override ریسپانسیو"></span>
                        </div>
                    </template>
                </div>
                {{-- Tree hint --}}
                <div class="px-3 py-2 border-t border-slate-100 shrink-0 bg-slate-50">
                    <p class="text-[10px] text-slate-400 text-center">کلیک برای انتخاب بلوک</p>
                </div>
            </div>

            {{-- Library Header (only when blocks tab active) --}}
            <div x-show="leftPanelTab==='blocks'" class="px-3 py-2.5 border-b border-slate-100 shrink-0">
                <div class="relative">
                    <input
                        x-model="librarySearch"
                        type="text"
                        placeholder="جستجوی بلوک..."
                        class="w-full text-sm pr-7 pl-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 placeholder-slate-400"
                    >
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute top-2 right-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- Library Blocks (only when blocks tab active) --}}
            <div x-show="leftPanelTab==='blocks'" class="flex-1 overflow-y-auto">
                {{-- Phase 3C/M3: 'forms' added so the interactive_form widget
                     (and future form-category types) is actually selectable in the
                     palette — the category existed in the registry but was silently
                     dropped from the UI list. --}}
                @php $orderedCats = ['layout','content','dynamic','ecommerce','marketing','forms','utility','module','extension']; @endphp
                @foreach($orderedCats as $catKey)
                    @if(!isset($byCategory[$catKey])) @continue @endif
                    @php
                        $catLabel = $catLabels[$catKey] ?? $catKey;
                        $catIcon  = $catIcons[$catKey] ?? '▪';
                        $catBlocks = $byCategory[$catKey];
                        $catColors = [
                            'layout'    => 'text-blue-600 bg-blue-50',
                            'content'   => 'text-green-700 bg-green-50',
                            'dynamic'   => 'text-purple-600 bg-purple-50',
                            'ecommerce' => 'text-emerald-700 bg-emerald-50',
                            'marketing' => 'text-orange-600 bg-orange-50',
                            'forms'     => 'text-cyan-700 bg-cyan-50',
                            'utility'   => 'text-slate-600 bg-slate-100',
                            'module'    => 'text-indigo-700 bg-indigo-50',
                            'extension' => 'text-teal-700 bg-teal-50',
                        ];
                        $badgeClass = $catColors[$catKey] ?? 'text-slate-600 bg-slate-100';
                    @endphp
                    <div
                        x-show="isCategoryVisible('{{ $catKey }}')"
                        class="border-b border-slate-100"
                    >
                        {{-- Category Header --}}
                        <button
                            @click="toggleCategory('{{ $catKey }}')"
                            class="w-full flex items-center justify-between px-3 py-2 hover:bg-slate-50 transition text-right"
                        >
                            <div class="flex items-center gap-2">
                                <span class="text-sm">{{ $catIcon }}</span>
                                <span class="text-xs font-semibold text-slate-700">{{ $catLabel }}</span>
                                <span class="text-xs text-slate-400 font-normal">({{ count($catBlocks) }})</span>
                            </div>
                            <svg
                                :class="expandedCats['{{ $catKey }}'] ? 'rotate-180' : ''"
                                class="w-3 h-3 text-slate-400 transition-transform"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        {{-- Blocks in Category --}}
                        <div x-show="expandedCats['{{ $catKey }}']" x-collapse>
                            <div class="px-2 pb-2 grid grid-cols-2 gap-1">
                                @foreach($catBlocks as $blockType => $blockDef)
                                    <button
                                        draggable="true"
                                        data-block-type="{{ $blockType }}"
                                        @click="if (!suppressPaletteClick) addBlock('{{ $blockType }}')"
                                        @pointerdown="startPointerLibraryDrag($event, '{{ $blockType }}')"
                                        @pointermove.window="movePointerLibraryDrag($event)"
                                        @pointerup.window="finishPointerLibraryDrag($event)"
                                        @pointercancel.window="cancelPointerLibraryDrag($event)"
                                        @dragstart="startDragBlock($event, '{{ $blockType }}')"
                                        @dragend="endDragBlock()"
                                        x-show="isBlockVisible('{{ $blockType }}', '{{ $blockDef['name'] }}')"
                                        class="flex flex-col items-start gap-1 p-2 rounded-lg border border-slate-200 bg-white hover:border-indigo-400 hover:bg-indigo-50 hover:shadow-sm transition text-right cursor-grab active:cursor-grabbing group"
                                        title="{{ $blockDef['name'] }}"
                                    >
                                        <div class="text-base leading-none">
                                            @switch($blockType)
                                                @case('hero') 🌟 @break @case('section') 📦 @break
                                                @case('grid') 🔲 @break @case('columns') ⬜ @break
                                                @case('tabs') 📑 @break @case('accordion') 🪗 @break
                                                @case('slider') 🎠 @break @case('heading') 🔠 @break
                                                @case('text') 📄 @break @case('image') 🖼️ @break
                                                @case('gallery') 🗂️ @break @case('video') 🎬 @break
                                                @case('button') 🔘 @break @case('cta') 📢 @break
                                                @case('countdown') ⏱️ @break @case('blog_posts') 📰 @break
                                                @case('products') 🏷️ @break @case('testimonials') ⭐ @break
                                                @case('module:blog_posts') 📰 @break
                                                @case('module:shop_product_grid') 🛍️ @break
                                                @case('module:courses_grid') 🎓 @break
                                                @case('module:pricing_table') 💰 @break
                                                @case('module:support_button') 💬 @break
                                                @default 🧩
                                            @endswitch
                                        </div>
                                        <span class="text-[10px] font-medium text-slate-700 group-hover:text-indigo-700 leading-tight line-clamp-2">{{ $blockDef['name'] }}</span>
                                        @if($blockDef['requires'] ?? null)
                                            <span class="text-[9px] px-1.5 py-0.5 rounded {{ $badgeClass }} font-medium leading-none">{{ $blockDef['requires'] }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Library hint --}}
            <div class="px-3 py-2 border-t border-slate-100 shrink-0 bg-slate-50">
                <p class="text-[10px] text-slate-400 text-center">کلیک یا بکشید برای افزودن</p>
            </div>
        </div>

        {{-- ── PANEL B: Live Canvas (center) ───────────────────── --}}
        <div class="flex-1 flex flex-col overflow-hidden bg-slate-200">

            {{-- Canvas Toolbar (device toggle + info) --}}
            <div class="h-10 bg-white border-b border-slate-200 flex items-center px-4 gap-3 shrink-0 z-10">
                {{-- Device Toggle --}}
                <div class="flex items-center gap-0.5 bg-slate-100 rounded-lg p-0.5">
                    <button @click="setDevice('desktop')"
                        :class="device==='desktop' ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500 hover:text-slate-700'"
                        class="flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        دسکتاپ
                    </button>
                    <button @click="setDevice('tablet')"
                        :class="device==='tablet' ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500 hover:text-slate-700'"
                        class="flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        تبلت
                    </button>
                    <button @click="setDevice('mobile')"
                        :class="device==='mobile' ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500 hover:text-slate-700'"
                        class="flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        موبایل
                    </button>
                </div>

                <div class="h-4 w-px bg-slate-200 shrink-0"></div>

                {{-- Block count + page info --}}
                <span class="text-xs text-slate-500" x-text="blocks.length + ' بلوک — ' + (pages[currentPage]?.title ?? currentPage)"></span>

                {{-- Saving indicator --}}
                <span x-show="quickSaving" class="text-xs text-indigo-500 flex items-center gap-1 mr-auto">
                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    ذخیره...
                </span>
            </div>

            {{-- Canvas Area --}}
            <div class="flex-1 overflow-auto py-4 flex justify-center">
                <div
                    class="relative bg-white shadow-xl transition-all duration-300 mx-auto"
                    :style="device==='desktop' ? 'width:100%;max-width:none' : device==='tablet' ? 'width:768px;max-width:100%' : 'width:390px;max-width:100%'"
                    style="min-height:500px;"
                >
                    {{-- Live Preview Iframe --}}
                    <iframe
                        id="tb-live-iframe"
                        :src="livePreviewUrl()"
                        class="w-full border-0 block"
                        style="min-height:500px;"
                        @load="onIframeLoad()"
                        title="پیش‌نمایش زنده قالب"
                    ></iframe>

                    {{-- Drag overlay (TB3-03): shown during drag from library --}}
                    <div
                        id="tb-drag-overlay"
                        x-show="isDraggingBlock"
                        class="absolute inset-0 z-20 flex items-start justify-center pt-24 pointer-events-none"
                        style="background: rgba(99,102,241,0.06); border: 3px dashed #6366f1;"
                    >
                        <div class="bg-white rounded-2xl shadow-xl px-8 py-5 text-center pointer-events-none">
                            <div class="text-3xl mb-2">🎯</div>
                            <p class="text-sm font-bold text-indigo-700">رها کنید تا بلوک اضافه شود</p>
                            <p class="text-xs text-slate-400 mt-1" x-text="'نوع: ' + (draggedBlockLabel || draggedBlockType)"></p>
                        </div>
                    </div>

                    {{-- Drag drop receiver (pointer-events on) --}}
                    <div
                        x-show="isDraggingBlock"
                        class="absolute inset-0 z-10"
                        @dragover.prevent="onDragOverCanvas($event)"
                        @dragleave="onDragLeaveCanvas()"
                        @drop.prevent="onDropToCanvas($event)"
                    ></div>

                    {{-- Loading overlay --}}
                    <div
                        x-show="iframeLoading"
                        class="absolute inset-0 z-30 bg-white flex items-center justify-center"
                        style="min-height:200px;"
                    >
                        <div class="flex items-center gap-3 text-slate-400">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                            <span class="text-sm">در حال بارگذاری...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── PANEL C: Props Panel (LTR = left side) ──────────── --}}
        <div class="w-80 bg-white border-r border-slate-200 flex flex-col overflow-hidden shrink-0 z-20">

            {{-- No selection --}}
            <div x-show="!selectedPath" class="flex flex-col items-center justify-center h-full text-center px-6">
                <div class="text-5xl mb-3 opacity-20">✏️</div>
                <p class="text-slate-400 text-sm">یک بلوک از Canvas کلیک کنید</p>
                <p class="text-slate-300 text-xs mt-1">برای ویرایش خصوصیات</p>
            </div>

            {{-- Selected block props --}}
            <template x-if="selectedPath && selectedBlock">
                <div class="flex flex-col h-full overflow-hidden">

                    {{-- Props Header --}}
                    <div class="px-4 py-3 border-b border-slate-100 shrink-0">
                        {{-- Breadcrumb path for nested blocks --}}
                        <div x-show="selectedPath.length > 1" class="flex items-center gap-1 mb-1.5 flex-wrap">
                            <template x-for="(pidx, d) in selectedPath.slice(0, -1)" :key="d">
                                <span class="flex items-center gap-1">
                                    <span class="text-[10px] text-slate-400 cursor-pointer hover:text-indigo-500"
                                          @click="selectBlock(selectedPath.slice(0, d+1))"
                                          x-text="blockLabel(getBlockByPath(selectedPath.slice(0,d+1))?.type ?? '')"></span>
                                    <svg class="w-2.5 h-2.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                </span>
                            </template>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div :class="blockCategoryColor(selectedBlock.type)" class="w-2 h-2 rounded-full"></div>
                                <span class="text-sm font-bold text-slate-800" x-text="blockLabel(selectedBlock.type)"></span>
                            </div>
                            <button @click="selectBlockById(null); sendToIframe({action:'select', blockId:null})" class="text-slate-400 hover:text-slate-600 text-lg leading-none">×</button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5 font-mono" x-text="selectedBlock.type"></p>
                    </div>

                    {{-- Block Actions (Move / Duplicate / Delete) — TB4: path-aware --}}
                    <div class="flex items-center gap-1.5 px-3 py-2 border-b border-slate-100 shrink-0">
                        <button @click="moveSelectedUp()" :disabled="selectedEditorCapabilities.lockedStructure || !canMoveUp()"
                            title="بالاتر"
                            class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-30 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                        </button>
                        <button @click="moveSelectedDown()" :disabled="selectedEditorCapabilities.lockedStructure || !canMoveDown()"
                            title="پایین‌تر"
                            class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-30 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <button @click="duplicateSelected()" :disabled="selectedEditorCapabilities.lockedStructure"
                            title="کپی بلوک"
                            class="flex-1 flex items-center justify-center py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                        <button @click="removeSelected()" :disabled="selectedEditorCapabilities.lockedStructure"
                            title="حذف بلوک"
                            class="flex-1 flex items-center justify-center py-1.5 rounded-lg border border-red-200 text-xs font-medium text-red-500 hover:bg-red-50 hover:border-red-400 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>

                    {{-- 3 Tabs: Content / Style / Advanced --}}
                    <div class="flex border-b border-slate-100 shrink-0">
                        <button @click="selectedTab = 'content'"
                            :class="selectedTab === 'content' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                            class="flex-1 py-2 text-xs text-center transition">محتوا</button>
                        <button @click="selectedTab = 'style'"
                            :class="selectedTab === 'style' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                            class="flex-1 py-2 text-xs text-center transition">استایل</button>
                        <button @click="selectedTab = 'advanced'"
                            :class="selectedTab === 'advanced' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                            class="flex-1 py-2 text-xs text-center transition">پیشرفته</button>
                    </div>

                    {{-- ADR-082: one scroll area for the three tabs. The schema
                         field loop (below the generic panes) serves every tab:
                         it lists the active tab's own fields (`panel`) and moves
                         above the generic sections on Style / Advanced (order-first). --}}
                    <div class="flex-1 overflow-y-auto flex flex-col" data-testid="tb-inspector-scroll">
                    {{-- ── STYLE TAB ──────────────────────────────────────────── --}}
                    <div x-show="selectedTab === 'style'">

                        {{-- TB5: Breakpoint Toggle Bar — for the generic wrapper
                             sections below; a widget that hides them all (ADR-082)
                             switches devices beside each responsive field instead. --}}
                        <div class="flex items-center gap-0.5 bg-slate-50 border-b border-slate-100 p-2"
                            x-show="CanvasControls.hiddenStyleSections(selectedEditorCapabilities).length < Object.keys(CanvasControls.GENERIC_STYLE_SECTIONS).length"
                            data-testid="tb-style-breakpoints">
                            <span class="text-[10px] text-slate-400 ml-1">ریسپانسیو:</span>
                            <button @click="activeBreakpoint='desktop'"
                                :class="activeBreakpoint==='desktop' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-100 border border-slate-200'"
                                class="flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-medium transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                D
                            </button>
                            <button @click="activeBreakpoint='tablet'"
                                :class="activeBreakpoint==='tablet' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-100 border border-slate-200'"
                                class="relative flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-medium transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                T
                                <span x-show="selectedBlock?.responsive?.tablet && Object.keys(selectedBlock.responsive.tablet).length > 0"
                                    class="absolute -top-1 -right-1 w-2 h-2 bg-amber-400 rounded-full"></span>
                            </button>
                            <button @click="activeBreakpoint='mobile'"
                                :class="activeBreakpoint==='mobile' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-100 border border-slate-200'"
                                class="relative flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-medium transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V8a2 2 0 00-2-2H8a2 2 0 00-2 2v13a2 2 0 002 2z"/></svg>
                                M
                                <span x-show="selectedBlock?.responsive?.mobile && Object.keys(selectedBlock.responsive.mobile).length > 0"
                                    class="absolute -top-1 -right-1 w-2 h-2 bg-amber-400 rounded-full"></span>
                            </button>
                            <span x-show="activeBreakpoint !== 'desktop'" class="mr-auto text-[9px] text-amber-600 font-medium">
                                override روی <span x-text="activeBreakpoint === 'tablet' ? 'تبلت' : 'موبایل'"></span>
                            </span>
                            <button x-show="activeBreakpoint !== 'desktop' && CanvasControls.hiddenStyleSections(selectedEditorCapabilities).length === 0"
                                @click="resetBlockStyle()"
                                class="mr-1 text-[10px] text-red-500 hover:text-red-700 font-medium underline">
                                پاک کردن
                            </button>
                        </div>

                        {{-- ADR-082: a widget hiding the generic sections whose block
                             still holds values saved in them before. --}}
                        <template x-if="hasHiddenGenericStyle()">
                            <div class="tbc-notice" data-testid="tb-hidden-generic-style">
                                این ویجت استایل‌های خودش را دارد و بخش‌های عمومی استایل برایش پنهان است، اما این بلوک هنوز مقدارهایی از آن بخش‌ها دارد که روی ظاهرش اثر می‌گذارند.
                                <button type="button" class="tbc-reset" @click="clearHiddenGenericStyle()" data-testid="tb-clear-hidden-generic-style">حذف این مقدارها</button>
                            </div>
                        </template>

                        {{-- Section: Typography --}}
                        <div class="border-b border-slate-100" x-show="genericStyleVisible('typography')">
                            <button @click="styleSection.typography = !styleSection.typography"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <span>تایپوگرافی</span>
                                <svg :class="styleSection.typography ? 'rotate-180' : ''" class="w-3 h-3 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="styleSection.typography" class="px-4 pb-4 space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[10px] text-slate-500 mb-1 flex items-center gap-1">
                                            سایز فونت (px)
                                            <span x-show="hasResponsiveOverride('fontSize', activeBreakpoint) && activeBreakpoint !== 'desktop'" class="w-1.5 h-1.5 bg-amber-400 rounded-full inline-block"></span>
                                        </p>
                                        <input type="number" min="8" max="200"
                                            :value="getCascadedValue('fontSize', '')"
                                            @input.debounce.300ms="updateStyle('fontSize', $event.target.value === '' ? '' : Number($event.target.value))"
                                            :placeholder="activeBreakpoint !== 'desktop' ? (selectedBlock?.style?.fontSize ?? 'ارث') : 'پیش‌فرض'"
                                            class="w-full text-xs px-2 py-1.5 rounded-lg border focus:outline-none focus:ring-1 focus:ring-indigo-300"
                                            :class="hasResponsiveOverride('fontSize', activeBreakpoint) && activeBreakpoint !== 'desktop' ? 'border-amber-300 bg-amber-50' : 'border-slate-200'">
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-slate-500 mb-1 flex items-center gap-1">
                                            وزن فونت
                                            <span x-show="hasResponsiveOverride('fontWeight', activeBreakpoint) && activeBreakpoint !== 'desktop'" class="w-1.5 h-1.5 bg-amber-400 rounded-full inline-block"></span>
                                        </p>
                                        <select :value="getCascadedValue('fontWeight', '')"
                                            @change="updateStyle('fontWeight', $event.target.value)"
                                            class="w-full text-xs px-2 py-1.5 rounded-lg border focus:outline-none focus:ring-1 focus:ring-indigo-300"
                                            :class="hasResponsiveOverride('fontWeight', activeBreakpoint) && activeBreakpoint !== 'desktop' ? 'border-amber-300 bg-amber-50' : 'border-slate-200'">
                                            <option value="">پیش‌فرض</option>
                                            <option value="300">سبک (300)</option>
                                            <option value="400">معمولی (400)</option>
                                            <option value="500">متوسط (500)</option>
                                            <option value="600">نیمه‌ضخیم (600)</option>
                                            <option value="700">ضخیم (700)</option>
                                            <option value="800">خیلی ضخیم (800)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[10px] text-slate-500 mb-1">فاصله خطوط</p>
                                        <input type="number" min="1" max="4" step="0.1"
                                            :value="getCascadedValue('lineHeight', '')"
                                            @input.debounce.300ms="updateStyle('lineHeight', $event.target.value === '' ? '' : $event.target.value)"
                                            placeholder="پیش‌فرض"
                                            class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-slate-500 mb-1">فاصله حروف (px)</p>
                                        <input type="number" min="-5" max="20" step="0.5"
                                            :value="getCascadedValue('letterSpacing', '')"
                                            @input.debounce.300ms="updateStyle('letterSpacing', $event.target.value === '' ? '' : $event.target.value)"
                                            placeholder="پیش‌فرض"
                                            class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 mb-1">تراز متن</p>
                                    <div class="flex gap-1">
                                        <template x-for="alignment in [['right','←','راست'],['center','↔','وسط'],['left','→','چپ'],['justify','≡','کامل']]" :key="alignment[0]">
                                            <button @click="updateStyle('textAlign', getCascadedValue('textAlign') === alignment[0] ? '' : alignment[0])"
                                                :class="getCascadedValue('textAlign') === alignment[0] ? 'bg-indigo-100 border-indigo-400 text-indigo-700' : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
                                                class="flex-1 py-1.5 rounded-lg border text-xs font-mono transition" :title="alignment[2]" x-text="alignment[1]"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section: Colors --}}
                        <div class="border-b border-slate-100" x-show="genericStyleVisible('colors')">
                            <button @click="styleSection.colors = !styleSection.colors"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <span>رنگ‌ها</span>
                                <svg :class="styleSection.colors ? 'rotate-180' : ''" class="w-3 h-3 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="styleSection.colors" class="px-4 pb-4 space-y-3">
                                <div>
                                    <p class="text-[10px] text-slate-500 mb-1 flex items-center gap-1">
                                        رنگ پس‌زمینه
                                        <span x-show="hasResponsiveOverride('bgColor', activeBreakpoint) && activeBreakpoint !== 'desktop'" class="w-1.5 h-1.5 bg-amber-400 rounded-full inline-block"></span>
                                    </p>
                                    <div class="flex items-center gap-1.5">
                                        <input type="color" :value="getCascadedValue('bgColor', '#ffffff')" @change="updateStyle('bgColor', $event.target.value)" class="w-8 h-7 rounded border border-slate-200 cursor-pointer p-0.5 shrink-0">
                                        <input type="text" :value="getCascadedValue('bgColor', '')" @input.debounce.300ms="updateStyle('bgColor', $event.target.value)" placeholder="بدون رنگ" class="flex-1 text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300 font-mono">
                                        <button x-show="getCascadedValue('bgColor')" @click="updateStyle('bgColor', '')" class="text-slate-400 hover:text-red-500 shrink-0">×</button>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 mb-1 flex items-center gap-1">
                                        رنگ متن
                                        <span x-show="hasResponsiveOverride('textColor', activeBreakpoint) && activeBreakpoint !== 'desktop'" class="w-1.5 h-1.5 bg-amber-400 rounded-full inline-block"></span>
                                    </p>
                                    <div class="flex items-center gap-1.5">
                                        <input type="color" :value="getCascadedValue('textColor', '#1f2937')" @change="updateStyle('textColor', $event.target.value)" class="w-8 h-7 rounded border border-slate-200 cursor-pointer p-0.5 shrink-0">
                                        <input type="text" :value="getCascadedValue('textColor', '')" @input.debounce.300ms="updateStyle('textColor', $event.target.value)" placeholder="بدون رنگ" class="flex-1 text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300 font-mono">
                                        <button x-show="getCascadedValue('textColor')" @click="updateStyle('textColor', '')" class="text-slate-400 hover:text-red-500 shrink-0">×</button>
                                    </div>
                                </div>
                                {{-- Gradient toggle (desktop only) --}}
                                <div x-show="activeBreakpoint === 'desktop'">
                                    <label class="flex items-center gap-2 cursor-pointer mb-2">
                                        <input type="checkbox" :checked="!!(selectedBlock?.style?.bgGradientFrom)" @change="toggleGradient($event.target.checked)" class="rounded text-indigo-600">
                                        <span class="text-[10px] text-slate-600 font-semibold">گرادیانت پس‌زمینه</span>
                                    </label>
                                    <div x-show="selectedBlock?.style?.bgGradientFrom || selectedBlock?.style?.bgGradientTo" class="space-y-2">
                                        <div class="flex items-center gap-1.5">
                                            <input type="color" :value="selectedBlock?.style?.bgGradientFrom || '#4f46e5'" @change="updateStyle('bgGradientFrom', $event.target.value)" class="w-8 h-7 rounded border border-slate-200 cursor-pointer p-0.5 shrink-0">
                                            <span class="text-[10px] text-slate-500">شروع</span>
                                            <input type="color" :value="selectedBlock?.style?.bgGradientTo || '#7c3aed'" @change="updateStyle('bgGradientTo', $event.target.value)" class="w-8 h-7 rounded border border-slate-200 cursor-pointer p-0.5 shrink-0">
                                            <span class="text-[10px] text-slate-500">پایان</span>
                                        </div>
                                        <select :value="selectedBlock?.style?.bgGradientDir || 'to bottom'" @change="updateStyle('bgGradientDir', $event.target.value)" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                            <option value="to bottom">بالا به پایین</option>
                                            <option value="to top">پایین به بالا</option>
                                            <option value="to right">راست به چپ</option>
                                            <option value="to left">چپ به راست</option>
                                            <option value="135deg">مورب ↗</option>
                                            <option value="45deg">مورب ↖</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section: Spacing --}}
                        <div class="border-b border-slate-100" x-show="genericStyleVisible('spacing')">
                            <button @click="styleSection.spacing = !styleSection.spacing"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <span>فاصله‌گذاری</span>
                                <svg :class="styleSection.spacing ? 'rotate-180' : ''" class="w-3 h-3 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="styleSection.spacing" class="px-4 pb-4 space-y-3">
                                <p class="text-[10px] text-slate-500">پدینگ (px)</p>
                                <div class="grid grid-cols-4 gap-1.5">
                                    <template x-for="padding in [['paddingTop','بالا'],['paddingRight','راست'],['paddingBottom','پایین'],['paddingLeft','چپ']]" :key="padding[0]">
                                        <div>
                                            <p class="text-[9px] text-slate-400 text-center mb-1" x-text="padding[1]"></p>
                                            <input type="number" min="0" max="300"
                                                :value="getCascadedValue(padding[0], '')"
                                                @input.debounce.300ms="updateStyle(padding[0], $event.target.value === '' ? '' : Number($event.target.value))"
                                                class="w-full text-xs text-center px-1 py-1.5 rounded-lg border focus:outline-none focus:ring-1 focus:ring-indigo-300"
                                                :class="hasResponsiveOverride(padding[0], activeBreakpoint) && activeBreakpoint !== 'desktop' ? 'border-amber-300 bg-amber-50' : 'border-slate-200'">
                                        </div>
                                    </template>
                                </div>
                                <p class="text-[10px] text-slate-500">مارجین (px)</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[9px] text-slate-400 mb-1">بالا</p>
                                        <input type="number" min="-100" max="300" :value="getCascadedValue('marginTop', '')" @input.debounce.300ms="updateStyle('marginTop', $event.target.value === '' ? '' : Number($event.target.value))" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    </div>
                                    <div>
                                        <p class="text-[9px] text-slate-400 mb-1">پایین</p>
                                        <input type="number" min="-100" max="300" :value="getCascadedValue('marginBottom', '')" @input.debounce.300ms="updateStyle('marginBottom', $event.target.value === '' ? '' : Number($event.target.value))" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section: Border --}}
                        <div class="border-b border-slate-100" x-show="genericStyleVisible('border')">
                            <button @click="styleSection.border = !styleSection.border"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <span>حاشیه</span>
                                <svg :class="styleSection.border ? 'rotate-180' : ''" class="w-3 h-3 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="styleSection.border" class="px-4 pb-4 space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[10px] text-slate-500 mb-1">ضخامت حاشیه (px)</p>
                                        <input type="number" min="0" max="20" :value="getCascadedValue('borderWidth', '')" @input.debounce.300ms="updateStyle('borderWidth', $event.target.value === '' ? '' : Number($event.target.value))" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-slate-500 mb-1">نوع حاشیه</p>
                                        <select :value="getCascadedValue('borderStyle', 'solid')" @change="updateStyle('borderStyle', $event.target.value)" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                            <option value="solid">solid</option>
                                            <option value="dashed">dashed</option>
                                            <option value="dotted">dotted</option>
                                            <option value="double">double</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 mb-1">رنگ حاشیه</p>
                                    <div class="flex items-center gap-1.5">
                                        <input type="color" :value="getCascadedValue('borderColor', '#e5e7eb')" @change="updateStyle('borderColor', $event.target.value)" class="w-8 h-7 rounded border border-slate-200 cursor-pointer p-0.5 shrink-0">
                                        <input type="text" :value="getCascadedValue('borderColor', '')" @input.debounce.300ms="updateStyle('borderColor', $event.target.value)" placeholder="#e5e7eb" class="flex-1 text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300 font-mono">
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 mb-1">گوشه‌های گرد (px)</p>
                                    <input type="number" min="0" max="100" :value="getCascadedValue('borderRadius', '')" @input.debounce.300ms="updateStyle('borderRadius', $event.target.value === '' ? '' : Number($event.target.value))" placeholder="۰" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                </div>
                            </div>
                        </div>

                        {{-- Section: Shadow --}}
                        <div class="border-b border-slate-100" x-show="genericStyleVisible('shadow')">
                            <button @click="styleSection.shadow = !styleSection.shadow"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <span>سایه</span>
                                <svg :class="styleSection.shadow ? 'rotate-180' : ''" class="w-3 h-3 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="styleSection.shadow" class="px-4 pb-4">
                                <div class="flex flex-wrap gap-1.5 mb-3">
                                    <template x-for="shadowOption in [['none','هیچ'],['sm','کوچک'],['md','متوسط'],['lg','بزرگ'],['xl','خیلی بزرگ']]" :key="shadowOption[0]">
                                        <button @click="updateStyle('shadow', getCascadedValue('shadow') === shadowOption[0] ? '' : shadowOption[0])"
                                            :class="getCascadedValue('shadow') === shadowOption[0] ? 'bg-indigo-100 border-indigo-400 text-indigo-700 font-semibold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                            class="px-2.5 py-1 rounded-lg border text-xs transition" x-text="shadowOption[1]"></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Section: Opacity --}}
                        <div class="border-b border-slate-100 px-4 py-3" x-show="genericStyleVisible('opacity')">
                            <p class="text-[10px] text-slate-500 mb-2">شفافیت</p>
                            <div class="flex items-center gap-2">
                                <input type="range" min="0.1" max="1" step="0.05" :value="getCascadedValue('opacity', 1)" @input.debounce.300ms="updateStyle('opacity', Number($event.target.value))" class="flex-1">
                                <span class="text-xs text-slate-500 w-8 text-left" x-text="Math.round(getCascadedValue('opacity', 1) * 100) + '%'"></span>
                            </div>
                        </div>

                        <div class="px-4 py-3" x-show="CanvasControls.hiddenStyleSections(selectedEditorCapabilities).length === 0">
                            <button @click="resetBlockStyle()" class="w-full text-xs py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-red-500 transition"
                                x-text="activeBreakpoint === 'desktop' ? 'پاک کردن همه استایل‌ها' : 'پاک کردن override ' + (activeBreakpoint === 'tablet' ? 'تبلت' : 'موبایل')">
                            </button>
                        </div>
                    </div>

                    {{-- ── ADVANCED TAB ───────────────────────────────────────── --}}
                    <div x-show="selectedTab === 'advanced'" class="px-4 py-4 space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">کلاس CSS سفارشی</label>
                            <input type="text"
                                :value="selectedBlock?.style?.customClass ?? ''"
                                @input.debounce.300ms="updateStyle('customClass', $event.target.value)"
                                placeholder="مثال: my-hero featured-section"
                                class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-2">نمایش در دستگاه‌ها</label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" :checked="selectedBlock?.style?.hideDesktop" @change="updateStyle('hideDesktop', $event.target.checked || '')" class="rounded text-indigo-600">
                                    <span class="text-xs text-slate-700">پنهان در دسکتاپ</span>
                                </label>
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" :checked="selectedBlock?.style?.hideTablet" @change="updateStyle('hideTablet', $event.target.checked || '')" class="rounded text-indigo-600">
                                    <span class="text-xs text-slate-700">پنهان در تبلت</span>
                                </label>
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" :checked="selectedBlock?.style?.hideMobile" @change="updateStyle('hideMobile', $event.target.checked || '')" class="rounded text-indigo-600">
                                    <span class="text-xs text-slate-700">پنهان در موبایل</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">انیمیشن ورود</label>
                            <select :value="selectedBlock?.style?.animation ?? 'none'"
                                @change="updateStyle('animation', $event.target.value === 'none' ? '' : $event.target.value)"
                                class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300 mb-2">
                                <option value="none">بدون انیمیشن</option>
                                <option value="fade-in">محو شدن (Fade In)</option>
                                <option value="slide-up">از پایین (Slide Up)</option>
                                <option value="slide-right">از راست (Slide Right)</option>
                                <option value="zoom-in">بزرگ‌شدن (Zoom In)</option>
                                <option value="bounce-in">جهش (Bounce In)</option>
                            </select>
                            <template x-if="selectedBlock?.style?.animation && selectedBlock.style.animation !== 'none'">
                                <div>
                                    <label class="block text-[10px] text-slate-500 mb-1">مدت زمان انیمیشن (ms)</label>
                                    <input type="number" min="200" max="3000" step="100"
                                        :value="selectedBlock?.style?.animationDuration ?? 600"
                                        @input.debounce.300ms="updateStyle('animationDuration', Number($event.target.value))"
                                        class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- ── CONTENT TAB ────────────────────────────────────────── --}}
                    {{-- ADR-082: this loop renders the active tab's own schema
                         fields (CanvasControls.visibleFields: panel, condition,
                         state), so on Style / Advanced it shows above the
                         generic sections and only when the widget has fields there. --}}
                    <div x-show="selectedTab === 'content' || panelHasFields(selectedTab)"
                        :class="selectedTab === 'content' ? '' : 'order-first border-b border-slate-100'"
                        class="px-4 py-4 space-y-4" :data-testid="'tb-panel-fields-' + selectedTab">
                        <template x-for="(schemaDef, propKey) in panelFields(selectedTab)" :key="propKey">
                            <div :data-testid="'tb-field-' + propKey">
                                {{-- Phase 3G-B-6 (§30): every control below now carries a real
                                     programmatic id<->label association (:id / :for keyed by
                                     propKey) — fixed once, generically, for built-in AND
                                     Extension controls alike, since both are rendered by this
                                     exact same loop. --}}
                                {{-- ADR-076: a field's schema `group` opens a sidebar section
                                     where it first appears, so a widget organises its own
                                     Inspector without editor changes. --}}
                                <template x-if="fieldGroupStart(propKey)">
                                    <p class="mt-2 mb-3 pb-1 border-b border-slate-100 text-xs font-bold tracking-wide text-indigo-700" x-text="schemaDef.group" :data-testid="'tb-field-group-' + propKey"></p>
                                </template>
                                {{-- ADR-082: Normal / Hover / Active above a state_group --}}
                                <template x-if="fieldStateStart(propKey)">
                                    @include('theme-runtime::canvas.control-states')
                                </template>
                                <div class="flex items-center mb-1.5">
                                    <label class="block text-xs font-semibold text-slate-600" :for="'ctrl-' + propKey" x-text="schemaDef.label || propKey"></label>
                                    @include('theme-runtime::canvas.control-devices')
                                </div>

                                {{-- ADR-082: range, responsive number/select, spacing,
                                     typography and shadow — the shared controls. --}}
                                @include('theme-runtime::canvas.control-field')

                                {{-- image (TB3-07) --}}
                                <template x-if="schemaDef.type === 'image'">
                                    <div>
                                        <div x-show="selectedBlock.props[propKey] && schemaDef.media_kind !== 'video'" class="mb-2 relative rounded-lg overflow-hidden border border-slate-200 bg-slate-50" style="height:80px;">
                                            <img :src="selectedBlock.props[propKey]" class="w-full h-full object-cover">
                                            <button @click="updateProp(propKey, '')" class="absolute top-1 left-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">×</button>
                                        </div>
                                        <div class="flex gap-1.5">
                                            <input type="text"
                                                :id="'ctrl-' + propKey"
                                                :value="selectedBlock.props[propKey] ?? ''"
                                                @input.debounce.300ms="updateProp(propKey, $event.target.value)"
                                                :placeholder="schemaDef.media_kind === 'video' ? 'آدرس URL ویدیو' : 'آدرس URL تصویر'"
                                                class="flex-1 text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                            <button @click="openMediaPicker(propKey)"
                                                class="flex items-center justify-center w-8 h-8 rounded-lg border border-indigo-300 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition shrink-0" title="انتخاب از کتابخانه">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                {{-- string; `motion` is an asset id here — the visual motion
                                     picker needs a tenant's own permitted assets (customer-site). --}}
                                <template x-if="schemaDef.type === 'string' || schemaDef.type === 'motion'">
                                    <input type="text"
                                        :id="'ctrl-' + propKey"
                                        :value="selectedBlock.props[propKey] ?? schemaDef.default ?? ''"
                                        @input.debounce.300ms="updateProp(propKey, $event.target.value)"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition"
                                        :placeholder="schemaDef.default ?? ''">
                                </template>

                                {{-- motion_params (ADR-079): per-asset runtime parameters come
                                     from a site's own runtime manifest, so they are edited in
                                     the site's Canvas; a master template keeps the asset's
                                     defaults. --}}
                                <template x-if="schemaDef.type === 'motion_params'">
                                    <p class="text-xs text-slate-500 leading-6 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2" data-testid="tb-motion-params-hint">
                                        متن‌ها، رنگ‌ها و تنظیمات انیمیشن هر موشن در ادیتور سایت مشتری و بر اساس موشن انتخاب‌شده تنظیم می‌شوند؛ قالب مادر از مقادیر پیش‌فرض موشن استفاده می‌کند.
                                    </p>
                                </template>

                                {{-- icon (Phase 0.6, ADR-065): the shared searchable SVG icon
                                     picker; a legacy emoji/text value keeps rendering as-is. --}}
                                <template x-if="schemaDef.type === 'icon'">
                                    @include('theme-runtime::canvas.icon-picker', [
                                        'value' => "selectedBlock.props[propKey] ?? schemaDef.default ?? ''",
                                        'set' => 'updateProp(propKey, __icon)',
                                        'id' => 'propKey',
                                        'label' => 'schemaDef.label || propKey',
                                    ])
                                </template>

                                {{-- number --}}
                                <template x-if="schemaDef.type === 'number' && !ctlIsResponsive(propKey)">
                                    <input type="number"
                                        :id="'ctrl-' + propKey"
                                        :value="selectedBlock.props[propKey] ?? schemaDef.default ?? 0"
                                        @input.debounce.300ms="updateProp(propKey, Number($event.target.value))"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition"
                                        :min="schemaDef.min ?? 0" :max="schemaDef.max ?? null" :step="schemaDef.step ?? 'any'">
                                </template>

                                {{-- boolean --}}
                                <template x-if="schemaDef.type === 'boolean'">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <div class="relative">
                                            <input type="checkbox" :id="'ctrl-' + propKey" :checked="ctlChecked(propKey)" @change="updateProp(propKey, $event.target.checked)" class="sr-only peer">
                                            <div class="w-9 h-5 bg-slate-200 peer-checked:bg-indigo-500 rounded-full transition"></div>
                                            <div class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-full rtl:peer-checked:-translate-x-full transition-transform"></div>
                                        </div>
                                        <span class="text-sm text-slate-700" x-text="ctlChecked(propKey) ? 'فعال' : 'غیرفعال'"></span>
                                    </label>
                                </template>

                                {{-- select (plain dropdown unless the schema asks for a dedicated `ui`) --}}
                                <template x-if="schemaDef.type === 'select' && !schemaDef.ui && !ctlIsResponsive(propKey)">
                                    <select :id="'ctrl-' + propKey" :value="selectedBlock.props[propKey] ?? schemaDef.default ?? ''" @change="updateProp(propKey, $event.target.value)"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition">
                                        <template x-for="opt in selectOptions(schemaDef)" :key="opt.value">
                                            <option :value="opt.value" :selected="String(selectedBlock.props[propKey] ?? schemaDef.default ?? '') === opt.value" x-text="opt.label"></option>
                                        </template>
                                    </select>
                                </template>

                                {{-- ADR-076: segmented control — a select whose schema declares
                                     ui: 'segmented' (alignment, icon colour mode). One button per
                                     schema option, so the control and the allowlist agree. --}}
                                <template x-if="schemaDef.type === 'select' && schemaDef.ui === 'segmented' && !ctlIsResponsive(propKey)">
                                    <div class="flex gap-1 p-1 rounded-lg bg-slate-100" role="radiogroup" :aria-label="schemaDef.label || propKey" :data-testid="'tb-segmented-' + propKey">
                                        <template x-for="opt in selectOptions(schemaDef)" :key="opt.value">
                                            <button type="button" role="radio"
                                                :aria-checked="String(selectedBlock.props[propKey] ?? schemaDef.default ?? '') === opt.value ? 'true' : 'false'"
                                                @click="updateProp(propKey, opt.value)"
                                                :class="String(selectedBlock.props[propKey] ?? schemaDef.default ?? '') === opt.value ? 'bg-white text-indigo-700 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-800'"
                                                class="flex-1 px-2 py-1.5 rounded-md text-xs leading-tight transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                                                x-text="opt.label" :data-testid="'tb-segmented-' + propKey + '-' + opt.value"></button>
                                        </template>
                                    </div>
                                </template>

                                {{-- Visual column picker — a select whose schema declares
                                     ui: 'column_picker'. Buttons are generated from the
                                     schema's own options, so the picker and the allowlist
                                     can never disagree. --}}
                                <template x-if="schemaDef.type === 'select' && schemaDef.ui === 'column_picker' && !ctlIsResponsive(propKey)">
                                    <div class="grid grid-cols-2 gap-2 mt-1" role="radiogroup" :aria-label="schemaDef.label || propKey" :data-testid="'tb-column-picker-' + propKey">
                                        <template x-for="opt in selectOptions(schemaDef)" :key="opt.value">
                                            <button type="button" role="radio"
                                                :aria-checked="String(selectedBlock.props[propKey] ?? schemaDef.default ?? '') === opt.value"
                                                @click="updateProp(propKey, opt.value)"
                                                :title="opt.label" :aria-label="opt.label"
                                                :class="String(selectedBlock.props[propKey] ?? schemaDef.default ?? '') === opt.value ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-200' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                                                class="px-2.5 py-2 rounded-lg border text-xs font-medium flex items-center justify-center gap-1.5 transition">
                                                <span class="flex items-center gap-0.5" aria-hidden="true">
                                                    <template x-for="n in opt.value.split(',').length" :key="n">
                                                        <span class="h-2.5 rounded-sm bg-current opacity-70" :class="opt.value.split(',').length > 2 ? 'w-1' : 'w-1.5'"></span>
                                                    </template>
                                                </span>
                                                <span x-text="faDigits(opt.value.split(',').length) + ' ستون'"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                {{-- Schema-driven repeater, nested to the registry depth cap
                                     (Phase 0.1 / 0.2b) — see the partial for the contract. --}}
                                <template x-if="schemaDef.type === 'repeater'">
                                    <div>
                                        @include('admin.theme-builder.partials.canvas-repeater', ['d' => 0, 'list' => '[propKey]', 'def' => 'schemaDef'])
                                    </div>
                                </template>

                                {{-- Header component order — visual Drag & Drop sortable list --}}
                                <template x-if="schemaDef.type === 'array' && propKey === 'header_order'">
                                    <div x-data="{
                                        order: JSON.parse(JSON.stringify(selectedBlock.props.header_order ?? [])),
                                        available: ['logo','menu','search','social','phone','cta','cart','account','dark_toggle','burger'],
                                        dragging: null,
                                        labelFor: (k) => ({
                                            'logo': 'لوگو',
                                            'menu': 'منو',
                                            'search': 'جستجو',
                                            'social': 'شبکه‌های اجتماعی',
                                            'phone': 'تلفن تماس',
                                            'cta': 'دکمه اقدام (CTA)',
                                            'cart': 'سبد خرید',
                                            'account': 'حساب کاربری',
                                            'dark_toggle': 'تاریک/روشن',
                                            'burger': 'منوی همبرگری'
                                        })[k] || k
                                    }">
                                        <label class="block text-xs font-semibold text-slate-600 mb-1.5" :for="'ctrl-' + propKey" x-text="schemaDef.label || propKey"></label>
                                        <p class="text-[10px] text-slate-500 mb-3">اجزای هدر را با نگه‌داشتن و کشیدن جابه‌جا کنید.</p>
                                        <div class="space-y-1.5">
                                            <template x-for="(key, idx) in order" :key="key + '-' + idx">
                                                <div
                                                    draggable="true"
                                                    @dragstart.prevent="dragging = key; $event.dataTransfer.setData('text/plain', key); $event.dataTransfer.effectAllowed = 'move';"
                                                    @dragover.prevent="dragOverIdx = idx"
                                                    @drop.prevent="
                                                        const from = order.indexOf(dragging);
                                                        const to = idx;
                                                        if (from === to) return;
                                                        order.splice(from, 1);
                                                        order.splice(to, 0, dragging);
                                                        updateProp(propKey, [...order]);
                                                        dragging = null;
                                                    "
                                                    @dragend="dragging = null"
                                                    :class="dragging === key ? 'opacity-50' : ''"
                                                    class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white hover:bg-indigo-50 hover:border-indigo-200 transition cursor-grab active:cursor-grabbing">
                                                    <button type="button" class="flex-shrink-0 text-indigo-600 hover:text-indigo-800 font-mono font-bold text-[10px]">⠿</button>
                                                    <span class="flex-1 text-xs text-slate-700" x-text="labelFor(key)"></span>
                                                    <button type="button" @click="order.splice(idx, 1); updateProp(propKey, [...order]);" class="text-[10px] text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded p-1 transition" title="حذف از ترتیب">×</button>
                                                </div>
                                            </template>
                                            <template x-for="key in available.filter(k => !order.includes(k))" :key="key">
                                                <button type="button" @click="order.push(key); updateProp(propKey, [...order]);" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-500 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 transition">
                                                    <span class="text-indigo-600 font-mono font-bold text-[10px]">+</span>
                                                    <span x-text="labelFor(key)"></span>
                                                </button>
                                            </template>
                                        </div>
                                        <template x-if="order.length === 0">
                                            <p class="text-[10px] text-slate-400 italic">هیچ חלקی انتخاب نشده است.</p>
                                        </template>
                                    </div>
                                </template>

                                {{-- Schema-less list props (type: array, no `fields`): guarded raw
                                     JSON editor. An invalid draft is never written to the block. --}}
                                <template x-if="schemaDef.type === 'array' && propKey !== 'header_order'">
                                    <div x-data="{ jsonDraft: null, jsonDraftFor: null, jsonError: '' }">
                                        <textarea :id="'ctrl-' + propKey" dir="ltr" rows="5" spellcheck="false"
                                            :value="(jsonDraftFor === selectedBlock.id && jsonDraft !== null) ? jsonDraft : JSON.stringify(selectedBlock.props[propKey] ?? schemaDef.default ?? [], null, 2)"
                                            @input="
                                                jsonDraft = $event.target.value; jsonDraftFor = selectedBlock.id;
                                                try {
                                                    const parsed = JSON.parse(jsonDraft);
                                                    if (!Array.isArray(parsed)) throw new Error('not a list');
                                                    jsonError = '';
                                                    updateProp(propKey, parsed);
                                                } catch (_) { jsonError = 'JSON نامعتبر است؛ تا اصلاح نشود ذخیره نمی‌شود.'; }
                                            "
                                            @blur="if (!jsonError) { jsonDraft = null; jsonDraftFor = null; }"
                                            class="w-full text-[11px] leading-5 px-2.5 py-2 rounded-lg border bg-white font-mono resize-y focus:outline-none focus:ring-2"
                                            :class="jsonError ? 'border-rose-300 focus:ring-rose-200' : 'border-slate-200 focus:ring-indigo-300'"></textarea>
                                        <p class="text-[10px] mt-1" :class="jsonError ? 'text-rose-600' : 'text-slate-400'" x-text="jsonError || 'ویرایش پیشرفته (فهرست JSON)'"></p>
                                    </div>
                                </template>

                                {{-- color --}}
                                {{-- Phase 0.5 (ADR-064): theme swatches (stored as var(--tb-…)
                                     so the block follows the theme), opacity (rgba() or a
                                     color-mix() of the swatch) and explicit transparent. The value
                                     model is the shared theme-runtime CanvasColor. --}}
                                <template x-if="schemaDef.type === 'color'">
                                    <div class="space-y-2" :data-testid="'tb-color-' + propKey" x-data="{ get cv() { return selectedBlock.props[propKey] ?? schemaDef.default ?? ''; } }">
                                        <div class="flex items-center gap-2">
                                            <span class="relative w-10 h-10 rounded-lg border border-slate-200 overflow-hidden shrink-0" style="background-image:conic-gradient(#e2e8f0 25%,#fff 0 50%,#e2e8f0 0 75%,#fff 0);background-size:10px 10px;" aria-hidden="true">
                                                <span class="absolute inset-0" :style="'background:' + colorPreview(cv)" :data-testid="'tb-color-preview-' + propKey"></span>
                                            </span>
                                            <input type="color" :id="'ctrl-' + propKey" :value="colorInputHex(cv)" @input="setColorBase(propKey, $event.target.value)" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer p-0.5" aria-label="انتخاب رنگ دلخواه">
                                            <input type="text" dir="ltr" :value="cv" @input.debounce.300ms="updateProp(propKey, $event.target.value)" class="flex-1 min-w-0 text-xs px-2.5 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 font-mono" placeholder="#000000" aria-label="مقدار رنگ" :data-testid="'tb-color-value-' + propKey">
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="رنگ‌های قالب">
                                            <template x-for="t in colorPalette()" :key="t.token">
                                                <button type="button" @click="setColorSwatch(propKey, t.token)" :title="t.label" :aria-label="t.label" :aria-pressed="colorParse(cv).token === t.token ? 'true' : 'false'"
                                                    :class="colorParse(cv).token === t.token ? 'ring-2 ring-indigo-500 ring-offset-1' : 'ring-1 ring-slate-200'"
                                                    class="w-6 h-6 rounded-full border-2 border-white shadow hover:scale-110 transition" :style="'background:' + t.value" :data-testid="'tb-color-swatch-' + propKey + '-' + t.token"></button>
                                            </template>
                                            <button type="button" @click="updateProp(propKey, 'transparent')" :aria-pressed="colorParse(cv).kind === 'transparent' ? 'true' : 'false'"
                                                :class="colorParse(cv).kind === 'transparent' ? 'ring-2 ring-indigo-500' : 'ring-1 ring-slate-200'"
                                                class="px-2 h-6 rounded-full text-[11px] text-slate-600 bg-white" :data-testid="'tb-color-transparent-' + propKey">بی‌رنگ</button>
                                        </div>
                                        <label class="flex items-center gap-2 text-[11px] text-slate-600">
                                            <span class="shrink-0">شفافیت</span>
                                            <input type="range" min="0" max="100" step="5" class="flex-1 accent-indigo-600" :value="colorParse(cv).alpha" @input="setColorAlpha(propKey, Number($event.target.value))"
                                                :disabled="!['token', 'hex', 'rgba'].includes(colorParse(cv).kind)" :data-testid="'tb-color-alpha-' + propKey" aria-label="میزان شفافیت رنگ">
                                            <span class="w-9 text-left tabular-nums" x-text="(colorParse(cv).kind === 'transparent' ? 0 : colorParse(cv).alpha) + '٪'"></span>
                                        </label>
                                    </div>
                                </template>

                                {{-- textarea; also multi-line `text` and `rich_text` (sanitized
                                     at render time), which had no control at all --}}
                                <template x-if="schemaDef.type === 'textarea' || schemaDef.type === 'text' || schemaDef.type === 'rich_text'">
                                    <textarea :id="'ctrl-' + propKey" :value="selectedBlock.props[propKey] ?? schemaDef.default ?? ''" @input.debounce.300ms="updateProp(propKey, $event.target.value)" rows="3"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition resize-y"
                                        :placeholder="schemaDef.default ?? ''"></textarea>
                                </template>

                                {{-- Phase 2B-R: canonical link control. The value stays a legacy
                                     plain URL string (registry legacy-string compatibility) unless it
                                     was authored structured, in which case url/target edits preserve
                                     the object shape. Raw URL strings are scheme-gated server-side by
                                     HtmlSanitizer::isValidUrl at render time. --}}
                                <template x-if="schemaDef.type === 'link' || schemaDef.control === 'link'">
                                    <div class="space-y-1.5">
                                        <input type="text"
                                            :id="'ctrl-' + propKey"
                                            :value="typeof (selectedBlock.props[propKey]) === 'string' ? selectedBlock.props[propKey] : (selectedBlock.props[propKey]?.url ?? '')"
                                            @input.debounce.300ms="updateLinkProp(propKey, $event.target.value)"
                                            class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition"
                                            placeholder="https:// …">
                                        <template x-if="selectedBlock.props[propKey] && typeof selectedBlock.props[propKey] === 'object'">
                                            <select :value="selectedBlock.props[propKey]?.target ?? '_self'"
                                                @change="updateLinkTarget(propKey, $event.target.value)"
                                                class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 bg-white">
                                                <option value="_self">همان صفحه</option>
                                                <option value="_blank">تب جدید</option>
                                            </select>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div x-show="selectedTab === 'content' && Object.keys(selectedSchema).length === 0 && !isContainerType(selectedBlock?.type)" class="text-center py-6">
                            <p class="text-xs text-slate-400">این بلوک تنظیماتی ندارد</p>
                        </div>

                        {{-- TB4: Container Children Panel ──────────────────── --}}
                        <template x-if="selectedTab === 'content' && isContainerType(selectedBlock?.type)">
                            <div class="mt-4 pt-4 border-t border-slate-200">
                                <div class="flex items-center justify-between px-1 mb-2">
                                    <span class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                                        <svg class="w-3 h-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                        فرزندان (<span x-text="selectedBlock?.children?.length ?? 0"></span>)
                                    </span>
                                    <button @click="showAddChildModal = true"
                                        class="text-xs px-2 py-1 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 font-medium transition flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                        افزودن
                                    </button>
                                </div>
                                <div class="space-y-1 max-h-48 overflow-y-auto">
                                    <template x-for="(child, ci) in (selectedBlock?.children ?? [])" :key="child.id">
                                        <div class="flex items-center gap-1 px-2 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs group">
                                            <span :class="blockCategoryColor(child.type)" class="w-1.5 h-1.5 rounded-full shrink-0"></span>
                                            <span class="flex-1 truncate text-slate-700" x-text="child.props?.label ? (blockLabel(child.type) + ': ' + child.props.label) : blockLabel(child.type)"></span>
                                            <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                                                <button @click="selectBlock([...selectedPath, ci])"
                                                    class="w-5 h-5 flex items-center justify-center rounded text-indigo-500 hover:bg-indigo-50" title="ویرایش">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                                <button @click="moveChildUp(ci)" :disabled="ci === 0"
                                                    class="w-5 h-5 flex items-center justify-center rounded text-slate-400 hover:bg-slate-100 disabled:opacity-30" title="بالاتر">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                                </button>
                                                <button @click="moveChildDown(ci)" :disabled="ci >= (selectedBlock?.children?.length ?? 0) - 1"
                                                    class="w-5 h-5 flex items-center justify-center rounded text-slate-400 hover:bg-slate-100 disabled:opacity-30" title="پایین‌تر">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                                <button @click="removeChild(ci)"
                                                    class="w-5 h-5 flex items-center justify-center rounded text-red-400 hover:bg-red-50" title="حذف">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="!(selectedBlock?.children?.length)" class="text-center py-4 text-slate-400 text-xs">
                                        هیچ بلوک فرزندی وجود ندارد
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    </div>{{-- /tb-inspector-scroll --}}

                </div>
            </template>

            {{-- TB4: Add Child Block Modal --}}
            <div x-show="showAddChildModal" x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div @click.outside="showAddChildModal = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-5">
                    <h3 class="text-sm font-bold text-slate-800 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        افزودن بلوک فرزند به <span class="text-indigo-600" x-text="blockLabel(selectedBlock?.type)"></span>
                    </h3>
                    <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                        @foreach($orderedCats as $catKey)
                            @if(!isset($byCategory[$catKey])) @continue @endif
                            @foreach($byCategory[$catKey] as $blockType => $blockDef)
                                <button
                                    @click="addChildBlock('{{ $blockType }}'); showAddChildModal = false"
                                    class="flex items-center gap-2 px-2 py-2 rounded-lg border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50 text-xs text-right transition">
                                    <span class="text-base leading-none shrink-0">
                                        @switch($blockType)
                                            @case('hero') 🌟 @break @case('section') 📦 @break
                                            @case('flex_container') 📐 @break @case('grid') 🔲 @break
                                            @case('columns') ⬜ @break @case('heading') 🔠 @break
                                            @case('text') 📄 @break @case('image') 🖼️ @break
                                            @case('button') 🔘 @break @case('cta') 📢 @break
                                            @default 🧩
                                        @endswitch
                                    </span>
                                    <span class="font-medium text-slate-700 leading-tight">{{ $blockDef['name'] }}</span>
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                    <button @click="showAddChildModal = false" class="w-full mt-3 text-xs py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition">انصراف</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TB3-07: MEDIA PICKER MODAL
    ═══════════════════════════════════════════════════════ --}}
    <div x-show="mediaModal.show" x-transition.opacity class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
        <div @click.outside="mediaModal.show = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col" style="max-height:85vh;">
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 shrink-0">
                <h3 class="font-bold text-slate-800">مدیر رسانه</h3>
                <button @click="mediaModal.show = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">×</button>
            </div>
            {{-- Tabs --}}
            <div class="flex border-b border-slate-100 shrink-0">
                <button @click="mediaModal.tab = 'library'" :class="mediaModal.tab === 'library' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-slate-500'" class="flex-1 py-2.5 text-sm text-center transition">کتابخانه</button>
                <button @click="mediaModal.tab = 'upload'; mediaModal.uploadDone = false" :class="mediaModal.tab === 'upload' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-slate-500'" class="flex-1 py-2.5 text-sm text-center transition">آپلود</button>
            </div>
            {{-- Library tab --}}
            <div x-show="mediaModal.tab === 'library'" class="flex-1 overflow-y-auto p-4">
                <div x-show="mediaModal.loading" class="flex items-center justify-center py-12 text-slate-400">
                    <svg class="w-5 h-5 animate-spin ml-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    در حال بارگذاری...
                </div>
                <div x-show="!mediaModal.loading && mediaModal.images.length === 0" class="text-center py-12 text-slate-400">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm">کتابخانه خالی است</p>
                    <p class="text-xs mt-1">تصویری آپلود کنید</p>
                </div>
                <div x-show="!mediaModal.loading" class="grid grid-cols-3 gap-3">
                    <template x-for="img in mediaModal.images" :key="img.url">
                        <div class="relative group rounded-lg overflow-hidden border border-slate-200 bg-slate-100 aspect-square cursor-pointer hover:border-indigo-400 transition"
                            @click="selectMedia(img.url)">
                            <img :src="img.url" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
                                <span class="bg-white text-xs font-semibold text-indigo-700 px-2 py-1 rounded-lg shadow">انتخاب</span>
                            </div>
                            <button @click.stop="deleteMedia(img.url)" class="absolute top-1 left-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-red-600">×</button>
                        </div>
                    </template>
                </div>
            </div>
            {{-- Upload tab --}}
            <div x-show="mediaModal.tab === 'upload'" class="flex-1 p-5 flex flex-col items-center justify-center">
                <div x-show="!mediaModal.uploadDone"
                    @dragover.prevent="$el.classList.add('border-indigo-400','bg-indigo-50')"
                    @dragleave="$el.classList.remove('border-indigo-400','bg-indigo-50')"
                    @drop.prevent="handleMediaDrop($event)"
                    class="w-full border-2 border-dashed border-slate-300 rounded-2xl p-10 text-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition"
                    @click="$refs.mediaFileInput.click()">
                    <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <p class="text-sm font-semibold text-slate-600 mb-1">فایل را بکشید اینجا</p>
                    <p class="text-xs text-slate-400">JPG، PNG، WebP، GIF، SVG — حداکثر ۵ مگابایت</p>
                    <div x-show="mediaModal.uploading" class="mt-4">
                        <svg class="w-6 h-6 animate-spin mx-auto text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    </div>
                </div>
                <div x-show="mediaModal.uploadDone" class="text-center">
                    <svg class="w-14 h-14 mx-auto mb-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm font-semibold text-green-700">آپلود موفق</p>
                    <button @click="mediaModal.tab = 'library'; fetchMedia()" class="mt-3 text-xs text-indigo-600 hover:underline">مشاهده در کتابخانه</button>
                </div>
                <input x-ref="mediaFileInput" type="file" accept="image/*" class="hidden" @change="uploadMediaFile($event.target.files[0])">
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TB6: TEMPLATE LIBRARY MODAL
    ═══════════════════════════════════════════════════════ --}}
    <div x-show="showTemplateLibrary" x-transition.opacity class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" style="display:none">
        <div @click.outside="showTemplateLibrary = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col" style="max-height:88vh">
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 shrink-0">
                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zm12 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                    کتابخانه قالب‌ها
                </h3>
                <button @click="showTemplateLibrary = false" class="text-slate-400 hover:text-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            {{-- Filters --}}
            <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-100 shrink-0 bg-slate-50">
                <input type="text" x-model="templateLibrary.search" @input.debounce.400ms="fetchTemplates()"
                    placeholder="جستجو…"
                    class="flex-1 text-sm px-3 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-purple-300">
                <select x-model="templateLibrary.type" @change="fetchTemplates()"
                    class="text-sm px-3 py-1.5 rounded-lg border border-slate-200 focus:outline-none">
                    <option value="">همه انواع</option>
                    <option value="section">بخش</option>
                    <option value="page">صفحه</option>
                    <option value="header">هدر</option>
                    <option value="footer">فوتر</option>
                    <option value="landing_page">لندینگ پیج</option>
                </select>
                <select x-model="templateLibrary.category" @change="fetchTemplates()"
                    class="text-sm px-3 py-1.5 rounded-lg border border-slate-200 focus:outline-none">
                    <option value="">همه دسته‌ها</option>
                    <option value="navigation">ناوبری</option>
                    <option value="hero">هیرو</option>
                    <option value="features">ویژگی‌ها و بنتو</option>
                    <option value="product">محصول</option>
                    <option value="store">فروشگاه</option>
                    <option value="blog">وبلاگ</option>
                    <option value="testimonials">اعتماد و آمار</option>
                    <option value="pricing">قیمت‌گذاری</option>
                    <option value="faq">سؤالات متداول</option>
                    <option value="corporate">شرکتی</option>
                    <option value="contact">تماس</option>
                    <option value="gallery">گالری</option>
                    <option value="team">تیم</option>
                </select>
            </div>
            {{-- Grid --}}
            <div class="flex-1 overflow-y-auto p-5">
                {{-- Loading --}}
                <div x-show="templateLibrary.loading" class="flex items-center justify-center py-16">
                    <svg class="w-8 h-8 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>
                {{-- Empty --}}
                <div x-show="!templateLibrary.loading && templateLibrary.templates.length === 0" class="text-center py-12 text-slate-400 text-sm">
                    هیچ قالبی یافت نشد
                </div>
                {{-- Cards --}}
                <div x-show="!templateLibrary.loading && templateLibrary.templates.length > 0"
                    class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <template x-for="tpl in templateLibrary.templates" :key="tpl.id">
                        <button type="button" class="text-start border border-slate-200 rounded-xl overflow-hidden hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-400 transition cursor-pointer group flex flex-col disabled:opacity-60 disabled:cursor-wait"
                            :data-testid="'tpl-card-' + tpl.id" :disabled="templateLibrary.inserting"
                            @click="insertTemplate(tpl.id)">
                            {{-- Thumb --}}
                            <div class="aspect-video w-full bg-gradient-to-br from-slate-100 to-purple-50 flex items-center justify-center relative overflow-hidden">
                                <template x-if="tpl.thumbnail_url">
                                    <img :src="tpl.thumbnail_url" :alt="tpl.name" loading="lazy" class="w-full h-full object-cover transition duration-300 group-hover:scale-105">
                                </template>
                                <template x-if="!tpl.thumbnail_url">
                                    <svg class="w-8 h-8 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/></svg>
                                </template>
                                <span class="absolute top-1.5 right-1.5 text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-white/90 text-purple-700" x-text="tpl.type_label"></span>
                                <span x-show="tpl.source === 'library'" class="absolute top-1.5 left-1.5 text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-purple-600 text-white">آماده</span>
                                <div class="absolute inset-0 bg-purple-600/0 group-hover:bg-purple-600/10 transition flex items-center justify-center">
                                    <span class="opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100 bg-purple-600 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition" x-text="templateLibrary.inserting ? 'در حال درج…' : 'درج'"></span>
                                </div>
                            </div>
                            <div class="p-3 w-full">
                                <p class="text-xs font-semibold text-slate-800 truncate" x-text="tpl.name"></p>
                                <p class="text-[10px] text-slate-400 mt-0.5" x-text="tpl.category_label"></p>
                                <p x-show="tpl.description" class="text-[10px] leading-relaxed text-slate-500 mt-1 line-clamp-2" x-text="tpl.description"></p>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TB6: SAVE AS TEMPLATE MODAL
    ═══════════════════════════════════════════════════════ --}}
    {{-- Phase 3A — Section Library modal: the Persian-first composition
        library (sections + header/footer compositions). Insertion goes
        through the canonical tree operations via CanvasSectionLibrary —
        ONE undoable operation, every child independently editable. --}}
    <div x-show="showSectionLibrary" x-transition.opacity class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" style="display:none">
        <div @click.outside="showSectionLibrary = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col" style="max-height:88vh">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 shrink-0">
                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span x-text="sectionLibrary.kind === 'header' ? 'کتابخانه هدر' : sectionLibrary.kind === 'footer' ? 'کتابخانه فوتر' : 'کتابخانه بخش‌ها'"></span>
                </h3>
                <button @click="showSectionLibrary = false" class="text-slate-400 hover:text-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-3 border-b border-slate-100 shrink-0 bg-slate-50 flex items-center gap-3">
                <input type="text" x-model="sectionLibrary.search" placeholder="جستجو…"
                    class="flex-1 text-sm px-3 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300">
            </div>
            <div class="flex-1 overflow-y-auto p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <template x-for="item in sectionLibraryItems()" :key="item.key">
                        <div class="border border-slate-200 rounded-xl p-4 hover:shadow-md hover:border-indigo-300 transition cursor-pointer flex flex-col"
                            @click="insertSection(item.key)">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700" x-text="item.categoryLabel"></span>
                                <span class="text-[10px] text-slate-400" x-text="item.widgets + ' ویجت'"></span>
                            </div>
                            <p class="text-sm font-semibold text-slate-800" x-text="item.name"></p>
                            <p class="text-xs text-slate-500 mt-1 flex-1" x-text="item.description"></p>
                            <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                درج
                            </span>
                        </div>
                    </template>
                </div>
                <p x-show="sectionLibraryItems().length === 0" class="text-center py-12 text-slate-400 text-sm">موردی یافت نشد</p>
            </div>
        </div>
    </div>

    <div x-show="showSaveAsTemplate" x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" style="display:none">
        <div @click.outside="showSaveAsTemplate = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
            <h3 class="text-base font-bold text-slate-800 mb-4">ذخیره به‌عنوان قالب</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">نام قالب *</label>
                    <input type="text" x-model="saveTemplateForm.name" placeholder="نام قالب…"
                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-300">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">نوع</label>
                        <select x-model="saveTemplateForm.type" class="w-full text-sm px-2 py-2 rounded-lg border border-slate-200 focus:outline-none">
                            <option value="section">بخش</option>
                            <option value="page">صفحه</option>
                            <option value="header">هدر</option>
                            <option value="footer">فوتر</option>
                            <option value="landing_page">لندینگ پیج</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">دسته</label>
                        <select x-model="saveTemplateForm.category" class="w-full text-sm px-2 py-2 rounded-lg border border-slate-200 focus:outline-none">
                            <option value="hero">هیرو</option>
                            <option value="features">ویژگی‌ها</option>
                            <option value="pricing">قیمت‌گذاری</option>
                            <option value="testimonials">نظرات</option>
                            <option value="contact">تماس</option>
                            <option value="gallery">گالری</option>
                            <option value="team">تیم</option>
                            <option value="blog">وبلاگ</option>
                            <option value="product">محصول</option>
                            <option value="corporate">شرکتی</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">محدوده ذخیره</label>
                    <div class="flex gap-2">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" x-model="saveTemplateForm.scope" value="block" class="text-amber-500">
                            <span class="text-xs text-slate-700">فقط بلوک انتخابی</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" x-model="saveTemplateForm.scope" value="page" class="text-amber-500">
                            <span class="text-xs text-slate-700">کل صفحه</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-5">
                <button @click="showSaveAsTemplate = false" class="flex-1 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">انصراف</button>
                <button @click="saveAsTemplate()" :disabled="!saveTemplateForm.name || saveTemplateForm.saving"
                    class="flex-1 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600 disabled:opacity-50 transition">
                    <span x-show="!saveTemplateForm.saving">ذخیره</span>
                    <span x-show="saveTemplateForm.saving">در حال ذخیره…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         EXTENSIONS MODAL (Phase 3G-B-6)
    ═══════════════════════════════════════════════════════ --}}
    <div x-show="showExtensionsModal" x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" style="display:none">
        <div @click.outside="showExtensionsModal = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
            <h3 class="text-base font-bold text-slate-800 mb-1">افزونه‌های این قالب</h3>
            <p class="text-xs text-slate-500 mb-4">نسخه هر افزونه به‌صورت صریح انتخاب و در قالب ذخیره می‌شود — تغییر نسخه فعلی افزونه در فروشگاه، این انتخاب را تغییر نمی‌دهد.</p>

            {{-- Currently selected --}}
            <div class="space-y-2 mb-4 max-h-56 overflow-y-auto">
                <template x-for="extensionEntry in selectedExtensionEntries()" :key="extensionEntry[0]">
                    <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-800 truncate" x-text="extensionEntry[0]"></div>
                            <div class="text-[11px] text-slate-500">نسخه: <span x-text="extensionEntry[1].version" class="font-mono"></span></div>
                            <div x-show="extensionSelectionErrors[extensionEntry[0]]" class="text-[11px] text-red-500 mt-0.5" x-text="extensionSelectionErrors[extensionEntry[0]]"></div>
                        </div>
                        <button @click="removeExtensionSelection(extensionEntry[0])"
                            class="shrink-0 w-6 h-6 flex items-center justify-center rounded text-red-400 hover:bg-red-50" title="حذف">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
                <div x-show="selectedExtensionEntries().length === 0" class="text-center py-4 text-slate-400 text-xs">
                    هنوز افزونه‌ای به این قالب افزوده نشده است
                </div>
            </div>

            {{-- Add new --}}
            <div class="border-t border-slate-200 pt-4" x-show="AVAILABLE_EXTENSIONS.length > 0">
                <label class="text-xs font-semibold text-slate-600 mb-1.5 block">افزودن افزونه</label>
                <div class="flex gap-2">
                    <select x-model="newExtensionProduct" @change="newExtensionVersion = releasesForProduct(newExtensionProduct)[0]?.version ?? ''"
                        class="flex-1 text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-300">
                        <template x-for="p in AVAILABLE_EXTENSIONS" :key="p.identifier">
                            <option :value="p.identifier" x-text="p.name + ' (' + p.identifier + ')'"></option>
                        </template>
                    </select>
                    <select x-model="newExtensionVersion" class="w-28 text-sm px-2 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-300 font-mono">
                        <template x-for="r in releasesForProduct(newExtensionProduct)" :key="r.version">
                            <option :value="r.version" x-text="r.version"></option>
                        </template>
                    </select>
                    <button @click="addExtensionSelection()" :disabled="!newExtensionProduct || !newExtensionVersion"
                        class="px-3 py-2 rounded-lg bg-teal-600 hover:bg-teal-700 text-white text-xs font-medium transition disabled:opacity-50">
                        افزودن
                    </button>
                </div>
            </div>
            <div x-show="AVAILABLE_EXTENSIONS.length === 0" class="text-center py-3 text-slate-400 text-xs border-t border-slate-200 mt-2 pt-3">
                هیچ افزونه قابل‌انتخابی (با نسخه منتشرشده) در کاتالوگ وجود ندارد.
            </div>

            <div class="flex gap-2 mt-5">
                <button @click="showExtensionsModal = false" class="flex-1 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">بستن</button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         UI-C2D: CHECKPOINTS MODAL (create / list / restore)
    ═══════════════════════════════════════════════════════ --}}
    <div x-show="showCheckpointsModal" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="showCheckpointsModal = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" data-testid="tb-checkpoints-modal">
            <h3 class="text-base font-bold text-slate-800 mb-1">تاریخچه / چک‌پوینت</h3>
            <p class="text-xs text-slate-500 mb-4">یک چک‌پوینت، وضعیت کامل پیش‌نویس این قالب (صفحات، هدر، فوتر، استایل‌های سراسری و انتخاب افزونه‌ها) را ذخیره می‌کند. بازیابی، فقط این وضعیت را برمی‌گرداند — روی نسخه‌های منتشرشده تجاری اثری ندارد.</p>

            <div class="flex gap-2 mb-4">
                <input type="text" x-model="newCheckpointLabel" placeholder="برچسب (اختیاری)"
                    class="flex-1 text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    data-testid="tb-checkpoint-label-input">
                <button @click="createCheckpoint()" :disabled="creatingCheckpoint"
                    class="px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition disabled:opacity-50"
                    data-testid="tb-checkpoint-create">
                    <span x-text="creatingCheckpoint ? 'در حال ذخیره...' : 'ایجاد چک‌پوینت'"></span>
                </button>
            </div>

            <div class="space-y-2 max-h-64 overflow-y-auto" data-testid="tb-checkpoint-list">
                <template x-for="rev in checkpoints" :key="rev.id">
                    <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-800 truncate" x-text="rev.label"></div>
                            <div class="text-[11px] text-slate-500" x-text="'نسخه ' + rev.version + ' — ' + formatCheckpointDate(rev.created_at)"></div>
                        </div>
                        <button type="button" @click="restoreCheckpoint(rev)" :disabled="restoringCheckpointId === rev.id"
                            class="shrink-0 px-2.5 py-1 rounded-lg border border-indigo-200 text-indigo-600 text-xs font-medium hover:bg-indigo-50 transition disabled:opacity-50"
                            data-testid="tb-checkpoint-restore">
                            <span x-text="restoringCheckpointId === rev.id ? '...' : 'بازیابی'"></span>
                        </button>
                    </div>
                </template>
                <div x-show="!loadingCheckpoints && checkpoints.length === 0" class="text-center py-4 text-slate-400 text-xs">
                    هنوز چک‌پوینتی ثبت نشده است
                </div>
                <div x-show="loadingCheckpoints" class="text-center py-4 text-slate-400 text-xs">در حال بارگذاری...</div>
            </div>

            <div class="flex gap-2 mt-5">
                <button @click="showCheckpointsModal = false" class="flex-1 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">بستن</button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         UI-C2E: CLONE MODAL
    ═══════════════════════════════════════════════════════ --}}
    <div x-show="showCloneModal" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="showCloneModal = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6" data-testid="tb-clone-modal">
            <h3 class="text-base font-bold text-slate-800 mb-1">کپی این تم</h3>
            <p class="text-xs text-slate-500 mb-4">یک تم جدید با همان صفحات، هدر، فوتر، استایل‌ها و انتخاب افزونه‌ها ساخته می‌شود — با شناسه و آدرس کاملاً جدید.</p>

            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">نام تم جدید</label>
            <input type="text" x-model="cloneName" class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300 mb-3"
                data-testid="tb-clone-name-input">

            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">سایت (اختیاری)</label>
            <select x-model="cloneSiteId" class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300 mb-4"
                data-testid="tb-clone-site-select">
                <option value="">قالب پلتفرم</option>
                @foreach($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->title }}</option>
                @endforeach
            </select>

            <div class="flex gap-2">
                <button @click="showCloneModal = false" class="flex-1 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">انصراف</button>
                <button @click="cloneThemeNow()" :disabled="cloning || !cloneName"
                    class="flex-1 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition disabled:opacity-50"
                    data-testid="tb-clone-confirm">
                    <span x-text="cloning ? 'در حال ایجاد...' : 'ایجاد کپی'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         ADD PAGE MODAL
    ═══════════════════════════════════════════════════════ --}}
    {{-- Global Styles is an explicit Theme-level panel, separate from the
         page/header/footer block-tree target state. --}}
    <div x-show="showGlobalStylesPanel" x-transition.opacity
        @keydown.escape.window="closeGlobalStylesPanel()"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-3 sm:p-5"
        role="dialog" aria-modal="true" aria-labelledby="global-styles-title"
        data-testid="global-styles-dialog">
        <div @click.outside="closeGlobalStylesPanel()"
            class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden"
            style="max-height:calc(100vh - 24px);">
            <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-slate-200 shrink-0">
                <div>
                    <h2 id="global-styles-title" class="text-base font-bold text-slate-800">استایل‌های سراسری</h2>
                    <p class="text-xs text-slate-500 mt-1">رنگ، تایپوگرافی و مقیاس‌های مشترک قالب</p>
                </div>
                <button type="button" @click="closeGlobalStylesPanel()"
                    class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                    aria-label="بستن پنل استایل‌های سراسری">×</button>
            </div>

            <div class="min-h-0 overflow-y-auto p-5 space-y-6" style="min-height:0;" data-testid="global-styles-controls">
                <fieldset>
                    <legend class="text-sm font-bold text-slate-700 mb-3">رنگ‌ها</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach([
                            'primary_color' => 'رنگ اصلی',
                            'secondary_color' => 'رنگ ثانویه',
                            'accent_color' => 'رنگ تأکیدی',
                            'background_color' => 'پس‌زمینه',
                            'surface_color' => 'سطح کارت‌ها',
                            'text_color' => 'متن اصلی',
                            'muted_text_color' => 'متن کم‌رنگ',
                        ] as $field => $label)
                            <label class="block text-xs font-semibold text-slate-600" for="global-style-{{ str_replace('_', '-', $field) }}">
                                {{ $label }}
                                <span class="mt-1.5 flex gap-2" dir="ltr">
                                    <input type="color" x-model="globalStyleDraft.{{ $field }}" @input="globalStylesDirty = true"
                                        class="w-11 h-10 rounded-lg border border-slate-200 bg-white p-1 cursor-pointer shrink-0"
                                        aria-label="انتخاب {{ $label }}">
                                    <input id="global-style-{{ str_replace('_', '-', $field) }}" type="text"
                                        x-model="globalStyleDraft.{{ $field }}" @input="globalStylesDirty = true"
                                        pattern="#[0-9a-fA-F]{6}" maxlength="7"
                                        class="min-w-0 flex-1 text-sm font-mono px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-300">
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="border-t border-slate-100 pt-5">
                    <legend class="text-sm font-bold text-slate-700 mb-3">تایپوگرافی</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block text-xs font-semibold text-slate-600" for="global-style-font-family">خانواده فونت
                            <input id="global-style-font-family" type="text" x-model="globalStyleDraft.font_family" @input="globalStylesDirty = true"
                                class="mt-1.5 w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-300" dir="ltr">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600" for="global-style-type-scale">مقیاس تایپوگرافی
                            <input id="global-style-type-scale" type="number" min="0.5" max="3" step="0.05" x-model.number="globalStyleDraft.type_scale" @input="globalStylesDirty = true"
                                class="mt-1.5 w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-300" dir="ltr">
                        </label>
                    </div>
                </fieldset>

                <fieldset class="border-t border-slate-100 pt-5">
                    <legend class="text-sm font-bold text-slate-700 mb-3">فاصله و گوشه‌ها</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach([
                            'spacing_unit' => ['واحد فاصله', 'text', null],
                            'spacing_scale' => ['مقیاس فاصله', 'number', '0.05'],
                            'radius_sm' => ['گوشه کوچک', 'text', null],
                            'radius_md' => ['گوشه متوسط', 'text', null],
                            'radius_lg' => ['گوشه بزرگ', 'text', null],
                        ] as $field => [$label, $type, $step])
                            <label class="block text-xs font-semibold text-slate-600" for="global-style-{{ str_replace('_', '-', $field) }}">{{ $label }}
                                <input id="global-style-{{ str_replace('_', '-', $field) }}" type="{{ $type }}"
                                    @if($step) min="0.5" max="3" step="{{ $step }}" @endif
                                    x-model{{ $type === 'number' ? '.number' : '' }}="globalStyleDraft.{{ $field }}" @input="globalStylesDirty = true"
                                    class="mt-1.5 w-full text-sm font-mono px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-300" dir="ltr">
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="border-t border-slate-100 pt-5">
                    <legend class="text-sm font-bold text-slate-700 mb-3">سایه‌ها</legend>
                    <div class="space-y-3">
                        @foreach(['shadow_sm' => 'سایه کوچک', 'shadow_md' => 'سایه متوسط', 'shadow_lg' => 'سایه بزرگ'] as $field => $label)
                            <label class="block text-xs font-semibold text-slate-600" for="global-style-{{ str_replace('_', '-', $field) }}">{{ $label }}
                                <input id="global-style-{{ str_replace('_', '-', $field) }}" type="text" x-model="globalStyleDraft.{{ $field }}" @input="globalStylesDirty = true"
                                    class="mt-1.5 w-full text-sm font-mono px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-300" dir="ltr">
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-slate-200 bg-slate-50 shrink-0">
                <span x-show="globalStylesDirty" class="text-xs font-medium text-amber-600" data-testid="global-styles-dirty">تغییرات ذخیره نشده</span>
                <div class="flex gap-2 mr-auto">
                    <button type="button" @click="closeGlobalStylesPanel()"
                        class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 text-sm font-medium hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">بستن</button>
                    <button type="button" @click="saveGlobalStyles()" :disabled="globalStylesSaving || !globalStylesDirty"
                        class="px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700 disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2"
                        data-testid="global-styles-save">
                        <span x-text="globalStylesSaving ? 'در حال ذخیره…' : 'ذخیره استایل‌ها'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showAddPage" x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="showAddPage = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
            <h3 class="text-base font-bold text-slate-800 mb-4">افزودن صفحه جدید</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block">عنوان صفحه *</label>
                    <input x-model="newPageTitle" type="text" class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="مثال: درباره ما" @keydown.enter="confirmAddPage()">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block">اسلاگ (URL)</label>
                    <input x-model="newPageSlug" type="text" class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="about-us (اختیاری)">
                </div>
            </div>
            <div class="flex gap-2 mt-5">
                <button @click="confirmAddPage()" :disabled="!newPageTitle" class="flex-1 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition">افزودن</button>
                <button @click="showAddPage = false" class="flex-1 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">انصراف</button>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast.show" x-transition.opacity
         :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 text-white text-sm font-medium px-4 py-2.5 rounded-xl shadow-lg z-50 flex items-center gap-2">
        <span x-text="toast.type === 'success' ? '✓' : '✗'"></span>
        <span x-text="toast.msg"></span>
    </div>
</div>
@endsection

@push('scripts')
@php
    /*
     * Phase 3G-B-6 root-cause fix: Blade's @json(...) directive splits its
     * argument on EVERY literal comma (Illuminate\View\Compilers\Concerns\
     * CompilesJson::compileJson() does a plain explode(',', ...), with zero
     * bracket/paren awareness, and silently DISCARDS anything past the 3rd
     * comma-separated segment). An expression with more than two top-level
     * -looking commas — like the mapWithKeys() closure below, which has one
     * for `fn($d,$t)` plus one per array item — silently truncates into
     * invalid PHP. This existing latent defect was never triggered before
     * because no prior automated test rendered this view end-to-end; adding
     * the `is_container` key here is what pushed BLOCK_SCHEMAS over the
     * 3-segment limit. Fixed at the root by precomputing into a plain
     * variable first, so @json() below only ever sees a bare variable name
     * (zero commas) — not by re-golfing the expression to dodge the comma
     * count.
     */
    $blockLabelsForJs = collect($registry)->mapWithKeys(fn($d, $t) => [$t => $d['name']]);
    /*
     * is_container is declarative capability metadata read straight off each
     * merged $registry entry's own `is_container` key (present on canonical
     * AND Extension entries alike — see ExtensionCanvasPaletteAdapter)
     * rather than re-derived via the canonical-ONLY
     * BlockRegistry::isContainerType() static method, which would silently
     * report every `ext:*` container type as non-container.
     */
    $blockSchemasForJs = collect($registry)->mapWithKeys(function ($d, $t) {
        $definition = \SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry::normalizeDefinition($d);
        return [$t => [
            'schema' => $definition['schema'],
            'defaultProps' => $definition['defaultProps'] ?? [],
            'editor' => $definition['editor'],
            'is_container' => !empty($definition['is_container']),
        ]];
    });
@endphp
{{-- Phase 3 (canvas limits contract alignment): the structural tree
     limits are the canonical PHP DocumentValidator constants, safely
     JSON-serialized server-side. The structural services partial reads
     them from window.CANVAS_TREE_LIMITS instead of duplicating the
     values as independent browser-side literals. --}}
<script>
window.CANVAS_TREE_LIMITS = @json($canvasTreeLimits);
</script>
@include('theme-runtime::canvas.color')
@include('theme-runtime::canvas.controls')
@include('theme-runtime::canvas.icons')
@include('theme-runtime::canvas.tab-strip')
@include('theme-runtime::canvas.inspect')
@include('theme-runtime::canvas.editor-bootstrap', ['canvasStructuralServices' => ['admin.theme-builder.partials.canvas-structural-services']])
@include('admin.theme-builder.partials.canvas-section-library')
<script>
const THEME_ID   = {{ $theme->id }};
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const BLOCK_LABELS  = @json($blockLabelsForJs);
const BLOCK_SCHEMAS = @json($blockSchemasForJs);
const FAST_PATH_BLOCK_TYPES = new Set(['reference_counter', 'header_modern_v1']);
const FAST_PATH_PROP_KEYS = new Map([
    ['header_modern_v1', new Set(['bgColor', 'textColor', 'ctaColor'])],
]);
const INITIAL_PAGES = @json($pages);
const INITIAL_HEADER_BLOCKS = @json($theme->header_blocks ?? []);
const INITIAL_FOOTER_BLOCKS = @json($theme->footer_blocks ?? []);
const INITIAL_GLOBAL_STYLES = @json($visualGlobalStyles);
// Phase 3G-B-6: Extension authoring — commercial catalog data only (no
// executable provider code ever reaches the browser here).
const AVAILABLE_EXTENSIONS = @json($availableExtensions);
const INITIAL_EXTENSION_SELECTIONS = @json($extensionSelections);
const EXTENSION_SELECTION_ERRORS = @json($extensionSelectionErrors);
// UI-C2D/C2E: initial publish status, for the canonical Canvas's own
// unpublish action — updated client-side after a successful unpublish so
// the menu reacts without a full page reload.
const INITIAL_THEME_STATUS = @json($theme->status);
// Phase 2A — canonical layout document contract adoption (narrowest
// boundary: a read-only version/direction/locale constant, no editor
// rewrite). The editor was built against this envelope version; the
// server fail-closes on any version it does not know.
const CANVAS_LAYOUT_VERSION = @json($layoutEnvelope['layout_version']);
const CANVAS_LAYOUT_ATTRIBUTES = @json($layoutAttributes);
window.CANVAS_LAYOUT_VERSION = CANVAS_LAYOUT_VERSION;
window.CANVAS_LAYOUT_ATTRIBUTES = CANVAS_LAYOUT_ATTRIBUTES;

function themeCanvas() {
    return {
        // ADR-082: tabs, conditions, per-device values and states of the
        // Inspector (theme-runtime canvas-controls.js; ctlSetProp below).
        ...window.CanvasControls.editorMixin(),
        // ── State ───────────────────────────────────────────────
        pages:          JSON.parse(JSON.stringify(INITIAL_PAGES)),
        currentPage:    Object.keys(INITIAL_PAGES)[0] ?? 'home',
        activeTarget:   'page',
        headerBlocks:   JSON.parse(JSON.stringify(INITIAL_HEADER_BLOCKS)),
        footerBlocks:   JSON.parse(JSON.stringify(INITIAL_FOOTER_BLOCKS)),
        globalStyles:   JSON.parse(JSON.stringify(INITIAL_GLOBAL_STYLES)),
        globalStyleDraft: JSON.parse(JSON.stringify(INITIAL_GLOBAL_STYLES)),
        showGlobalStylesPanel: false,
        globalStylesDirty: false,
        globalStylesSaving: false,
        selectedTab:    'content',
        isDirty:        false,
        // P1: every Canvas save goes through one serial lane (CanvasSaveQueue,
        // created in init()). `saving` / `quickSaving` are derived from the
        // outstanding requests below — never set directly.
        saveQueue:      null,
        manualSaveRequests: 0,
        backgroundSaveRequests: 0,
        saveInFlight:   false,
        switchingTarget: false,
        publishing:     false,
        showAddPage:    false,
        newPageTitle:   '',
        newPageSlug:    '',
        librarySearch:  '',
        expandedCats:   { layout:true, content:true, module:true, dynamic:false, ecommerce:false, marketing:false, utility:false, extension:true },
        toast:          { show: false, msg: '', type: 'success', _timer: null },
        // TB3-02: iframe state
        device:         'desktop',
        iframeTimestamp: Date.now(),
        inspectOn: false,
        inspectHost: null,
        auditSummary: null,
        iframeLoading:  true,
        iframeReady:    false,
        propUpdateTimer: null,
        pendingFastPropUpdates: [],
        // TB3-03: drag state
        isDraggingBlock:     false,
        draggedBlockType:    null,
        draggedBlockLabel:   null,
        dropTargetIndex:     -1,
        dropTargetBeforeId:  null,
        dragTargetContainerId: null,  // TB4: container drag target
        structuralDrag: null,
        palettePointerDrag: null,
        suppressPaletteClick: false,
        // Phase 2C: operation history and canonical structural services.
        documentState:  null,
        treeOperations: null,
        historyEngine:  null,
        navigator:      null,
        historyVersion: 0,
        // TB3-05: style panel sections (open/closed)
        styleSection: { typography: true, colors: true, spacing: false, border: false, shadow: false },
        // TB3-04: inline edit auto-save
        inlineAutoSaveTimer: null,
        // TB3-07: media manager
        mediaModal: { show: false, tab: 'library', loading: false, images: [], uploading: false, uploadDone: false, targetPropKey: null, onSelect: null },
        // Phase 0.1: per-item collapse state of schema-driven repeaters (UI only, never saved).
        repeaterOpen: {},
        // TB4: nested containers
        leftPanelTab:    'blocks',      // 'blocks' | 'tree' | 'health' (WU-3)
        treeExpanded:    {},            // blockId → bool (default: expanded)
        showAddChildModal: false,
        // TB5: responsive editor
        activeBreakpoint: 'desktop',   // 'desktop' | 'tablet' | 'mobile'
        // TB6: template library
        showTemplateLibrary: false,
        showSaveAsTemplate: false,
        templateLibrary: { loading: false, inserting: false, templates: [], type: '', category: '', search: '' },
        saveTemplateForm: { name: '', type: 'section', category: 'hero', scope: 'block', saving: false },
        // Phase 3A — Section Library state (Persian-first composition library).
        showSectionLibrary: false,
        sectionLibrary: { kind: 'section', search: '' },
        // Phase 3G-B-6: Extension authoring — {product_identity: {version}},
        // written verbatim into Theme.settings.extension_selections on save.
        extensionSelections: JSON.parse(JSON.stringify(INITIAL_EXTENSION_SELECTIONS ?? {})),
        extensionSelectionErrors: EXTENSION_SELECTION_ERRORS ?? {},
        showExtensionsModal: false,
        newExtensionProduct: '',
        newExtensionVersion: '',
        // UI-C2D/C2E: More-actions menu, checkpoints, clone, unpublish.
        showMoreMenu: false,
        themeStatus: INITIAL_THEME_STATUS,
        unpublishing: false,
        showCheckpointsModal: false,
        checkpoints: [],
        loadingCheckpoints: false,
        creatingCheckpoint: false,
        restoringCheckpointId: null,
        newCheckpointLabel: '',
        showCloneModal: false,
        cloneName: '',
        cloneSiteId: '',
        cloning: false,
        importing: false,

        // ── Computed ─────────────────────────────────────────────
        get blocks() {
            if (this.activeTarget === 'header') return this.headerBlocks;
            if (this.activeTarget === 'footer') return this.footerBlocks;
            return this.pages[this.currentPage]?.blocks ?? [];
        },
        set blocks(val) {
            if (this.activeTarget === 'header') this.headerBlocks = val;
            else if (this.activeTarget === 'footer') this.footerBlocks = val;
            else if (this.pages[this.currentPage]) this.pages[this.currentPage].blocks = val;
        },
        // Stable node id is authoritative; positional paths are derived for
        // legacy rendering and public path-based editor methods.
        get selectedPath() {
            return this.documentState?.path(this.blocks) ?? null;
        },
        get selectedBlock() {
            return this.documentState?.selectedNode(this.blocks) ?? null;
        },
        // backward compat — top-level index of selected block
        get selectedIndex() {
            return this.selectedPath?.length > 0 ? (this.selectedPath[0] ?? -1) : -1;
        },
        get selectedSchema() {
            if (!this.selectedBlock) return {};
            // A `blocks` field is the container's child list, authored in
            // the children panel — it has no content-tab control.
            const schema = BLOCK_SCHEMAS[this.selectedBlock.type]?.schema ?? {};
            return Object.fromEntries(Object.entries(schema).filter(([, def]) => def?.type !== 'blocks'));
        },
        // ADR-076 section headers (fieldGroupStart) come from the
        // CanvasControls mixin, computed over the active tab's visible fields.
        // ADR-082: the mixin's prop writer — one undo step per key, like any
        // control; `undefined` drops a device value back to inheritance.
        ctlSetProp(key, value) {
            this.updateProp(key, value);
            if (value === undefined && this.selectedBlock?.props) delete this.selectedBlock.props[key];
        },
        get selectedEditorCapabilities() {
            return this.selectedBlock
                ? (BLOCK_SCHEMAS[this.selectedBlock.type]?.editor ?? { allowCustomCss: true, lockedStructure: false, uiDirection: null })
                : { allowCustomCss: true, lockedStructure: false, uiDirection: null };
        },
        // A user-requested save (toolbar, Ctrl+S, zone switch, extension change) is outstanding.
        get saving() { return this.manualSaveRequests > 0; },
        // A background save (structural autosave, inline-edit autosave) is outstanding.
        get quickSaving() { return this.backgroundSaveRequests > 0; },
        // The user's save is waiting behind a request already on the wire.
        get saveWaiting() { return this.saving && !!this.saveQueue?.waiting; },
        get canUndo() { this.historyVersion; return !!this.historyEngine?.canUndo; },
        get canRedo() { this.historyVersion; return !!this.historyEngine?.canRedo; },
        get recentHistory() { this.historyVersion; return this.historyEngine?.recent(10) ?? []; },
        // TB5: reactive style object for the active breakpoint
        get currentBpStyle() {
            if (!this.selectedBlock) return {};
            if (this.activeBreakpoint === 'desktop') return this.selectedBlock.style ?? {};
            if (!this.selectedBlock.responsive) return {};
            return this.selectedBlock.responsive[this.activeBreakpoint] ?? {};
        },
        // TB5: get a value with cascade fallback (mobile → tablet → desktop)
        getCascadedValue(key, def = '') {
            if (!this.selectedBlock) return def;
            if (this.activeBreakpoint === 'mobile') {
                const m = this.selectedBlock.responsive?.mobile?.[key];
                if (m !== undefined && m !== '') return m;
                const t = this.selectedBlock.responsive?.tablet?.[key];
                if (t !== undefined && t !== '') return t;
            }
            if (this.activeBreakpoint === 'tablet') {
                const t = this.selectedBlock.responsive?.tablet?.[key];
                if (t !== undefined && t !== '') return t;
            }
            return this.selectedBlock.style?.[key] ?? def;
        },
        // TB5: true when a breakpoint has an override for this key
        hasResponsiveOverride(key, bp) {
            if (!this.selectedBlock?.responsive) return false;
            const v = this.selectedBlock.responsive[bp]?.[key];
            return v !== undefined && v !== '';
        },

        // ── Block Library ─────────────────────────────────────────
        toggleCategory(key)   { this.expandedCats[key] = !this.expandedCats[key]; },
        isCategoryVisible(key) {
            if (!this.librarySearch) return true;
            const s = this.librarySearch.toLowerCase();
            return Object.keys(BLOCK_LABELS).some(t => {
                return t.includes(s) || (BLOCK_LABELS[t] ?? '').toLowerCase().includes(s);
            });
        },
        isBlockVisible(type, name) {
            if (!this.librarySearch) return true;
            const s = this.librarySearch.toLowerCase();
            return type.toLowerCase().includes(s) || name.toLowerCase().includes(s);
        },

        // ── TB4: Block Tree Helpers ───────────────────────────────
        makeBlock(type) {
            return {
                id: 'blk_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                type,
                props: JSON.parse(JSON.stringify(BLOCK_SCHEMAS[type]?.defaultProps ?? {})),
                ...(this.isContainerType(type) ? { children: [] } : {})
            };
        },

        isContainerType(type) {
            // Phase 3F-B: declarative metadata from the server (see
            // BLOCK_SCHEMAS construction above), not a hardcoded array —
            // the authoritative source is BlockRegistry::isContainerType().
            return !!(BLOCK_SCHEMAS[type]?.is_container);
        },

        getBlockByPath(path, arr) {
            return CanvasIdentity.nodeAtPath(arr ?? this.blocks, path);
        },

        getSiblingArray(path) {
            if (!path || path.length === 0) return null;
            if (path.length === 1) return this.blocks;
            const parent = this.getBlockByPath(path.slice(0, -1));
            if (!parent) return null;
            if (!parent.children) parent.children = [];
            return parent.children;
        },

        findBlockPath(blockId, arr, prefix) {
            arr = arr ?? this.blocks;
            prefix = prefix ?? [];
            const path = CanvasIdentity.pathForId(arr, blockId);
            return path ? [...prefix, ...path] : null;
        },

        flattenTree(arr, depth, prefix) {
            const tree = arr ?? this.blocks;
            const baseDepth = depth ?? 0;
            const basePath = prefix ?? [];
            return (this.navigator?.flatten(tree, this.treeExpanded, baseDepth) ?? [])
                .map(entry => ({ ...entry, path: [...basePath, ...entry.path] }));
        },

        isSelectedPath(path) {
            if (!this.selectedPath || !path) return false;
            if (this.selectedPath.length !== path.length) return false;
            return this.selectedPath.every((v, i) => v === path[i]);
        },

        toggleTreeNode(blockId) {
            this.treeExpanded[blockId] = this.treeExpanded[blockId] !== false ? false : true;
        },
        navigatorLabel(block) {
            return this.navigator?.label(block, BLOCK_LABELS)
                ?? (BLOCK_LABELS[block?.type] ?? block?.type ?? '');
        },
        nodeLocked(block) {
            return !!this.treeOperations?.capabilities(block).lockedStructure;
        },

        // ── Canvas Actions ────────────────────────────────────────
        addBlock(type, atIndex = -1, parentPath = null) {
            const block = this.makeBlock(type);
            const parent = parentPath ? this.getBlockByPath(parentPath) : null;
            const parentId = parent?.id ?? 'page';
            const children = parent ? (parent.children ?? []) : this.blocks;
            const index = atIndex >= 0 ? Math.min(atIndex, children.length) : children.length;
            try {
                const operation = this.treeOperations.insertNode(this.blocks, block, parentId, index);
                this.recordStructuralHistory('insert', operation, null, block, this.documentState.selectedNodeId, block.id);
                this.afterStructuralChange(operation, block.id);
            } catch (error) { this.structuralError(error); }
        },

        addChildBlock(type) {
            if (!this.selectedPath || !this.isContainerType(this.selectedBlock?.type)) return;
            this.addBlock(type, -1, this.selectedPath);
        },

        // Remove block at selectedPath
        removeSelected() {
            if (!this.selectedPath || this.selectedEditorCapabilities.lockedStructure) return;
            const nodeId = this.selectedBlock.id;
            try {
                const operation = this.treeOperations.deleteNode(this.blocks, nodeId);
                const fallback = operation.before.parentId === 'page' ? null : operation.before.parentId;
                this.recordStructuralHistory('delete', operation, operation.before.node, null, nodeId, fallback);
                this.afterStructuralChange(operation, fallback);
            } catch (error) { this.structuralError(error); }
        },

        duplicateSelected() {
            if (!this.selectedPath || this.selectedEditorCapabilities.lockedStructure) return;
            try {
                const operation = this.treeOperations.duplicateNode(this.blocks, this.selectedBlock.id);
                this.recordStructuralHistory('duplicate', operation, null, operation.node, this.selectedBlock.id, operation.duplicate_id);
                this.afterStructuralChange(operation, operation.duplicate_id);
            } catch (error) { this.structuralError(error); }
        },

        _regenIds(block) {
            block.id = 'blk_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);
            (block.children ?? []).forEach(c => this._regenIds(c));
        },

        selectBlock(pathOrIndex) {
            // Accept path array or legacy integer index
            const path = Array.isArray(pathOrIndex) ? pathOrIndex : (pathOrIndex >= 0 ? [pathOrIndex] : null);
            const selected = CanvasIdentity.nodeAtPath(this.blocks, path);
            this.documentState?.select(selected?.id ?? null, this.blocks);
            this.selectedTab = 'content';
            if (this.selectedBlock) {
                this.sendToIframe({ action: 'select', blockId: this.selectedBlock.id });
            }
        },

        // Move selected block within its sibling array
        moveSelectedUp() {
            if (!this.selectedPath || this.selectedEditorCapabilities.lockedStructure) return;
            const found = this.treeOperations.find(this.blocks, this.selectedBlock.id);
            if (!found || found.index <= 0) return;
            this.performMove(this.selectedBlock.id, found.parentId, found.index - 1);
        },

        moveSelectedDown() {
            if (!this.selectedPath || this.selectedEditorCapabilities.lockedStructure) return;
            const found = this.treeOperations.find(this.blocks, this.selectedBlock.id);
            if (!found || found.index >= found.siblings.length - 1) return;
            this.performMove(this.selectedBlock.id, found.parentId, found.index + 2);
        },

        canMoveUp() {
            if (!this.selectedPath) return false;
            return (this.selectedPath[this.selectedPath.length - 1] ?? 0) > 0;
        },

        canMoveDown() {
            if (!this.selectedPath) return false;
            const siblings = this.getSiblingArray(this.selectedPath);
            const idx = this.selectedPath[this.selectedPath.length - 1];
            return siblings && idx < siblings.length - 1;
        },

        // Container child operations (called from children list in props panel)
        moveChildUp(ci) {
            if (!this.selectedBlock?.children || ci <= 0) return;
            this.performMove(this.selectedBlock.children[ci].id, this.selectedBlock.id, ci - 1);
        },

        moveChildDown(ci) {
            const ch = this.selectedBlock?.children ?? [];
            if (ci >= ch.length - 1) return;
            this.performMove(ch[ci].id, this.selectedBlock.id, ci + 2);
        },

        removeChild(ci) {
            if (!this.selectedBlock?.children) return;
            const childId = this.selectedBlock.children[ci]?.id;
            if (!childId) return;
            const selectedId = this.selectedBlock.id;
            try {
                const operation = this.treeOperations.deleteNode(this.blocks, childId);
                this.recordStructuralHistory('delete', operation, operation.before.node, null, childId, selectedId);
                this.afterStructuralChange(operation, selectedId);
            } catch (error) { this.structuralError(error); }
        },

        // Legacy: kept for undo/redo top-level swap
        moveBlock(from, to) {
            if (to < 0 || to >= this.blocks.length) return;
            const node = this.blocks[from];
            if (node) this.performMove(node.id, 'page', to > from ? to + 1 : to);
        },

        // ── TB3-S2: Undo / Redo ──────────────────────────────────
        performMove(nodeId, targetParentId, targetIndex) {
            try {
                const operation = this.treeOperations.moveNode(this.blocks, nodeId, targetParentId, targetIndex);
                this.recordStructuralHistory('move', operation, operation.before, { parentId: operation.target_parent_id, index: operation.final_index }, nodeId, nodeId);
                this.afterStructuralChange(operation, nodeId);
            } catch (error) { this.structuralError(error); }
        },

        recordStructuralHistory(type, operation, before, after, selectionBefore, selectionAfter) {
            this.historyEngine.record({ type, targetId: operation.node_id, operation, before, after, selectionBefore, selectionAfter });
            this.historyVersion++;
        },

        afterStructuralChange(operation, selectedId) {
            this.documentState.queue(operation);
            this.selectBlockById(selectedId);
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        selectBlockById(nodeId) {
            this.documentState?.select(nodeId || null, this.blocks);
        },

        structuralError(error) {
            const labels = { cycle: 'انتقال چرخه‌ای مجاز نیست.', 'locked-structure': 'ساختار این بخش قفل است.', 'invalid-parent-capability': 'این مقصد فرزند نمی‌پذیرد.', 'invalid-child-capability': 'نوع بلوک با مقصد سازگار نیست.', 'max-children': 'ظرفیت مقصد تکمیل است.', 'max-depth': 'عمق تو در تو بیش از حد مجاز است.' };
            this.showToast(labels[error?.message] || 'جابجایی ساختاری معتبر نیست.', 'error');
        },

        applyHistoryEntry(entry, side) {
            const value = side === 'before' ? entry.before : entry.after;
            try {
                let operation = null;
                if (entry.type === 'move') {
                    const source = this.treeOperations.find(this.blocks, entry.targetId);
                    const requested = source?.parentId === value.parentId && source.index < value.index ? value.index + 1 : value.index;
                    operation = this.treeOperations.moveNode(this.blocks, entry.targetId, value.parentId, requested);
                } else if (entry.type === 'insert' || entry.type === 'duplicate') {
                    if (side === 'before') {
                        const result = this.treeOperations.deleteNode(this.blocks, entry.operation.node?.id || entry.operation.duplicate_id);
                        operation = { operation: 'delete', node_id: entry.operation.node?.id || entry.operation.duplicate_id };
                    } else {
                        operation = this.treeOperations.insertNode(this.blocks, JSON.parse(JSON.stringify(entry.after)), entry.operation.target_parent_id, entry.operation.target_index);
                    }
                } else if (entry.type === 'delete') {
                    if (side === 'before') {
                        operation = this.treeOperations.insertNode(this.blocks, JSON.parse(JSON.stringify(entry.before)), entry.operation.before.parentId, entry.operation.before.index);
                    } else {
                        this.treeOperations.deleteNode(this.blocks, entry.targetId);
                        operation = { operation: 'delete', node_id: entry.targetId };
                    }
                } else                if (entry.type === 'value') {
                    const found = this.treeOperations.find(this.blocks, entry.targetId);
                    if (!found) return;
                    let bag = found.node;
                    for (let i = 0; i < entry.path.length - 1; i++) bag = bag[entry.path[i]] || (bag[entry.path[i]] = {});
                    const key = entry.path[entry.path.length - 1];
                    if (value === undefined) delete bag[key]; else bag[key] = JSON.parse(JSON.stringify(value));
                    this.schedulePropUpdate();
                }
                // Phase 3A root-cause fix (replay-mismatch defect class): a
                // history REPLAY re-derives tree state from full snapshots,
                // so the previously queued incremental log no longer
                // describes how the stored tree reaches the submitted
                // document (replaying old + derived ops diverged mid-storm,
                // producing "result does not match" and transient duplicate-
                // id rejections). The pending log is therefore reset here:
                // the resulting save goes through the server's IMPLICIT
                // reconciler, which still enforces every structural guard
                // (placement, locked structure, cycles, duplicate ids,
                // depth/count bounds) on the full document. Security is NOT
                // weakened — the anti-forgery replay remains the authority
                // for forward (non-replay) structural edits.
                if (this.documentState) this.documentState.pendingOperations = [];
                this.selectBlockById(side === 'before' ? entry.selectionBefore : entry.selectionAfter);
                this.isDirty = true;
            } catch (error) { this.structuralError(error); }
        },

        // Phase 2C: keyboard shortcuts. Keys typed into form controls are
        // excluded so Inspector editing never triggers a structural/value undo.
        onUndoShortcut($event) {
            const t = $event.target;
            if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
            this.undo();
        },

        onRedoShortcut($event) {
            const t = $event.target;
            if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
            this.redo();
        },

        undo() {
            const entry = this.historyEngine?.undo();
            if (!entry) return;
            this.historyVersion++;
            if (entry.type !== 'value') this.autoSaveAndReload();
        },

        redo() {
            const entry = this.historyEngine?.redo();
            if (!entry) return;
            this.historyVersion++;
            if (entry.type !== 'value') this.autoSaveAndReload();
        },

        // Update a single prop and schedule live iframe update
        // `options.coalesceKey` overrides how consecutive edits merge into one
        // undo step: null records a step of its own (structural list edits).
        updateProp(propKey, value, options) {
            if (!this.selectedBlock) return;
            const targetId = this.selectedBlock.id;
            const before = this.selectedBlock.props?.[propKey];
            // Phase 2B-R acceptance fix: seeded/legacy blocks may carry
            // `props: []` (empty PHP array → JSON array). Writing named
            // properties onto an array is silently dropped by
            // JSON.stringify, which made every edit to such blocks a no-op
            // in BOTH the live update (render-block payload) and the save
            // payload. Normalize to a plain object before the first write;
            // storage is only rewritten through an explicit save.
            if (!this.selectedBlock.props || typeof this.selectedBlock.props !== 'object' || Array.isArray(this.selectedBlock.props)) {
                this.selectedBlock.props = {};
            }
            this.selectedBlock.props[propKey] = value;
            this.historyEngine?.record({
                type: 'value', targetId, path: ['props', propKey], before, after: value,
                selectionBefore: targetId, selectionAfter: targetId,
                coalesceKey: options?.coalesceKey === undefined ? `prop:${targetId}:${propKey}` : options.coalesceKey,
            });
            this.historyVersion++;
            this.isDirty = true;
            const fastPathKeys = FAST_PATH_PROP_KEYS.get(this.selectedBlock.type);
            if (FAST_PATH_BLOCK_TYPES.has(this.selectedBlock.type)
                && (!fastPathKeys || fastPathKeys.has(propKey))) {
                this.pushFastPropUpdate(targetId, propKey, value);
            } else {
                this.schedulePropUpdate();
            }
        },

        // Phase 2B-R: link-typed fields. Preserves a structured link object
        // ({url,target,nofollow,sponsored,ariaLabel}) when one exists;
        // plain legacy URL strings stay plain strings.
        updateLinkProp(propKey, url) {
            if (!this.selectedBlock) return;
            const cur = this.selectedBlock.props?.[propKey];
            if (cur && typeof cur === 'object' && !Array.isArray(cur)) {
                this.updateProp(propKey, { ...cur, url });
            } else {
                this.updateProp(propKey, url);
            }
        },

        updateLinkTarget(propKey, target) {
            if (!this.selectedBlock) return;
            const cur = this.selectedBlock.props?.[propKey];
            if (cur && typeof cur === 'object' && !Array.isArray(cur)) {
                this.updateProp(propKey, { ...cur, target });
            }
        },

        // ── Phase 0.1 / 0.2b: schema-driven repeater ────────────────────
        // Driven only by the normalized `fields` contract each repeater
        // declares (InspectorFieldRegistry). A list is addressed by a path:
        // [propKey] for the prop's own list, [propKey, index, fieldKey, …]
        // for a list nested inside one of its items (CanvasListPath), always
        // read against the root prop's schema. Every write replaces the root
        // prop through one updateProp(), so undo/redo, dirty state and live
        // preview behave exactly like any scalar prop; items are copied,
        // never mutated in place, and unknown keys on an item are preserved.
        repeaterPath(path) {
            return Array.isArray(path) ? path : [path];
        },
        repeaterListId(path) {
            return this.repeaterPath(path).join('-');
        },
        repeaterItems(path, rootDef) {
            const [rootKey, ...rest] = this.repeaterPath(path);
            return CanvasListPath.itemsAt(this.selectedBlock?.props?.[rootKey], rootDef, rest);
        },
        repeaterDef(path, rootDef) {
            return CanvasListPath.defAt(rootDef, this.repeaterPath(path).slice(1));
        },
        // Structural edits (add/remove/move/duplicate) are always an undo
        // step of their own; field edits pass a key so typing coalesces.
        persistRepeaterItems(path, rootDef, items, coalesceKey = null) {
            const [rootKey, ...rest] = this.repeaterPath(path);
            const root = CanvasListPath.replaceAt(this.selectedBlock?.props?.[rootKey], rootDef, rest, items);
            if (root === null) return false;
            this.updateProp(rootKey, rootDef?.storage === 'json' ? JSON.stringify(root) : root, { coalesceKey });
            return true;
        },
        // Stable `_id` when the item has one (every item created here does);
        // legacy items keep a positional key and are never rewritten just to
        // gain an id — same rule as InspectorFieldRegistry::compatibilityItemId.
        repeaterItemKey(item, index) {
            return (typeof item?._id === 'string' && /^[A-Za-z0-9_-]{1,80}$/.test(item._id)) ? item._id : `legacy-${index}`;
        },
        newRepeaterId() {
            return 'item_' + (globalThis.crypto?.randomUUID?.().replaceAll('-', '') ?? (Date.now().toString(36) + Math.random().toString(36).slice(2)));
        },
        newRepeaterItem(fields) {
            const item = { _id: this.newRepeaterId() };
            for (const [key, def] of Object.entries(fields ?? {})) {
                item[key] = def?.default ?? ({ toggle: false, number: 0, range: 0, repeater: [], array: [] }[this.fieldControl(def)] ?? '');
            }
            return item;
        },
        addRepeaterItem(path, rootDef) {
            const items = this.repeaterItems(path, rootDef).slice();
            const item = this.newRepeaterItem(this.repeaterDef(path, rootDef)?.fields);
            items.push(item);
            if (this.persistRepeaterItems(path, rootDef, items)) this.setRepeaterItemOpen(path, item, items.length - 1, true);
        },
        duplicateRepeaterItem(path, rootDef, index) {
            const items = this.repeaterItems(path, rootDef).slice();
            if (items[index] === undefined) return;
            const copy = CanvasListPath.cloneWithFreshIds(items[index], () => this.newRepeaterId());
            if (CanvasListPath.isItem(copy)) copy._id = this.newRepeaterId();
            items.splice(index + 1, 0, copy);
            if (this.persistRepeaterItems(path, rootDef, items)) this.setRepeaterItemOpen(path, copy, index + 1, true);
        },
        removeRepeaterItem(path, rootDef, index) {
            const items = this.repeaterItems(path, rootDef).slice();
            if (items[index] === undefined) return;
            items.splice(index, 1);
            this.persistRepeaterItems(path, rootDef, items);
        },
        moveRepeaterItem(path, rootDef, index, direction) {
            const items = this.repeaterItems(path, rootDef).slice();
            const target = index + direction;
            if (items[index] === undefined || target < 0 || target >= items.length) return;
            const wasOpen = this.isRepeaterItemOpen(path, items[index], index);
            [items[index], items[target]] = [items[target], items[index]];
            if (this.persistRepeaterItems(path, rootDef, items)) this.setRepeaterItemOpen(path, items[target], target, wasOpen);
        },
        updateRepeaterField(path, rootDef, index, fieldKey, value) {
            const items = this.repeaterItems(path, rootDef).slice();
            if (!CanvasListPath.isItem(items[index])) return;
            const coalesceKey = `repeater:${this.repeaterOpenKey(path, items[index], index)}:${fieldKey}`;
            items[index] = { ...items[index], [fieldKey]: value };
            this.persistRepeaterItems(path, rootDef, items, coalesceKey);
        },
        // Same object-preserving rule as updateLinkProp().
        updateRepeaterLink(path, rootDef, index, fieldKey, url) {
            const cur = this.repeaterItems(path, rootDef)[index]?.[fieldKey];
            const value = CanvasListPath.isItem(cur) ? { ...cur, url } : url;
            this.updateRepeaterField(path, rootDef, index, fieldKey, value);
        },
        // The chosen URL is delivered only if the same block is still
        // selected when the library returns.
        pickRepeaterMedia(path, rootDef, index, fieldKey) {
            const blockId = this.selectedBlock?.id;
            this.openMediaModal((url) => {
                if (!this.selectedBlock || this.selectedBlock.id !== blockId) return;
                this.updateRepeaterField(path, rootDef, index, fieldKey, url);
            });
        },
        // Collapse state is editor-only UI and never persisted. It is keyed
        // by the item identities along the path, so a nested item keeps its
        // state when its parent is reordered. The first item of every list
        // starts open so its fields are discoverable.
        repeaterOpenKey(path, item, index) {
            const [rootKey, ...rest] = this.repeaterPath(path);
            const parts = [rootKey];
            let items = CanvasListPath.parse(this.selectedBlock?.props?.[rootKey]);
            for (let i = 0; i < rest.length; i += 2) {
                const parent = items[rest[i]];
                parts.push(this.repeaterItemKey(parent, rest[i]), rest[i + 1]);
                items = CanvasListPath.isItem(parent) ? CanvasListPath.parse(parent[rest[i + 1]]) : [];
            }
            return `${this.selectedBlock?.id}:${parts.join('/')}:${this.repeaterItemKey(item, index)}`;
        },
        isRepeaterItemOpen(path, item, index) {
            const state = this.repeaterOpen[this.repeaterOpenKey(path, item, index)];
            return state === undefined ? index === 0 : state;
        },
        setRepeaterItemOpen(path, item, index, open) {
            this.repeaterOpen = { ...this.repeaterOpen, [this.repeaterOpenKey(path, item, index)]: open };
        },
        toggleRepeaterItem(path, item, index) {
            this.setRepeaterItemOpen(path, item, index, !this.isRepeaterItemOpen(path, item, index));
        },
        repeaterItemTitle(schemaDef, item, index) {
            for (const [key, def] of Object.entries(schemaDef?.fields ?? {})) {
                if (this.fieldControl(def) !== 'text') continue;
                const v = item?.[key];
                if (typeof v === 'string' && v.trim() !== '') return v.trim().length > 40 ? v.trim().slice(0, 40) + '…' : v.trim();
            }
            return `${schemaDef?.item_label || 'مورد'} ${this.faDigits(index + 1)}`;
        },
        // A list whose items are one short text each (pricing features)
        // edits inline, one row per item, instead of as collapsible cards.
        repeaterCompactField(schemaDef) {
            const entries = Object.entries(schemaDef?.fields ?? {});
            // An icon field always gets the icon picker (Phase 0.6).
            return entries.length === 1 && this.fieldControl(entries[0][1]) === 'text' ? entries[0][0] : null;
        },
        // show_if: 'flag' (a truthy field) or, Phase 3b, 'kind=product' /
        // 'kind=a|b' (the field equals one of the values; empty = its default).
        repeaterFieldVisible(fieldDef, item, fields = null) {
            const rule = fieldDef?.show_if;
            if (!rule) return true;
            const eq = String(rule).indexOf('=');
            if (eq < 0) return this.isTruthy(item?.[rule]);
            const key = String(rule).slice(0, eq);
            let value = item?.[key];
            if (value === undefined || value === null || value === '') value = fields?.[key]?.default ?? '';
            return String(rule).slice(eq + 1).split('|').includes(String(value));
        },
        fieldControl(def) {
            const raw = def?.control ?? def?.type ?? 'text';
            return ({ string: 'text', boolean: 'toggle', image: 'media' })[raw] ?? raw;
        },
        // Nested lists are editable up to the registry's own depth cap
        // (InspectorFieldRegistry maxDepth ≤ 3: depths 0, 1 and 2).
        isSupportedRepeaterControl(def, depth = 0) {
            const control = this.fieldControl(def);
            if (control === 'repeater') return depth < 2;
            return ['text', 'icon', 'textarea', 'rich_text', 'number', 'range', 'toggle', 'select', 'color', 'link', 'media'].includes(control);
        },
        // Client mirror of HtmlSanitizer::isValidUrl(), so the author sees
        // a link the renderer will refuse before it is published.
        isUnsafeLink(value) {
            const url = (CanvasListPath.isItem(value) ? value.url : value);
            if (typeof url !== 'string' || url.trim() === '') return false;
            const t = url.trim();
            if (/^[\s\x00-\x1f]*(javascript|data|vbscript):/i.test(t)) return true;
            if (!/^[a-z][a-z0-9+.\-]*:/i.test(t)) return false;
            return !/^(https?|mailto|tel):/i.test(t);
        },
        // Accepts every options shape the registry uses: a value=>label map,
        // a plain list of values, or a list of {value,label} objects.
        selectOptions(def) {
            const o = def?.options;
            if (Array.isArray(o)) {
                return o.map(x => (x && typeof x === 'object')
                    ? { value: String(x.value ?? ''), label: String(x.label ?? x.value ?? '') }
                    : { value: String(x), label: String(x) });
            }
            if (o && typeof o === 'object') return Object.entries(o).map(([value, label]) => ({ value: String(value), label: String(label) }));
            return [];
        },
        // <input type=color> only understands #rrggbb; rgba()/var()/''
        // stay editable through the paired text input.
        colorInputValue(value, fallback) {
            // A swatch or rgba() value shows its base colour in the native picker.
            const palette = this.colorPalette();
            return window.CanvasColor.parse(value, palette).hex
                || window.CanvasColor.parse(fallback, palette).hex
                || '#000000';
        },
        // Phase 0.5 (ADR-064): colour control — the value model is the
        // shared window.CanvasColor (theme-runtime/resources/js/canvas-color.js).
        colorPalette() { return window.CanvasColor.palette(this.globalStyles); },
        colorParse(value) { return window.CanvasColor.parse(value, this.colorPalette()); },
        colorPreview(value) { return window.CanvasColor.previewCss(value, this.colorPalette()); },
        colorInputHex(value) { return window.CanvasColor.inputHex(value, this.colorPalette()); },
        setColorSwatch(propKey, token) {
            const current = this.colorParse(this.selectedBlock?.props?.[propKey] ?? '');
            const alpha = ['token', 'hex', 'rgba'].includes(current.kind) ? current.alpha : 100;
            this.updateProp(propKey, window.CanvasColor.swatch(token, alpha));
        },
        setColorBase(propKey, hex) {
            const current = this.colorParse(this.selectedBlock?.props?.[propKey] ?? '');
            const alpha = ['token', 'hex', 'rgba'].includes(current.kind) ? current.alpha : 100;
            this.updateProp(propKey, window.CanvasColor.fixed(hex, alpha));
        },
        setColorAlpha(propKey, alpha) {
            this.updateProp(propKey, window.CanvasColor.withAlpha(this.selectedBlock?.props?.[propKey] ?? '', alpha, this.colorPalette()));
        },
        clampNumber(raw, def) {
            if (raw === '' || raw === null || raw === undefined) return '';
            let n = Number(raw);
            if (!Number.isFinite(n)) return '';
            if (def?.min !== undefined && def?.min !== null) n = Math.max(Number(def.min), n);
            if (def?.max !== undefined && def?.max !== null) n = Math.min(Number(def.max), n);
            return n;
        },
        isTruthy(v) {
            return v === true || v === 1 || v === '1' || v === 'true';
        },
        faDigits(v) {
            return String(v).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        },

        // ── TB3-S2: Per-block style overrides (TB5: breakpoint-aware) ─
        updateStyle(styleKey, value) {
            if (!this.selectedBlock) return;
            const targetId = this.selectedBlock.id;
            const bp = this.activeBreakpoint;
            const path = bp === 'desktop' ? ['style', styleKey] : ['responsive', bp, styleKey];
            const before = bp === 'desktop'
                ? this.selectedBlock.style?.[styleKey]
                : this.selectedBlock.responsive?.[bp]?.[styleKey];
            // Same legacy-shape guard as updateProp: an array-typed `style`
            // or `responsive` bag would silently drop named keys on JSON
            // serialization (Phase 2C: the responsive bag was previously not
            // normalized — seeded blocks lost breakpoint overrides on save).
            if (!this.selectedBlock.style || typeof this.selectedBlock.style !== 'object' || Array.isArray(this.selectedBlock.style)) {
                this.selectedBlock.style = {};
            }
            if (!this.selectedBlock.responsive || typeof this.selectedBlock.responsive !== 'object' || Array.isArray(this.selectedBlock.responsive)) {
                this.selectedBlock.responsive = {};
            }
            if (bp === 'desktop') {
                if (!this.selectedBlock.style) this.selectedBlock.style = {};
                if (value === '' || value === null) {
                    delete this.selectedBlock.style[styleKey];
                    if (Object.keys(this.selectedBlock.style).length === 0) delete this.selectedBlock.style;
                } else {
                    this.selectedBlock.style[styleKey] = value;
                }
            } else {
                if (!this.selectedBlock.responsive) this.selectedBlock.responsive = {};
                if (!this.selectedBlock.responsive[bp]) this.selectedBlock.responsive[bp] = {};
                if (value === '' || value === null) {
                    delete this.selectedBlock.responsive[bp][styleKey];
                    if (Object.keys(this.selectedBlock.responsive[bp]).length === 0) delete this.selectedBlock.responsive[bp];
                    if (Object.keys(this.selectedBlock.responsive).length === 0) delete this.selectedBlock.responsive;
                } else {
                    this.selectedBlock.responsive[bp][styleKey] = value;
                }
            }
            const after = bp === 'desktop'
                ? this.selectedBlock.style?.[styleKey]
                : this.selectedBlock.responsive?.[bp]?.[styleKey];
            this.historyEngine?.record({
                type: 'value', targetId, path, before, after,
                selectionBefore: targetId, selectionAfter: targetId,
                coalesceKey: `style:${targetId}:${bp}:${styleKey}`,
            });
            this.historyVersion++;
            this.isDirty = true;
            this.schedulePropUpdate();
        },

        resetBlockStyle() {
            if (!this.selectedBlock) return;
            const bp = this.activeBreakpoint;
            if (bp === 'desktop') {
                delete this.selectedBlock.style;
            } else {
                if (this.selectedBlock.responsive?.[bp]) {
                    delete this.selectedBlock.responsive[bp];
                    if (Object.keys(this.selectedBlock.responsive).length === 0) delete this.selectedBlock.responsive;
                }
            }
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        // ── Page Management ───────────────────────────────────────
        // Phase 3A — Section Library boundary (Step 19): the insertion
        // flow lives in the section-library partial; the editor only
        // opens the modal and hands the library the canonical op call.
        openSectionLibrary(kind = 'section') {
            this.sectionLibrary.kind = kind;
            this.sectionLibrary.search = '';
            this.showSectionLibrary = true;
        },
        // The library offers compositions for the ACTIVE zone: header
        // compositions while editing the header zone, footer while editing
        // the footer, sections on pages.
        sectionLibraryZone() {
            return this.activeTarget === 'header' ? 'header'
                : this.activeTarget === 'footer' ? 'footer' : 'section';
        },
        sectionLibraryItems() {
            return window.CANVAS_SECTION_LIBRARY
                ? window.CANVAS_SECTION_LIBRARY.list(this.sectionLibrary.kind, this.sectionLibrary.search)
                : [];
        },
        insertSection(key) {
            try {
                window.CANVAS_SECTION_LIBRARY.insert(this, key); // canonical insertNode inside
                this.showSectionLibrary = false;
            } catch (e) {
                this.showToast(e?.message ?? 'خطا در درج بخش', 'error');
            }
        },

        async switchTarget(target, pageKey = null) {
            if (this.switchingTarget || (target === this.activeTarget && (!pageKey || pageKey === this.currentPage))) return;
            this.switchingTarget = true;
            try {
                // Failed saves leave the user on the dirty source zone. The
                // request body captures this active target before awaiting.
                if (this.isDirty && !(await this.saveCurrentPage())) return;
                this.activeTarget = target;
                if (pageKey) this.currentPage = pageKey;
                this.selectBlockById(null);
                this.selectedTab = 'content';
                this.documentState = new CanvasDocumentState(this.blocks);
                this.historyEngine.clear();
                this.historyVersion++;
                this.reloadIframe();
            } finally {
                this.switchingTarget = false;
            }
        },

        async switchPage(key) {
            return this.switchTarget('page', key);
        },

        // Theme-level state, independent from block-tree target switching.
        // Manual save keeps browser synchronization deterministic.
        openGlobalStylesPanel() {
            this.globalStyleDraft = JSON.parse(JSON.stringify(this.globalStyles));
            this.globalStylesDirty = false;
            this.showGlobalStylesPanel = true;
            this.$nextTick(() => document.getElementById('global-style-primary-color')?.focus());
        },

        closeGlobalStylesPanel() {
            this.showGlobalStylesPanel = false;
        },

        async saveGlobalStyles() {
            if (this.globalStylesSaving || !this.globalStylesDirty) return false;
            this.globalStylesSaving = true;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/canvas/global-styles`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ styles: this.globalStyleDraft })
                });
                const d = await r.json();
                if (!r.ok || !d.success) {
                    this.showToast(d.message ?? d.error ?? 'خطا در ذخیره استایل‌ها', 'error');
                    return false;
                }
                this.globalStyles = JSON.parse(JSON.stringify(d.styles));
                this.globalStyleDraft = JSON.parse(JSON.stringify(d.styles));
                this.globalStylesDirty = false;
                this.showToast('استایل‌های سراسری ذخیره شد ✓', 'success');
                this.reloadIframe();
                return true;
            } catch (_) {
                this.showToast('خطای شبکه', 'error');
                return false;
            } finally {
                this.globalStylesSaving = false;
            }
        },

        saveActiveContext() {
            return this.showGlobalStylesPanel ? this.saveGlobalStyles() : this.saveCurrentPage();
        },

        deletePage(key) {
            if (!confirm('این صفحه حذف شود؟')) return;
            fetch(`/admin/theme-builder/${THEME_ID}/canvas/pages/${key}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    delete this.pages[key];
                    if (this.currentPage === key) {
                        this.currentPage = Object.keys(this.pages)[0];
                        this.reloadIframe();
                    }
                    this.showToast('صفحه حذف شد.', 'success');
                }
            });
        },

        confirmAddPage() {
            if (!this.newPageTitle.trim()) return;
            fetch(`/admin/theme-builder/${THEME_ID}/canvas/pages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                body: JSON.stringify({ title: this.newPageTitle, slug: this.newPageSlug })
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    this.pages[d.key] = d.page;
                    this.showAddPage  = false;
                    this.newPageTitle = '';
                    this.newPageSlug  = '';
                    this.switchPage(d.key);
                    this.showToast('صفحه افزوده شد.', 'success');
                }
            });
        },

        // ── Extensions (3G-B-6) ─────────────────────────────────────
        // Theme.settings.extension_selections is the ONE truth for which
        // Extension release version this Theme is authored against — never
        // silently bound to an Extension's current_release_id. Every
        // add/remove/change here is an explicit author action, persisted
        // immediately via the same single Canvas save path used for blocks
        // (server-validated in ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections()).
        releasesForProduct(identifier) {
            return AVAILABLE_EXTENSIONS.find(p => p.identifier === identifier)?.releases ?? [];
        },
        selectedExtensionEntries() {
            return Object.entries(this.extensionSelections);
        },
        openExtensionsModal() {
            this.newExtensionProduct = AVAILABLE_EXTENSIONS[0]?.identifier ?? '';
            this.newExtensionVersion = this.releasesForProduct(this.newExtensionProduct)[0]?.version ?? '';
            this.showExtensionsModal = true;
        },
        async addExtensionSelection() {
            if (!this.newExtensionProduct || !this.newExtensionVersion) return;
            this.extensionSelections[this.newExtensionProduct] = { version: this.newExtensionVersion };
            await this.persistExtensionSelections();
        },
        async removeExtensionSelection(identifier) {
            if (!confirm('این افزونه از قالب حذف شود؟ بلوک‌های موجود از این افزونه حفظ می‌شوند اما افزودن بلوک جدید از آن ممکن نخواهد بود.')) return;
            const next = { ...this.extensionSelections };
            delete next[identifier];
            this.extensionSelections = next;
            await this.persistExtensionSelections();
        },
        async persistExtensionSelections() {
            const result = await this.requestCanvasSave('manual');
            if (result.ok) this.showToast('انتخاب افزونه ذخیره شد ✓', 'success');
            else this.showToast(result.error ?? (result.network ? 'خطای شبکه' : 'خطا در ذخیره انتخاب افزونه'), 'error');
        },

        // ── Save & Publish ────────────────────────────────────────
        // P1: the ONLY code that talks to /canvas/save. Runs strictly one at a
        // time (CanvasSaveQueue). Everything it sends — target, page, blocks,
        // extension selections, operation log — is captured synchronously
        // when the run starts, so edits made while the request is in flight
        // are never confused with what was saved:
        //   - only the operations this request carried are acknowledged;
        //     operations queued mid-flight stay pending;
        //   - `isDirty` is recomputed against the live tree, so a mid-flight
        //     edit stays visibly unsaved instead of being marked saved;
        //   - a document switched away from mid-flight is never touched.
        async runCanvasSave() {
            const docState = this.documentState;
            const target = this.activeTarget;
            const sentOperations = [...(docState?.pendingOperations ?? [])];
            const blocksJson = JSON.stringify(this.blocks);
            const snapshot = {
                target,
                page_key: target === 'page' ? this.currentPage : undefined,
                blocks: JSON.parse(blocksJson),
                extension_selections: JSON.parse(JSON.stringify(this.extensionSelections ?? {})),
            };
            const send = async (operations) => {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/canvas/save`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ ...snapshot, structural_operations: operations }),
                });
                return { r, d: await r.json().catch(() => ({})) };
            };

            this.saveInFlight = true;
            try {
                let { r, d } = await send(sentOperations);
                // Phase 2C hardening: a replay-mismatched operation log falls
                // back ONCE to an operations-free save. The server still runs
                // its full implicit reconciler (every placement/locked guard)
                // — this only forgives an incomplete local operation log, it
                // never weakens server-side validation. The rejected log is
                // dropped either way; operations queued mid-flight are kept.
                if ((!r.ok || !d.success) && d.structure_errors && docState) {
                    const sent = new Set(sentOperations);
                    docState.pendingOperations = docState.pendingOperations.filter(op => !sent.has(op));
                    ({ r, d } = await send(undefined));
                }
                if (!r.ok || !d.success) return { ok: false, error: d.error ?? null };

                const stillCurrent = this.documentState === docState;
                docState?.markSavedSnapshot(snapshot.blocks, sentOperations, stillCurrent ? this.blocks : docState.tree);
                if (stillCurrent) {
                    this.isDirty = JSON.stringify(this.blocks) !== blocksJson
                        || (docState?.pendingOperations.length ?? 0) > 0;
                }
                return { ok: true };
            } catch (_) {
                return { ok: false, error: null, network: true };
            } finally {
                this.saveInFlight = false;
            }
        },

        // `kind` only decides which indicator the request shows ('manual' →
        // Save button, 'background' → toolbar spinner). Editing is never
        // blocked: requests made during a save are queued and coalesced.
        async requestCanvasSave(kind) {
            const counter = kind === 'manual' ? 'manualSaveRequests' : 'backgroundSaveRequests';
            this[counter]++;
            try {
                return await this.saveQueue.request();
            } finally {
                this[counter]--;
            }
        },

        async saveCurrentPage() {
            const result = await this.requestCanvasSave('manual');
            if (result.ok) {
                this.showToast('ذخیره شد ✓', 'success');
                return true;
            }
            this.showToast(result.error ?? (result.network ? 'خطای شبکه' : 'خطا در ذخیره'), 'error');
            return false;
        },

        // Silent auto-save (no success toast) used before iframe reload
        async autoSave() {
            const result = await this.requestCanvasSave('background');
            if (!result.ok) this.showToast(result.error ?? (result.network ? 'خطای شبکه' : 'خطا در ذخیره'), 'error');
            return result.ok;
        },

        async autoSaveAndReload() {
            await this.autoSave();
            this.reloadIframe();
        },

        async publishTheme() {
            if (this.publishing) return;
            // Never publish while the latest edits failed to save.
            if (!(await this.saveCurrentPage())) return;
            this.publishing = true;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/publish`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.success ?? r.ok) {
                    this.themeStatus = 'published';
                    this.showToast('قالب منتشر شد ✓', 'success');
                }
                else this.showToast(d.message ?? 'خطا', 'error');
            } catch { this.showToast('خطای شبکه', 'error'); }
            finally   { this.publishing = false; }
        },

        // ── UI-C2E: Unpublish ──────────────────────────────────────
        async unpublishTheme() {
            if (this.unpublishing) return;
            if (!confirm('این تم از حالت انتشار خارج شود؟ نسخه‌های منتشرشده تجاری (ThemeRelease) دست‌نخورده باقی می‌مانند.')) return;
            this.unpublishing = true;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/unpublish`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.success ?? r.ok) {
                    this.themeStatus = 'draft';
                    this.showToast('از حالت انتشار خارج شد ✓', 'success');
                } else {
                    this.showToast(d.message ?? 'خطا', 'error');
                }
            } catch { this.showToast('خطای شبکه', 'error'); }
            finally { this.unpublishing = false; }
        },

        // ── UI-C2D: Checkpoints (master draft history) ─────────────
        async openCheckpointsModal() {
            this.showCheckpointsModal = true;
            this.newCheckpointLabel = '';
            await this.fetchCheckpoints();
        },
        async fetchCheckpoints() {
            this.loadingCheckpoints = true;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/revisions`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN }
                });
                const d = await r.json();
                this.checkpoints = d.revisions ?? [];
            } catch { this.checkpoints = []; }
            finally { this.loadingCheckpoints = false; }
        },
        async createCheckpoint() {
            if (this.creatingCheckpoint) return;
            // A checkpoint must capture the CURRENT draft, including whatever
            // is being edited right now — save first, exactly like publish does.
            await this.saveCurrentPage();
            this.creatingCheckpoint = true;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/revision`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ label: this.newCheckpointLabel || undefined })
                });
                const d = await r.json();
                if (d.success) {
                    this.newCheckpointLabel = '';
                    this.showToast('چک‌پوینت ذخیره شد ✓', 'success');
                    await this.fetchCheckpoints();
                } else {
                    this.showToast('خطا در ذخیره چک‌پوینت', 'error');
                }
            } catch { this.showToast('خطای شبکه', 'error'); }
            finally { this.creatingCheckpoint = false; }
        },
        async restoreCheckpoint(rev) {
            if (this.restoringCheckpointId) return;
            if (!confirm(`وضعیت فعلی با «${rev.label}» جایگزین شود؟ تغییرات ذخیره‌نشده فعلی از بین می‌روند.`)) return;
            this.restoringCheckpointId = rev.id;
            try {
                const r = await fetch(`/admin/theme-revisions/${rev.id}/restore`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.success ?? r.ok) {
                    this.showToast('چک‌پوینت بازیابی شد — در حال بارگذاری مجدد...', 'success');
                    window.location.reload();
                } else {
                    this.showToast(d.message ?? 'خطا در بازیابی', 'error');
                }
            } catch { this.showToast('خطای شبکه', 'error'); }
            finally { this.restoringCheckpointId = null; }
        },
        formatCheckpointDate(iso) {
            try { return new Date(iso).toLocaleString('fa-IR'); } catch { return iso; }
        },

        // ── UI-C2E: Clone ───────────────────────────────────────────
        openCloneModal() {
            this.cloneName = 'کپی ' + {{ \Illuminate\Support\Js::from($theme->name) }};
            this.cloneSiteId = '';
            this.showCloneModal = true;
        },
        async cloneThemeNow() {
            if (this.cloning || !this.cloneName) return;
            this.cloning = true;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/clone`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ name: this.cloneName, site_id: this.cloneSiteId || undefined })
                });
                const d = await r.json();
                if (d.success && d.canvas_url) {
                    this.showToast('تم کپی شد — در حال انتقال...', 'success');
                    window.location.href = d.canvas_url;
                } else {
                    this.showToast(d.error ?? d.message ?? 'خطا در کپی تم', 'error');
                }
            } catch { this.showToast('خطای شبکه', 'error'); }
            finally { this.cloning = false; }
        },

        // ── UI-C2E: Import (creates a NEW theme, then navigates to it) ──
        async importThemeFile(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file || this.importing) return;
            this.importing = true;
            const fd = new FormData();
            fd.append('file', file);
            try {
                const r = await fetch(`/admin/theme-builder/import`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: fd
                });
                const d = await r.json();
                if (d.success && d.canvas_url) {
                    this.showToast('تم وارد شد — در حال انتقال...', 'success');
                    window.location.href = d.canvas_url;
                } else {
                    this.showToast(d.error ?? 'خطا در درون‌ریزی فایل', 'error');
                }
            } catch { this.showToast('خطای شبکه', 'error'); }
            finally { this.importing = false; }
        },

        openPreview() {
            window.open(`/admin/theme-builder/${THEME_ID}/canvas/${this.currentPage}/preview?target=${encodeURIComponent(this.activeTarget)}`, '_blank');
        },

        // ── TB3-02: Live Iframe ───────────────────────────────────
        livePreviewUrl() {
            return `/admin/theme-builder/${THEME_ID}/canvas/live-preview?target=${encodeURIComponent(this.activeTarget)}&page=${encodeURIComponent(this.currentPage)}&t=${this.iframeTimestamp}`;
        },

        reloadIframe() {
            this.iframeLoading = true;
            this.iframeReady   = false;
            this.iframeTimestamp = Date.now();
        },

        getIframe() {
            return document.getElementById('tb-live-iframe');
        },

        sendToIframe(msg) {
            const iframe = this.getIframe();
            if (iframe && iframe.contentWindow) {
                try { ThemeCanvasRuntime.post(iframe.contentWindow, msg.action, Object.fromEntries(Object.entries(msg).filter(([key]) => key !== 'action'))); } catch (_) {}
            }
        },

        pushFastPropUpdate(blockId, propKey, value) {
            const message = { action: 'setProp', blockId, propKey, value };
            if (!this.iframeReady) {
                const pending = this.pendingFastPropUpdates.findIndex(
                    update => update.blockId === blockId && update.propKey === propKey
                );
                if (pending === -1) this.pendingFastPropUpdates.push(message);
                else this.pendingFastPropUpdates[pending] = message;
                return;
            }
            this.sendToIframe(message);
        },

        flushFastPropUpdates() {
            const pending = this.pendingFastPropUpdates.splice(0);
            pending.forEach(message => this.sendToIframe(message));
        },

        onIframeLoad() {
            // Give bridge script ~100ms to attach
            setTimeout(() => { this.iframeLoading = false; }, 150);
        },

        // Debounced per-block re-render on prop change
        schedulePropUpdate() {
            clearTimeout(this.propUpdateTimer);
            this.propUpdateTimer = setTimeout(() => this.pushPropUpdate(), 350);
        },

        async pushPropUpdate() {
            if (!this.selectedBlock) return;
            // Phase 2B-R acceptance fix: never silently drop an early edit.
            // If the iframe bridge has not completed its ready handshake yet,
            // remember the pending update and flush it when 'ready' arrives;
            // a bail-and-forget here made first-second edits vanish.
            if (!this.iframeReady) { this.pendingPropUpdate = true; return; }
            try {
                const blockPayload = JSON.parse(JSON.stringify(this.selectedBlock)); // includes .style if set
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/canvas/render-block`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ block: blockPayload })
                });
                const d = await r.json();
                if (d.html) {
                    this.sendToIframe({ action: 'updateBlock', blockId: this.selectedBlock.id, html: d.html });
                }
            } catch (_) {}
        },

        // Handle messages from iframe
        handleIframeMessage(e) {
            if (!ThemeCanvasRuntime.sameOrigin(e.origin) || e.source !== this.getIframe()?.contentWindow) return;
            const d = e.data;
            if (!ThemeCanvasRuntime.valid(d, 'to-editor')) return;

            switch (d.action) {
                case 'ready':
                    this.iframeReady   = true;
                    this.iframeLoading = false;
                    this.flushFastPropUpdates();
                    if (this.pendingPropUpdate) { this.pendingPropUpdate = false; this.pushPropUpdate(); }
                    if (this.selectedBlock) {
                        this.sendToIframe({ action: 'select', blockId: this.selectedBlock.id });
                    }
                    break;

                case 'iframeHeight': {
                    const iframe = this.getIframe();
                    if (iframe) iframe.style.height = Math.max(400, d.height || 400) + 'px';
                    break;
                }

                case 'blockSelected': {
                    if (CanvasIdentity.findById(this.blocks, d.blockId)) {
                        this.selectBlockById(d.blockId);
                        this.selectedTab = 'content';
                    }
                    break;
                }

                case 'dropIndexResult':
                    this.dropTargetIndex      = d.index ?? this.blocks.length;
                    this.dropTargetBeforeId   = d.beforeId ?? '__end__';
                    this.dragTargetContainerId = d.containerId ?? null;
                    this.sendToIframe({ action: 'setDropTarget', beforeId: this.dropTargetBeforeId });
                    if (this.dragTargetContainerId) {
                        this.sendToIframe({ action: 'setContainerTarget', containerId: this.dragTargetContainerId });
                    }
                    break;

                case 'dragStart':
                    this.structuralDrag = { nodeId: d.nodeId, valid: false };
                    this.selectBlockById(d.nodeId);
                    break;

                case 'dragOver': {
                    let valid = false;
                    try {
                        const preview = JSON.parse(JSON.stringify(this.blocks));
                        const validator = new CanvasTreeOperations(BLOCK_SCHEMAS);
                        validator.moveNode(preview, d.nodeId, d.targetParentId, d.targetIndex);
                        valid = true;
                    } catch (_) {}
                    this.structuralDrag = { nodeId: d.nodeId, targetParentId: d.targetParentId, targetIndex: d.targetIndex, position: d.position, valid };
                    this.sendToIframe({ action: 'setStructuralDrop', targetId: d.targetParentId, position: d.position, valid });
                    break;
                }

                case 'dropNode':
                    if (this.structuralDrag?.valid && this.structuralDrag.nodeId === d.nodeId) {
                        this.performMove(d.nodeId, d.targetParentId, d.targetIndex);
                    } else {
                        this.structuralError(new Error('invalid-parent-capability'));
                    }
                    this.structuralDrag = null;
                    break;

                case 'dragCancel':
                    this.structuralDrag = null;
                    break;

                // TB3-04: inline edit prop change from iframe
                case 'inlinePropChange':
                    if (this.selectedBlock && d.propKey) {
                        this.selectedBlock.props[d.propKey] = d.value ?? '';
                        this.isDirty = true;
                        if (d.final) {
                            clearTimeout(this.inlineAutoSaveTimer);
                            this.inlineAutoSaveTimer = setTimeout(() => this.autoSave(), 1500);
                        }
                    }
                    break;
            }
        },

        // TB3-05: gradient toggle
        toggleGradient(enable) {
            if (enable) {
                if (!this.selectedBlock.style) this.selectedBlock.style = {};
                this.selectedBlock.style.bgGradientFrom = this.selectedBlock.style.bgGradientFrom || '#4f46e5';
                this.selectedBlock.style.bgGradientTo   = this.selectedBlock.style.bgGradientTo   || '#7c3aed';
                this.isDirty = true;
                this.schedulePropUpdate();
            } else {
                this.updateStyle('bgGradientFrom', '');
                this.updateStyle('bgGradientTo', '');
                this.updateStyle('bgGradientDir', '');
            }
        },

        // TB3-07: Media Manager
        openMediaPicker(propKey) {
            this.mediaModal.targetPropKey = propKey;
            this.mediaModal.onSelect = null;
            this.mediaModal.show  = true;
            this.mediaModal.tab   = 'library';
            this.mediaModal.uploadDone = false;
            this.fetchMedia();
        },

        // Nested repeater media fields: opens the same media library but
        // delivers the chosen URL to a callback instead of a top-level prop
        // key, so the caller decides which item receives it (see
        // pickRepeaterMedia()).
        openMediaModal(onSelect) {
            this.mediaModal.targetPropKey = null;
            this.mediaModal.onSelect = typeof onSelect === 'function' ? onSelect : null;
            this.mediaModal.show  = true;
            this.mediaModal.tab   = 'library';
            this.mediaModal.uploadDone = false;
            this.fetchMedia();
        },

        async fetchMedia() {
            this.mediaModal.loading = true;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/media`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN }
                });
                const d = await r.json();
                this.mediaModal.images = d.images ?? [];
            } catch (_) { this.mediaModal.images = []; }
            finally { this.mediaModal.loading = false; }
        },

        selectMedia(url) {
            if (typeof this.mediaModal.onSelect === 'function') {
                const deliver = this.mediaModal.onSelect;
                this.mediaModal.onSelect = null;
                this.mediaModal.show = false;
                deliver(url);
                return;
            }
            if (this.mediaModal.targetPropKey && this.selectedBlock) {
                this.updateProp(this.mediaModal.targetPropKey, url);
            }
            this.mediaModal.show = false;
        },

        async deleteMedia(url) {
            if (!confirm('این تصویر حذف شود؟')) return;
            await fetch(`/admin/theme-builder/${THEME_ID}/media`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                body: JSON.stringify({ url })
            });
            this.mediaModal.images = this.mediaModal.images.filter(img => img.url !== url);
        },

        handleMediaDrop(e) {
            const file = e.dataTransfer?.files?.[0];
            if (file && file.type.startsWith('image/')) this.uploadMediaFile(file);
        },

        async uploadMediaFile(file) {
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) { this.showToast('حداکثر ۵ مگابایت', 'error'); return; }
            this.mediaModal.uploading = true;
            const fd = new FormData();
            fd.append('file', file);
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/media/upload`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: fd
                });
                const d = await r.json();
                if (d.url) {
                    this.mediaModal.uploadDone = true;
                    this.mediaModal.images.unshift({ url: d.url, name: d.name ?? file.name });
                } else {
                    this.showToast(d.error ?? d.message ?? 'خطا در آپلود', 'error');
                }
            } catch (_) { this.showToast('خطای شبکه', 'error'); }
            finally { this.mediaModal.uploading = false; }
        },

        setDevice(d) { this.device = d; this.activeBreakpoint = d; },
        toggleInspect() { this.inspectHost?.toggle(); },

        // ── TB3-03: Drag from Library ─────────────────────────────
        startDragBlock(event, blockType) {
            this.draggedBlockType  = blockType;
            this.draggedBlockLabel = BLOCK_LABELS[blockType] ?? blockType;
            this.isDraggingBlock   = true;
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/plain', blockType);
            this.sendToIframe({ action: 'showDropZones', visible: true });
        },

        startPointerLibraryDrag(event, blockType) {
            if (event.button !== 0 || event.pointerType === 'mouse' && !event.isPrimary) return;
            this.palettePointerDrag = { id: event.pointerId, startX: event.clientX, startY: event.clientY, moved: false };
            this.draggedBlockType = blockType;
            this.draggedBlockLabel = BLOCK_LABELS[blockType] ?? blockType;
            event.currentTarget.setPointerCapture?.(event.pointerId);
        },

        movePointerLibraryDrag(event) {
            if (!this.palettePointerDrag || event.pointerId !== this.palettePointerDrag.id) return;
            const distance = Math.hypot(event.clientX - this.palettePointerDrag.startX, event.clientY - this.palettePointerDrag.startY);
            if (!this.palettePointerDrag.moved && distance < 6) return;
            if (!this.palettePointerDrag.moved) {
                this.palettePointerDrag.moved = true;
                this.isDraggingBlock = true;
                this.sendToIframe({ action: 'showDropZones', visible: true });
            }
            this.onDragOverCanvas(event);
        },

        finishPointerLibraryDrag(event) {
            if (!this.palettePointerDrag || event.pointerId !== this.palettePointerDrag.id) return;
            const moved = this.palettePointerDrag.moved;
            this.palettePointerDrag = null;
            if (moved) {
                event.preventDefault();
                this.suppressPaletteClick = true;
                this.onDropToCanvas(event);
                setTimeout(() => { this.suppressPaletteClick = false; }, 0);
            } else {
                this.draggedBlockType = null;
                this.draggedBlockLabel = null;
            }
        },

        cancelPointerLibraryDrag(event) {
            if (!this.palettePointerDrag || event.pointerId !== this.palettePointerDrag.id) return;
            this.palettePointerDrag = null;
            this.endDragBlock();
        },

        endDragBlock() {
            this.isDraggingBlock       = false;
            this.draggedBlockType      = null;
            this.draggedBlockLabel     = null;
            this.dropTargetIndex       = -1;
            this.dropTargetBeforeId    = null;
            this.dragTargetContainerId = null;
            this.sendToIframe({ action: 'showDropZones', visible: false });
            this.sendToIframe({ action: 'setContainerTarget', containerId: null });
        },

        onDragOverCanvas(event) {
            if (!this.isDraggingBlock) return;
            const iframe = this.getIframe();
            if (iframe) {
                const rect = iframe.getBoundingClientRect();
                const scrollY = iframe.contentWindow?.scrollY ?? 0;
                const relY = event.clientY - rect.top + scrollY;
                const relX = event.clientX - rect.left;
                // TB4: send clientX so iframe can detect container targets
                this.sendToIframe({ action: 'getDropIndex', clientY: relY, clientX: relX });
            }
        },

        onDragLeaveCanvas() {
            this.sendToIframe({ action: 'setDropTarget', beforeId: null });
        },

        onDropToCanvas(event) {
            event.preventDefault();
            const type = this.draggedBlockType || event.dataTransfer.getData('text/plain');
            if (!type) { this.endDragBlock(); return; }
            if (this.dragTargetContainerId) {
                // Drop into a container — find its path and add as child
                const containerPath = this.findBlockPath(this.dragTargetContainerId);
                if (containerPath) {
                    this.addBlock(type, -1, containerPath);
                    this.endDragBlock();
                    return;
                }
            }
            const idx = this.dropTargetIndex >= 0 ? this.dropTargetIndex : this.blocks.length;
            this.addBlock(type, idx);
            this.endDragBlock();
        },

        // ── Helpers ───────────────────────────────────────────────
        blockLabel(type)   { return BLOCK_LABELS[type] ?? type; },
        blockPreview(block) {
            const d = block?.props ?? block?.data ?? {};
            return d.title || d.text || d.content || d.name || d.subtitle || d.label || '';
        },
        blockCategoryColor(type) {
            if (!type) return 'bg-slate-300';
            if (type.startsWith('module:blog'))   return 'bg-blue-500';
            if (type.startsWith('module:shop'))   return 'bg-emerald-500';
            if (type.startsWith('module:course')) return 'bg-violet-500';
            if (type.startsWith('module:form'))   return 'bg-amber-500';
            if (type.startsWith('module:'))       return 'bg-indigo-500';
            if (type === 'hero' || type === 'cta') return 'bg-purple-500';
            if (['section','container','grid','columns','tabs','accordion','slider'].includes(type)) return 'bg-blue-400';
            if (['heading','text','image','gallery','video','button'].includes(type)) return 'bg-green-500';
            if (['countdown','promo_banner'].includes(type)) return 'bg-orange-400';
            return 'bg-slate-400';
        },

        showToast(msg, type = 'success') {
            clearTimeout(this.toast._timer);
            this.toast = { show: true, msg, type, _timer: setTimeout(() => this.toast.show = false, 3000) };
        },

        // TB3-01 + TB4: Normalize blocks — convert data→props on load, recurse into children
        normalizeBlock(block) {
            if (block.data !== undefined && block.props === undefined) {
                block.props = block.data;
                delete block.data;
            }
            if (!block.props) block.props = {};
            // TB4: move legacy block.blocks / block.props.blocks to block.children
            if (!block.children) {
                if (Array.isArray(block.blocks)) {
                    block.children = block.blocks;
                    delete block.blocks;
                } else if (Array.isArray(block.props?.blocks)) {
                    block.children = block.props.blocks;
                    delete block.props.blocks;
                }
            }
            if (Array.isArray(block.children)) {
                block.children = block.children.map(c => this.normalizeBlock(c));
            }
            return block;
        },

        normalizePages(pages) {
            Object.values(pages).forEach(page => {
                if (Array.isArray(page.blocks)) {
                    page.blocks = page.blocks.map(b => this.normalizeBlock(b));
                }
            });
        },

        // ── Init ──────────────────────────────────────────────────
        // TB6: Template Library
        async openTemplateLibrary() {
            this.showTemplateLibrary = true;
            await this.fetchTemplates();
        },
        async fetchTemplates() {
            this.templateLibrary.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.templateLibrary.type)     params.set('type',     this.templateLibrary.type);
                if (this.templateLibrary.category) params.set('category', this.templateLibrary.category);
                if (this.templateLibrary.search)   params.set('search',   this.templateLibrary.search);

                const res = await fetch(`{{ route('admin.theme-builder.canvas.templates', $theme) }}?${params}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                this.templateLibrary.templates = json.templates ?? [];
            } catch (e) {
                this.templateLibrary.templates = [];
            } finally {
                this.templateLibrary.loading = false;
            }
        },
        // Canvas overhaul Unit 5 (ADR-075): a template lands in its own zone
        // (a header template in the header, never in the page body), a
        // header/footer template replaces the current one after
        // confirmation, and the canvas re-renders at once — every other
        // structural insert already saves and reloads the iframe; this one
        // only queued the operations, so nothing appeared until the next edit.
        async insertTemplate(id) {
            if (this.templateLibrary.inserting) return;
            this.templateLibrary.inserting = true;
            try {
                const res = await fetch('{{ route('admin.theme-builder.canvas.insert-template', $theme) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' },
                    body: JSON.stringify({ template_id: id }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.success || !Array.isArray(json.blocks)) {
                    this.showToast(json.message || 'درج قالب ممکن نشد.', 'error');
                    return;
                }
                const zone = ['header', 'footer'].includes(json.zone) ? json.zone : 'page';
                if (this.activeTarget !== zone) {
                    await this.switchTarget(zone);
                    if (this.activeTarget !== zone) return; // the dirty zone failed to save
                }
                if (zone !== 'page' && this.blocks.length > 0) {
                    const label = zone === 'header' ? 'هدر' : 'فوتر';
                    if (!confirm(`${label} فعلی با این قالب جایگزین شود؟ (با «بازگردانی» قابل برگشت است)`)) return;
                    for (const old of [...this.blocks]) {
                        // Legacy/imported chrome can lack an id; structural
                        // operations address nodes by id only.
                        if (typeof old.id !== 'string' || old.id === '') old.id = 'blk_' + Math.random().toString(36).slice(2, 10);
                        const operation = this.treeOperations.deleteNode(this.blocks, old.id);
                        this.documentState.queue(operation);
                        this.recordStructuralHistory('delete', operation, operation.before.node, null, old.id, null);
                    }
                }
                let firstId = null;
                for (const raw of json.blocks) {
                    const block = this.normalizeBlock(JSON.parse(JSON.stringify(raw)));
                    const operation = this.treeOperations.insertNode(this.blocks, block, 'page', this.blocks.length);
                    this.documentState.queue(operation);
                    this.recordStructuralHistory('insert', operation, null, block, this.documentState.selectedNodeId, block.id);
                    firstId ??= block.id;
                }
                this.showTemplateLibrary = false;
                this.selectBlockById(firstId);
                this.isDirty = true;
                await this.autoSaveAndReload();
                this.showToast('قالب درج شد', 'success');
            } catch (e) {
                console.error('[template-insert]', e);
                const structural = ['cycle', 'locked-structure', 'invalid-parent-capability', 'invalid-child-capability', 'max-children', 'max-depth'];
                if (structural.includes(e?.message)) this.structuralError(e);
                else this.showToast('خطا در درج قالب', 'error');
            } finally {
                this.templateLibrary.inserting = false;
            }
        },
        async saveAsTemplate() {
            if (!this.saveTemplateForm.name) return;
            this.saveTemplateForm.saving = true;
            try {
                let blocks = [];
                if (this.saveTemplateForm.scope === 'block' && this.selectedBlock) {
                    blocks = [this.selectedBlock];
                } else {
                    blocks = this.blocks;
                }

                const res = await fetch('{{ route('admin.theme-builder.canvas.save-as-template', $theme) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        name:     this.saveTemplateForm.name,
                        type:     this.saveTemplateForm.type,
                        category: this.saveTemplateForm.category,
                        scope:    this.saveTemplateForm.scope,
                        blocks:   blocks,
                        page_key: this.currentPage,
                    }),
                });
                const json = await res.json();
                if (json.success) {
                    this.showSaveAsTemplate = false;
                    this.saveTemplateForm.name = '';
                    this.showToast('قالب «' + json.template.name + '» ذخیره شد', 'success');
                }
            } catch (e) {
                this.showToast('خطا در ذخیره قالب', 'error');
            } finally {
                this.saveTemplateForm.saving = false;
            }
        },

        init() {
            // TB3-01: Normalize all loaded blocks to use props
            this.normalizePages(this.pages);
            this.headerBlocks = this.headerBlocks.map(block => this.normalizeBlock(block));
            this.footerBlocks = this.footerBlocks.map(block => this.normalizeBlock(block));

            // Phase 2C services keep composition, history, and selection
            // independent from this Blade/Alpine presentation surface.
            this.documentState = new CanvasDocumentState(this.blocks);
            this.treeOperations = new CanvasTreeOperations(BLOCK_SCHEMAS);
            this.navigator = new CanvasNavigator();
            this.historyEngine = new CanvasHistory((entry, side) => this.applyHistoryEntry(entry, side), 100);
            this.saveQueue = new CanvasSaveQueue(() => this.runCanvasSave());

            // P1: leaving while a save is on the wire or queued would drop it.
            // (Ordinary unsaved edits keep their existing visible "ذخیره نشده" cue.)
            window.addEventListener('beforeunload', (e) => {
                if (!this.saveQueue?.busy) return;
                e.preventDefault();
                e.returnValue = '';
            });

            // TB3-02: Listen for postMessage from live preview iframe
            window.addEventListener('message', (e) => this.handleIframeMessage(e));

            // WU-1: Inspect Mode. The preview finds window.TbInspectHost on
            // this window (same origin) and re-applies the state after reloads.
            this.inspectHost = window.TbInspect?.attachHost({
                frame: () => this.getIframe(),
                describe: (type) => ({ label: BLOCK_LABELS[type] || '', schema: BLOCK_SCHEMAS[type]?.schema || null, defaults: BLOCK_SCHEMAS[type]?.defaultProps || null }),
                onChange: (on) => { this.inspectOn = on; if (!on) this.auditSummary = null; },
                onAudit: (summary) => { this.auditSummary = this.inspectOn ? summary : null; },
                // WU-3: a «سلامت ویجت» row selects its block.
                select: (blockId) => { this.selectBlockById(blockId); this.sendToIframe({ action: 'select', blockId }); },
            }) ?? null;

            // TB3-S2: Keyboard shortcuts
            window.addEventListener('keydown', (e) => {
                // Skip shortcuts when editing an input/textarea
                const tag = document.activeElement?.tagName;
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
                if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                    e.preventDefault();
                    this.undo();
                } else if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
                    e.preventDefault();
                    this.redo();
                }
            });

            // Warn on unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) { e.preventDefault(); e.returnValue = ''; }
            });

            // Initial iframe load
            this.iframeLoading = true;
        }
    };
}
</script>
@endpush
