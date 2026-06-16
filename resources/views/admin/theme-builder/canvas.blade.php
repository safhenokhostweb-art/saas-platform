@extends('layouts.canvas')

@section('title', 'قالب‌ساز — ' . $theme->name)

@push('head')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
@endpush

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
    @keydown.ctrl.s.window.prevent="saveCurrentPage()"
>

    {{-- ═══════════════════════════════════════════════════════
         TOOLBAR
    ═══════════════════════════════════════════════════════ --}}
    <div class="h-14 bg-white border-b border-slate-200 flex items-center px-4 gap-3 shrink-0 shadow-sm z-30">

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

        {{-- Page Tabs --}}
        <div class="flex items-center gap-1.5 overflow-x-auto flex-1 min-w-0" style="scrollbar-width:none;">
            <template x-for="(page, key) in pages" :key="key">
                <button
                    @click="switchPage(key)"
                    :class="currentPage === key
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition whitespace-nowrap flex items-center gap-1.5 group relative"
                >
                    <span x-text="page.title"></span>
                    <span
                        x-show="key !== 'home'"
                        @click.stop="deletePage(key)"
                        :class="currentPage === key ? 'text-indigo-200 hover:text-white' : 'text-slate-400 hover:text-red-500'"
                        class="text-xs leading-none transition hidden group-hover:inline"
                        title="حذف صفحه"
                    >×</span>
                </button>
            </template>
            <button
                @click="showAddPage = true"
                class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-indigo-600 transition whitespace-nowrap"
                title="افزودن صفحه جدید"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                صفحه جدید
            </button>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2 shrink-0">
            {{-- Dirty indicator --}}
            <span x-show="isDirty" class="text-amber-500 text-xs font-medium flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse inline-block"></span>
                ذخیره نشده
            </span>

            {{-- Preview in new tab --}}
            <button
                @click="openPreview()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600 text-xs font-medium transition"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                پیش‌نمایش
            </button>

            {{-- Save --}}
            <button
                @click="saveCurrentPage()"
                :disabled="saving"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-xs font-medium transition disabled:opacity-60"
            >
                <svg x-show="!saving" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <svg x-show="saving" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                <span x-text="saving ? 'در حال ذخیره...' : 'ذخیره'"></span>
            </button>

            {{-- Publish --}}
            <button
                @click="publishTheme()"
                :disabled="publishing"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition disabled:opacity-60"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                <span x-text="publishing ? 'انتشار...' : 'انتشار'"></span>
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         MAIN 3-PANEL
    ═══════════════════════════════════════════════════════ --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- ── PANEL A: Block Library (RTL = right side) ─────── --}}
        <div class="w-64 bg-white border-l border-slate-200 flex flex-col overflow-hidden shrink-0 z-20">

            {{-- Library Header --}}
            <div class="px-3 py-2.5 border-b border-slate-100 shrink-0">
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

            {{-- Library Blocks --}}
            <div class="flex-1 overflow-y-auto">
                @php $orderedCats = ['layout','content','dynamic','ecommerce','marketing','utility','module']; @endphp
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
                            'utility'   => 'text-slate-600 bg-slate-100',
                            'module'    => 'text-indigo-700 bg-indigo-50',
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
                                        @click="addBlock('{{ $blockType }}')"
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
            <div x-show="selectedIndex < 0" class="flex flex-col items-center justify-center h-full text-center px-6">
                <div class="text-5xl mb-3 opacity-20">✏️</div>
                <p class="text-slate-400 text-sm">یک بلوک از Canvas کلیک کنید</p>
                <p class="text-slate-300 text-xs mt-1">برای ویرایش خصوصیات</p>
            </div>

            {{-- Selected block props --}}
            <template x-if="selectedIndex >= 0 && selectedBlock">
                <div class="flex flex-col h-full overflow-hidden">

                    {{-- Props Header --}}
                    <div class="px-4 py-3 border-b border-slate-100 shrink-0">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div :class="blockCategoryColor(selectedBlock.type)" class="w-2 h-2 rounded-full"></div>
                                <span class="text-sm font-bold text-slate-800" x-text="blockLabel(selectedBlock.type)"></span>
                            </div>
                            <button @click="selectedIndex = -1; sendToIframe({action:'select', blockId:null})" class="text-slate-400 hover:text-slate-600 text-lg leading-none">×</button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1 font-mono" x-text="selectedBlock.type"></p>
                    </div>

                    {{-- Move Up / Down --}}
                    <div class="flex items-center gap-2 px-4 py-2 border-b border-slate-50 shrink-0">
                        <button @click="moveBlock(selectedIndex, selectedIndex - 1)" :disabled="selectedIndex === 0"
                            class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-30 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                            بالاتر
                        </button>
                        <button @click="moveBlock(selectedIndex, selectedIndex + 1)" :disabled="selectedIndex === blocks.length - 1"
                            class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-30 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            پایین‌تر
                        </button>
                    </div>

                    {{-- Props Form --}}
                    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                        <template x-for="(schemaDef, propKey) in selectedSchema" :key="propKey">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5" x-text="schemaDef.label || propKey"></label>

                                {{-- string --}}
                                <template x-if="schemaDef.type === 'string'">
                                    <input
                                        type="text"
                                        :value="selectedBlock.props[propKey] ?? schemaDef.default ?? ''"
                                        @input="updateProp(propKey, $event.target.value)"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition"
                                        :placeholder="schemaDef.default ?? ''"
                                    >
                                </template>

                                {{-- number --}}
                                <template x-if="schemaDef.type === 'number'">
                                    <input
                                        type="number"
                                        :value="selectedBlock.props[propKey] ?? schemaDef.default ?? 0"
                                        @input="updateProp(propKey, Number($event.target.value))"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition"
                                        :min="0"
                                    >
                                </template>

                                {{-- boolean --}}
                                <template x-if="schemaDef.type === 'boolean'">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <div class="relative">
                                            <input type="checkbox"
                                                :checked="selectedBlock.props[propKey] ?? false"
                                                @change="updateProp(propKey, $event.target.checked)"
                                                class="sr-only peer">
                                            <div class="w-9 h-5 bg-slate-200 peer-checked:bg-indigo-500 rounded-full transition"></div>
                                            <div class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-full rtl:peer-checked:-translate-x-full transition-transform"></div>
                                        </div>
                                        <span class="text-sm text-slate-700" x-text="(selectedBlock.props[propKey] ?? false) ? 'فعال' : 'غیرفعال'"></span>
                                    </label>
                                </template>

                                {{-- select --}}
                                <template x-if="schemaDef.type === 'select'">
                                    <select
                                        :value="selectedBlock.props[propKey] ?? schemaDef.default ?? ''"
                                        @change="updateProp(propKey, $event.target.value)"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition"
                                    >
                                        <template x-for="(label, val) in schemaDef.options" :key="val">
                                            <option :value="val" :selected="(selectedBlock.props[propKey] ?? schemaDef.default) == val" x-text="label"></option>
                                        </template>
                                    </select>
                                </template>

                                {{-- color --}}
                                <template x-if="schemaDef.type === 'color'">
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="color"
                                            :value="selectedBlock.props[propKey] ?? '#4f46e5'"
                                            @input="updateProp(propKey, $event.target.value)"
                                            class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer p-0.5"
                                        >
                                        <input
                                            type="text"
                                            :value="selectedBlock.props[propKey] ?? '#4f46e5'"
                                            @input="updateProp(propKey, $event.target.value)"
                                            class="flex-1 text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 font-mono"
                                            placeholder="#000000"
                                        >
                                    </div>
                                </template>

                                {{-- textarea --}}
                                <template x-if="schemaDef.type === 'textarea'">
                                    <textarea
                                        :value="selectedBlock.props[propKey] ?? schemaDef.default ?? ''"
                                        @input="updateProp(propKey, $event.target.value)"
                                        rows="3"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition resize-y"
                                        :placeholder="schemaDef.default ?? ''"
                                    ></textarea>
                                </template>
                            </div>
                        </template>

                        {{-- No props --}}
                        <div x-show="Object.keys(selectedSchema).length === 0" class="text-center py-6">
                            <p class="text-xs text-slate-400">این بلوک تنظیماتی ندارد</p>
                        </div>
                    </div>

                    {{-- Delete Block --}}
                    <div class="px-4 py-3 border-t border-slate-100 shrink-0">
                        <button
                            @click="removeBlock(selectedIndex)"
                            class="w-full flex items-center justify-center gap-2 py-2 rounded-lg border border-red-200 text-red-500 hover:bg-red-50 text-xs font-medium transition"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            حذف این بلوک
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         ADD PAGE MODAL
    ═══════════════════════════════════════════════════════ --}}
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
<script>
const THEME_ID   = {{ $theme->id }};
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const BLOCK_LABELS  = @json(collect($registry)->mapWithKeys(fn($d,$t)=>[$t=>$d['name']]));
const BLOCK_SCHEMAS = @json(collect($registry)->mapWithKeys(fn($d,$t)=>[$t=>['schema'=>$d['schema']??[],'defaultProps'=>$d['defaultProps']??[]]]));
const INITIAL_PAGES = @json($pages);

function themeCanvas() {
    return {
        // ── State ───────────────────────────────────────────────
        pages:          JSON.parse(JSON.stringify(INITIAL_PAGES)),
        currentPage:    Object.keys(INITIAL_PAGES)[0] ?? 'home',
        selectedIndex:  -1,
        isDirty:        false,
        saving:         false,
        quickSaving:    false,
        publishing:     false,
        showAddPage:    false,
        newPageTitle:   '',
        newPageSlug:    '',
        librarySearch:  '',
        expandedCats:   { layout:true, content:true, module:true, dynamic:false, ecommerce:false, marketing:false, utility:false },
        toast:          { show: false, msg: '', type: 'success', _timer: null },
        // TB3-02: iframe state
        device:         'desktop',
        iframeTimestamp: Date.now(),
        iframeLoading:  true,
        iframeReady:    false,
        propUpdateTimer: null,
        // TB3-03: drag state
        isDraggingBlock:    false,
        draggedBlockType:   null,
        draggedBlockLabel:  null,
        dropTargetIndex:    -1,
        dropTargetBeforeId: null,

        // ── Computed ─────────────────────────────────────────────
        get blocks() {
            return this.pages[this.currentPage]?.blocks ?? [];
        },
        set blocks(val) {
            if (this.pages[this.currentPage]) this.pages[this.currentPage].blocks = val;
        },
        get selectedBlock() {
            if (this.selectedIndex < 0 || this.selectedIndex >= this.blocks.length) return null;
            return this.blocks[this.selectedIndex];
        },
        get selectedSchema() {
            if (!this.selectedBlock) return {};
            return BLOCK_SCHEMAS[this.selectedBlock.type]?.schema ?? {};
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

        // ── Canvas Actions ────────────────────────────────────────
        addBlock(type, atIndex = -1) {
            const defaults = JSON.parse(JSON.stringify(BLOCK_SCHEMAS[type]?.defaultProps ?? {}));
            const block = {
                id: 'blk_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                type,
                props: defaults  // TB3-01: always use props
            };
            const arr = [...this.blocks];
            if (atIndex >= 0 && atIndex <= arr.length) {
                arr.splice(atIndex, 0, block);
                this.selectedIndex = atIndex;
            } else {
                arr.push(block);
                this.selectedIndex = arr.length - 1;
            }
            this.pages[this.currentPage].blocks = arr;
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        removeBlock(index) {
            const arr = [...this.blocks];
            arr.splice(index, 1);
            this.pages[this.currentPage].blocks = arr;
            if (this.selectedIndex >= arr.length) this.selectedIndex = arr.length - 1;
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        selectBlock(index) {
            this.selectedIndex = index;
            if (this.selectedBlock) {
                this.sendToIframe({ action: 'select', blockId: this.selectedBlock.id });
            }
        },

        moveBlock(from, to) {
            if (to < 0 || to >= this.blocks.length) return;
            const arr = [...this.blocks];
            const [item] = arr.splice(from, 1);
            arr.splice(to, 0, item);
            this.pages[this.currentPage].blocks = arr;
            this.selectedIndex = to;
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        // Update a single prop and schedule live iframe update
        updateProp(propKey, value) {
            if (!this.selectedBlock) return;
            this.selectedBlock.props[propKey] = value;
            this.isDirty = true;
            this.schedulePropUpdate();
        },

        // ── Page Management ───────────────────────────────────────
        switchPage(key) {
            if (this.isDirty) this.saveCurrentPage();
            this.currentPage   = key;
            this.selectedIndex = -1;
            this.reloadIframe();
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

        // ── Save & Publish ────────────────────────────────────────
        async saveCurrentPage() {
            if (this.saving) return;
            this.saving = true;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/canvas/save`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ page_key: this.currentPage, blocks: this.blocks })
                });
                const d = await r.json();
                if (d.success) { this.isDirty = false; this.showToast('ذخیره شد ✓', 'success'); }
                else this.showToast(d.error ?? 'خطا در ذخیره', 'error');
            } catch { this.showToast('خطای شبکه', 'error'); }
            finally   { this.saving = false; }
        },

        // Silent auto-save (no toast) used before iframe reload
        async autoSave() {
            if (this.quickSaving) return;
            this.quickSaving = true;
            try {
                await fetch(`/admin/theme-builder/${THEME_ID}/canvas/save`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ page_key: this.currentPage, blocks: this.blocks })
                });
                this.isDirty = false;
            } catch (_) {}
            finally { this.quickSaving = false; }
        },

        async autoSaveAndReload() {
            await this.autoSave();
            this.reloadIframe();
        },

        async publishTheme() {
            if (this.publishing) return;
            await this.saveCurrentPage();
            this.publishing = true;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/publish`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.success ?? r.ok) this.showToast('قالب منتشر شد ✓', 'success');
                else this.showToast(d.message ?? 'خطا', 'error');
            } catch { this.showToast('خطای شبکه', 'error'); }
            finally   { this.publishing = false; }
        },

        openPreview() {
            window.open(`/admin/theme-builder/${THEME_ID}/canvas/${this.currentPage}/preview`, '_blank');
        },

        // ── TB3-02: Live Iframe ───────────────────────────────────
        livePreviewUrl() {
            return `/admin/theme-builder/${THEME_ID}/canvas/live-preview?page=${encodeURIComponent(this.currentPage)}&t=${this.iframeTimestamp}`;
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
                try { iframe.contentWindow.postMessage(msg, '*'); } catch (_) {}
            }
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
            if (!this.selectedBlock || !this.iframeReady) return;
            try {
                const r = await fetch(`/admin/theme-builder/${THEME_ID}/canvas/render-block`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ block: this.selectedBlock })
                });
                const d = await r.json();
                if (d.html) {
                    this.sendToIframe({ action: 'updateBlock', blockId: this.selectedBlock.id, html: d.html });
                }
            } catch (_) {}
        },

        // Handle messages from iframe
        handleIframeMessage(e) {
            const d = e.data;
            if (!d || !d.action) return;

            switch (d.action) {
                case 'ready':
                    this.iframeReady  = true;
                    this.iframeLoading = false;
                    // Restore selection in new iframe load
                    if (this.selectedBlock) {
                        this.sendToIframe({ action: 'select', blockId: this.selectedBlock.id });
                    }
                    break;

                case 'iframeHeight':
                    const iframe = this.getIframe();
                    if (iframe) iframe.style.height = Math.max(400, d.height || 400) + 'px';
                    break;

                case 'blockSelected':
                    const idx = this.blocks.findIndex(b => b.id === d.blockId);
                    if (idx >= 0) this.selectedIndex = idx;
                    break;

                case 'dropIndexResult':
                    this.dropTargetIndex    = d.index ?? this.blocks.length;
                    this.dropTargetBeforeId = d.beforeId ?? '__end__';
                    this.sendToIframe({ action: 'setDropTarget', beforeId: this.dropTargetBeforeId });
                    break;
            }
        },

        setDevice(d) { this.device = d; },

        // ── TB3-03: Drag from Library ─────────────────────────────
        startDragBlock(event, blockType) {
            this.draggedBlockType  = blockType;
            this.draggedBlockLabel = BLOCK_LABELS[blockType] ?? blockType;
            this.isDraggingBlock   = true;
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/plain', blockType);
            this.sendToIframe({ action: 'showDropZones', visible: true });
        },

        endDragBlock() {
            this.isDraggingBlock    = false;
            this.draggedBlockType   = null;
            this.draggedBlockLabel  = null;
            this.dropTargetIndex    = -1;
            this.dropTargetBeforeId = null;
            this.sendToIframe({ action: 'showDropZones', visible: false });
        },

        onDragOverCanvas(event) {
            if (!this.isDraggingBlock) return;
            const iframe = this.getIframe();
            if (iframe) {
                const rect = iframe.getBoundingClientRect();
                const scrollY = iframe.contentWindow?.scrollY ?? 0;
                const relY = event.clientY - rect.top + scrollY;
                this.sendToIframe({ action: 'getDropIndex', clientY: relY });
            }
        },

        onDragLeaveCanvas() {
            this.sendToIframe({ action: 'setDropTarget', beforeId: null });
        },

        onDropToCanvas(event) {
            event.preventDefault();
            const type = this.draggedBlockType || event.dataTransfer.getData('text/plain');
            if (!type) { this.endDragBlock(); return; }
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

        // TB3-01: Normalize blocks — always convert data → props on load
        normalizePages(pages) {
            Object.values(pages).forEach(page => {
                if (Array.isArray(page.blocks)) {
                    page.blocks = page.blocks.map(block => {
                        if (block.data !== undefined && block.props === undefined) {
                            block.props = block.data;
                            delete block.data;
                        }
                        if (!block.props) block.props = {};
                        return block;
                    });
                }
            });
        },

        // ── Init ──────────────────────────────────────────────────
        init() {
            // TB3-01: Normalize all loaded blocks to use props
            this.normalizePages(this.pages);

            // TB3-02: Listen for postMessage from live preview iframe
            window.addEventListener('message', (e) => this.handleIframeMessage(e));

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
