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
            {{-- Undo / Redo --}}
            <button @click="undo()" :disabled="historyIndex <= 0"
                title="واگرد (Ctrl+Z)"
                class="flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-300 disabled:opacity-30 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6M3 10l6-6"/></svg>
            </button>
            <button @click="redo()" :disabled="historyIndex >= history.length - 1"
                title="از نو (Ctrl+Y)"
                class="flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-300 disabled:opacity-30 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2M21 10l-6 6M21 10l-6-6"/></svg>
            </button>
            <div class="h-6 w-px bg-slate-200 shrink-0"></div>

            {{-- Dirty indicator --}}
            <span x-show="isDirty" class="text-amber-500 text-xs font-medium flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse inline-block"></span>
                ذخیره نشده
            </span>

            {{-- TB6: Template Library button --}}
            <button @click="openTemplateLibrary()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-purple-200 bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-medium transition"
                title="کتابخانه قالب‌ها">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zm12 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                قالب‌ها
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
                            <span class="truncate flex-1" x-text="node.block.props?.label ? (blockLabel(node.block.type) + ': ' + node.block.props.label) : blockLabel(node.block.type)"></span>
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
                            <button @click="selectedPath = null; sendToIframe({action:'select', blockId:null})" class="text-slate-400 hover:text-slate-600 text-lg leading-none">×</button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5 font-mono" x-text="selectedBlock.type"></p>
                    </div>

                    {{-- Block Actions (Move / Duplicate / Delete) — TB4: path-aware --}}
                    <div class="flex items-center gap-1.5 px-3 py-2 border-b border-slate-100 shrink-0">
                        <button @click="moveSelectedUp()" :disabled="!canMoveUp()"
                            title="بالاتر"
                            class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-30 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                        </button>
                        <button @click="moveSelectedDown()" :disabled="!canMoveDown()"
                            title="پایین‌تر"
                            class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-30 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <button @click="duplicateSelected()"
                            title="کپی بلوک"
                            class="flex-1 flex items-center justify-center py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                        <button @click="removeSelected()"
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

                    {{-- ── STYLE TAB ──────────────────────────────────────────── --}}
                    <div x-show="selectedTab === 'style'" class="flex-1 overflow-y-auto">

                        {{-- TB5: Breakpoint Toggle Bar --}}
                        <div class="flex items-center gap-0.5 bg-slate-50 border-b border-slate-100 p-2">
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
                            <button x-show="activeBreakpoint !== 'desktop'"
                                @click="resetBlockStyle()"
                                class="mr-1 text-[10px] text-red-500 hover:text-red-700 font-medium underline">
                                پاک کردن
                            </button>
                        </div>

                        {{-- Section: Typography --}}
                        <div class="border-b border-slate-100">
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
                                            @input="updateStyle('fontSize', $event.target.value === '' ? '' : Number($event.target.value))"
                                            :placeholder="activeBreakpoint !== 'desktop' ? (selectedBlock.style?.fontSize ?? 'ارث') : 'پیش‌فرض'"
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
                                            @input="updateStyle('lineHeight', $event.target.value === '' ? '' : $event.target.value)"
                                            placeholder="پیش‌فرض"
                                            class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-slate-500 mb-1">فاصله حروف (px)</p>
                                        <input type="number" min="-5" max="20" step="0.5"
                                            :value="getCascadedValue('letterSpacing', '')"
                                            @input="updateStyle('letterSpacing', $event.target.value === '' ? '' : $event.target.value)"
                                            placeholder="پیش‌فرض"
                                            class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 mb-1">تراز متن</p>
                                    <div class="flex gap-1">
                                        <template x-for="[val, icon, tip] in [['right','←','راست'],['center','↔','وسط'],['left','→','چپ'],['justify','≡','کامل']]" :key="val">
                                            <button @click="updateStyle('textAlign', getCascadedValue('textAlign') === val ? '' : val)"
                                                :class="getCascadedValue('textAlign') === val ? 'bg-indigo-100 border-indigo-400 text-indigo-700' : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
                                                class="flex-1 py-1.5 rounded-lg border text-xs font-mono transition" :title="tip" x-text="icon"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section: Colors --}}
                        <div class="border-b border-slate-100">
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
                                        <input type="text" :value="getCascadedValue('bgColor', '')" @input="updateStyle('bgColor', $event.target.value)" placeholder="بدون رنگ" class="flex-1 text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300 font-mono">
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
                                        <input type="text" :value="getCascadedValue('textColor', '')" @input="updateStyle('textColor', $event.target.value)" placeholder="بدون رنگ" class="flex-1 text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300 font-mono">
                                        <button x-show="getCascadedValue('textColor')" @click="updateStyle('textColor', '')" class="text-slate-400 hover:text-red-500 shrink-0">×</button>
                                    </div>
                                </div>
                                {{-- Gradient toggle (desktop only) --}}
                                <div x-show="activeBreakpoint === 'desktop'">
                                    <label class="flex items-center gap-2 cursor-pointer mb-2">
                                        <input type="checkbox" :checked="!!(selectedBlock.style?.bgGradientFrom)" @change="toggleGradient($event.target.checked)" class="rounded text-indigo-600">
                                        <span class="text-[10px] text-slate-600 font-semibold">گرادیانت پس‌زمینه</span>
                                    </label>
                                    <div x-show="selectedBlock.style?.bgGradientFrom || selectedBlock.style?.bgGradientTo" class="space-y-2">
                                        <div class="flex items-center gap-1.5">
                                            <input type="color" :value="selectedBlock.style?.bgGradientFrom || '#4f46e5'" @change="updateStyle('bgGradientFrom', $event.target.value)" class="w-8 h-7 rounded border border-slate-200 cursor-pointer p-0.5 shrink-0">
                                            <span class="text-[10px] text-slate-500">شروع</span>
                                            <input type="color" :value="selectedBlock.style?.bgGradientTo || '#7c3aed'" @change="updateStyle('bgGradientTo', $event.target.value)" class="w-8 h-7 rounded border border-slate-200 cursor-pointer p-0.5 shrink-0">
                                            <span class="text-[10px] text-slate-500">پایان</span>
                                        </div>
                                        <select :value="selectedBlock.style?.bgGradientDir || 'to bottom'" @change="updateStyle('bgGradientDir', $event.target.value)" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
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
                        <div class="border-b border-slate-100">
                            <button @click="styleSection.spacing = !styleSection.spacing"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <span>فاصله‌گذاری</span>
                                <svg :class="styleSection.spacing ? 'rotate-180' : ''" class="w-3 h-3 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="styleSection.spacing" class="px-4 pb-4 space-y-3">
                                <p class="text-[10px] text-slate-500">پدینگ (px)</p>
                                <div class="grid grid-cols-4 gap-1.5">
                                    <template x-for="[key, label] in [['paddingTop','بالا'],['paddingRight','راست'],['paddingBottom','پایین'],['paddingLeft','چپ']]" :key="key">
                                        <div>
                                            <p class="text-[9px] text-slate-400 text-center mb-1" x-text="label"></p>
                                            <input type="number" min="0" max="300"
                                                :value="getCascadedValue(key, '')"
                                                @input="updateStyle(key, $event.target.value === '' ? '' : Number($event.target.value))"
                                                class="w-full text-xs text-center px-1 py-1.5 rounded-lg border focus:outline-none focus:ring-1 focus:ring-indigo-300"
                                                :class="hasResponsiveOverride(key, activeBreakpoint) && activeBreakpoint !== 'desktop' ? 'border-amber-300 bg-amber-50' : 'border-slate-200'">
                                        </div>
                                    </template>
                                </div>
                                <p class="text-[10px] text-slate-500">مارجین (px)</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[9px] text-slate-400 mb-1">بالا</p>
                                        <input type="number" min="-100" max="300" :value="getCascadedValue('marginTop', '')" @input="updateStyle('marginTop', $event.target.value === '' ? '' : Number($event.target.value))" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    </div>
                                    <div>
                                        <p class="text-[9px] text-slate-400 mb-1">پایین</p>
                                        <input type="number" min="-100" max="300" :value="getCascadedValue('marginBottom', '')" @input="updateStyle('marginBottom', $event.target.value === '' ? '' : Number($event.target.value))" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section: Border --}}
                        <div class="border-b border-slate-100">
                            <button @click="styleSection.border = !styleSection.border"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <span>حاشیه</span>
                                <svg :class="styleSection.border ? 'rotate-180' : ''" class="w-3 h-3 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="styleSection.border" class="px-4 pb-4 space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[10px] text-slate-500 mb-1">ضخامت حاشیه (px)</p>
                                        <input type="number" min="0" max="20" :value="getCascadedValue('borderWidth', '')" @input="updateStyle('borderWidth', $event.target.value === '' ? '' : Number($event.target.value))" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
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
                                        <input type="text" :value="getCascadedValue('borderColor', '')" @input="updateStyle('borderColor', $event.target.value)" placeholder="#e5e7eb" class="flex-1 text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300 font-mono">
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 mb-1">گوشه‌های گرد (px)</p>
                                    <input type="number" min="0" max="100" :value="getCascadedValue('borderRadius', '')" @input="updateStyle('borderRadius', $event.target.value === '' ? '' : Number($event.target.value))" placeholder="۰" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                </div>
                            </div>
                        </div>

                        {{-- Section: Shadow --}}
                        <div class="border-b border-slate-100">
                            <button @click="styleSection.shadow = !styleSection.shadow"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <span>سایه</span>
                                <svg :class="styleSection.shadow ? 'rotate-180' : ''" class="w-3 h-3 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="styleSection.shadow" class="px-4 pb-4">
                                <div class="flex flex-wrap gap-1.5 mb-3">
                                    <template x-for="[val, label] in [['none','هیچ'],['sm','کوچک'],['md','متوسط'],['lg','بزرگ'],['xl','خیلی بزرگ']]" :key="val">
                                        <button @click="updateStyle('shadow', getCascadedValue('shadow') === val ? '' : val)"
                                            :class="getCascadedValue('shadow') === val ? 'bg-indigo-100 border-indigo-400 text-indigo-700 font-semibold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                            class="px-2.5 py-1 rounded-lg border text-xs transition" x-text="label"></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Section: Opacity --}}
                        <div class="border-b border-slate-100 px-4 py-3">
                            <p class="text-[10px] text-slate-500 mb-2">شفافیت</p>
                            <div class="flex items-center gap-2">
                                <input type="range" min="0.1" max="1" step="0.05" :value="getCascadedValue('opacity', 1)" @input="updateStyle('opacity', Number($event.target.value))" class="flex-1">
                                <span class="text-xs text-slate-500 w-8 text-left" x-text="Math.round(getCascadedValue('opacity', 1) * 100) + '%'"></span>
                            </div>
                        </div>

                        <div class="px-4 py-3">
                            <button @click="resetBlockStyle()" class="w-full text-xs py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-red-500 transition"
                                x-text="activeBreakpoint === 'desktop' ? 'پاک کردن همه استایل‌ها' : 'پاک کردن override ' + (activeBreakpoint === 'tablet' ? 'تبلت' : 'موبایل')">
                            </button>
                        </div>
                    </div>

                    {{-- ── ADVANCED TAB ───────────────────────────────────────── --}}
                    <div x-show="selectedTab === 'advanced'" class="flex-1 overflow-y-auto px-4 py-4 space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">کلاس CSS سفارشی</label>
                            <input type="text"
                                :value="selectedBlock.style?.customClass ?? ''"
                                @input="updateStyle('customClass', $event.target.value)"
                                placeholder="مثال: my-hero featured-section"
                                class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-2">نمایش در دستگاه‌ها</label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" :checked="selectedBlock.style?.hideDesktop" @change="updateStyle('hideDesktop', $event.target.checked || '')" class="rounded text-indigo-600">
                                    <span class="text-xs text-slate-700">پنهان در دسکتاپ</span>
                                </label>
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" :checked="selectedBlock.style?.hideTablet" @change="updateStyle('hideTablet', $event.target.checked || '')" class="rounded text-indigo-600">
                                    <span class="text-xs text-slate-700">پنهان در تبلت</span>
                                </label>
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" :checked="selectedBlock.style?.hideMobile" @change="updateStyle('hideMobile', $event.target.checked || '')" class="rounded text-indigo-600">
                                    <span class="text-xs text-slate-700">پنهان در موبایل</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">انیمیشن ورود</label>
                            <select :value="selectedBlock.style?.animation ?? 'none'"
                                @change="updateStyle('animation', $event.target.value === 'none' ? '' : $event.target.value)"
                                class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300 mb-2">
                                <option value="none">بدون انیمیشن</option>
                                <option value="fade-in">محو شدن (Fade In)</option>
                                <option value="slide-up">از پایین (Slide Up)</option>
                                <option value="slide-right">از راست (Slide Right)</option>
                                <option value="zoom-in">بزرگ‌شدن (Zoom In)</option>
                                <option value="bounce-in">جهش (Bounce In)</option>
                            </select>
                            <template x-if="selectedBlock.style?.animation && selectedBlock.style.animation !== 'none'">
                                <div>
                                    <label class="block text-[10px] text-slate-500 mb-1">مدت زمان انیمیشن (ms)</label>
                                    <input type="number" min="200" max="3000" step="100"
                                        :value="selectedBlock.style?.animationDuration ?? 600"
                                        @input="updateStyle('animationDuration', Number($event.target.value))"
                                        class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- ── CONTENT TAB ────────────────────────────────────────── --}}
                    <div x-show="selectedTab === 'content'" class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                        <template x-for="(schemaDef, propKey) in selectedSchema" :key="propKey">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5" x-text="schemaDef.label || propKey"></label>

                                {{-- image (TB3-07) --}}
                                <template x-if="schemaDef.type === 'image'">
                                    <div>
                                        <div x-show="selectedBlock.props[propKey]" class="mb-2 relative rounded-lg overflow-hidden border border-slate-200 bg-slate-50" style="height:80px;">
                                            <img :src="selectedBlock.props[propKey]" class="w-full h-full object-cover">
                                            <button @click="updateProp(propKey, '')" class="absolute top-1 left-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">×</button>
                                        </div>
                                        <div class="flex gap-1.5">
                                            <input type="text"
                                                :value="selectedBlock.props[propKey] ?? ''"
                                                @input="updateProp(propKey, $event.target.value)"
                                                placeholder="آدرس URL تصویر"
                                                class="flex-1 text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                            <button @click="openMediaPicker(propKey)"
                                                class="flex items-center justify-center w-8 h-8 rounded-lg border border-indigo-300 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition shrink-0" title="انتخاب از کتابخانه">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                {{-- string --}}
                                <template x-if="schemaDef.type === 'string'">
                                    <input type="text"
                                        :value="selectedBlock.props[propKey] ?? schemaDef.default ?? ''"
                                        @input="updateProp(propKey, $event.target.value)"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition"
                                        :placeholder="schemaDef.default ?? ''">
                                </template>

                                {{-- number --}}
                                <template x-if="schemaDef.type === 'number'">
                                    <input type="number"
                                        :value="selectedBlock.props[propKey] ?? schemaDef.default ?? 0"
                                        @input="updateProp(propKey, Number($event.target.value))"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition" :min="0">
                                </template>

                                {{-- boolean --}}
                                <template x-if="schemaDef.type === 'boolean'">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <div class="relative">
                                            <input type="checkbox" :checked="selectedBlock.props[propKey] ?? false" @change="updateProp(propKey, $event.target.checked)" class="sr-only peer">
                                            <div class="w-9 h-5 bg-slate-200 peer-checked:bg-indigo-500 rounded-full transition"></div>
                                            <div class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-full rtl:peer-checked:-translate-x-full transition-transform"></div>
                                        </div>
                                        <span class="text-sm text-slate-700" x-text="(selectedBlock.props[propKey] ?? false) ? 'فعال' : 'غیرفعال'"></span>
                                    </label>
                                </template>

                                {{-- select --}}
                                <template x-if="schemaDef.type === 'select'">
                                    <select :value="selectedBlock.props[propKey] ?? schemaDef.default ?? ''" @change="updateProp(propKey, $event.target.value)"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition">
                                        <template x-for="(label, val) in schemaDef.options" :key="val">
                                            <option :value="val" :selected="(selectedBlock.props[propKey] ?? schemaDef.default) == val" x-text="label"></option>
                                        </template>
                                    </select>
                                </template>

                                {{-- color --}}
                                <template x-if="schemaDef.type === 'color'">
                                    <div class="flex items-center gap-2">
                                        <input type="color" :value="selectedBlock.props[propKey] ?? '#4f46e5'" @input="updateProp(propKey, $event.target.value)" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer p-0.5">
                                        <input type="text" :value="selectedBlock.props[propKey] ?? '#4f46e5'" @input="updateProp(propKey, $event.target.value)" class="flex-1 text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 font-mono" placeholder="#000000">
                                    </div>
                                </template>

                                {{-- textarea --}}
                                <template x-if="schemaDef.type === 'textarea'">
                                    <textarea :value="selectedBlock.props[propKey] ?? schemaDef.default ?? ''" @input="updateProp(propKey, $event.target.value)" rows="3"
                                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition resize-y"
                                        :placeholder="schemaDef.default ?? ''"></textarea>
                                </template>
                            </div>
                        </template>

                        <div x-show="Object.keys(selectedSchema).length === 0 && !isContainerType(selectedBlock?.type)" class="text-center py-6">
                            <p class="text-xs text-slate-400">این بلوک تنظیماتی ندارد</p>
                        </div>

                        {{-- TB4: Container Children Panel ──────────────────── --}}
                        <template x-if="isContainerType(selectedBlock?.type)">
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
                    <option value="hero">هیرو</option>
                    <option value="features">ویژگی‌ها</option>
                    <option value="pricing">قیمت‌گذاری</option>
                    <option value="testimonials">نظرات</option>
                    <option value="contact">تماس</option>
                    <option value="gallery">گالری</option>
                    <option value="team">تیم</option>
                    <option value="blog">وبلاگ</option>
                    <option value="product">محصول</option>
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
                        <div class="border border-slate-200 rounded-xl overflow-hidden hover:shadow-md transition cursor-pointer group flex flex-col"
                            @click="insertTemplate(tpl.id)">
                            {{-- Thumb --}}
                            <div class="aspect-video bg-gradient-to-br from-slate-100 to-purple-50 flex items-center justify-center relative overflow-hidden">
                                <template x-if="tpl.thumbnail_url">
                                    <img :src="tpl.thumbnail_url" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!tpl.thumbnail_url">
                                    <svg class="w-8 h-8 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/></svg>
                                </template>
                                <span class="absolute top-1.5 right-1.5 text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-white/90 text-purple-700" x-text="tpl.type_label"></span>
                                <div class="absolute inset-0 bg-purple-600/0 group-hover:bg-purple-600/10 transition flex items-center justify-center">
                                    <span class="opacity-0 group-hover:opacity-100 bg-purple-600 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">درج</span>
                                </div>
                            </div>
                            <div class="p-3">
                                <p class="text-xs font-semibold text-slate-800 truncate" x-text="tpl.name"></p>
                                <p class="text-[10px] text-slate-400 mt-0.5" x-text="tpl.category_label"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TB6: SAVE AS TEMPLATE MODAL
    ═══════════════════════════════════════════════════════ --}}
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
{{-- Phase 3F-B: is_container is declarative capability metadata from the
     canonical BlockRegistry::isContainerType(), not a client-side hardcoded
     array — see App\Services\ThemeBuilder\BlockRegistry (this host's thin
     subclass of the shared canonical authority). --}}
const BLOCK_SCHEMAS = @json(collect($registry)->mapWithKeys(fn($d,$t)=>[$t=>['schema'=>$d['schema']??[],'defaultProps'=>$d['defaultProps']??[],'is_container'=>\App\Services\ThemeBuilder\BlockRegistry::isContainerType($t)]]));
const INITIAL_PAGES = @json($pages);

function themeCanvas() {
    return {
        // ── State ───────────────────────────────────────────────
        pages:          JSON.parse(JSON.stringify(INITIAL_PAGES)),
        currentPage:    Object.keys(INITIAL_PAGES)[0] ?? 'home',
        selectedTab:    'content',
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
        isDraggingBlock:     false,
        draggedBlockType:    null,
        draggedBlockLabel:   null,
        dropTargetIndex:     -1,
        dropTargetBeforeId:  null,
        dragTargetContainerId: null,  // TB4: container drag target
        // TB3-S2: undo/redo history
        history:        [],
        historyIndex:   -1,
        maxHistory:     25,
        // TB3-05: style panel sections (open/closed)
        styleSection: { typography: true, colors: true, spacing: false, border: false, shadow: false },
        // TB3-04: inline edit auto-save
        inlineAutoSaveTimer: null,
        // TB3-07: media manager
        mediaModal: { show: false, tab: 'library', loading: false, images: [], uploading: false, uploadDone: false, targetPropKey: null },
        // TB4: nested containers
        selectedPath:    null,          // null | [0] | [0, 1] | ...
        leftPanelTab:    'blocks',      // 'blocks' | 'tree'
        treeExpanded:    {},            // blockId → bool (default: expanded)
        showAddChildModal: false,
        // TB5: responsive editor
        activeBreakpoint: 'desktop',   // 'desktop' | 'tablet' | 'mobile'
        // TB6: template library
        showTemplateLibrary: false,
        showSaveAsTemplate: false,
        templateLibrary: { loading: false, templates: [], type: '', category: '', search: '' },
        saveTemplateForm: { name: '', type: 'section', category: 'hero', scope: 'block', saving: false },

        // ── Computed ─────────────────────────────────────────────
        get blocks() {
            return this.pages[this.currentPage]?.blocks ?? [];
        },
        set blocks(val) {
            if (this.pages[this.currentPage]) this.pages[this.currentPage].blocks = val;
        },
        // TB4: path-based selection
        get selectedBlock() {
            if (!this.selectedPath || this.selectedPath.length === 0) return null;
            return this.getBlockByPath(this.selectedPath);
        },
        // backward compat — top-level index of selected block
        get selectedIndex() {
            return this.selectedPath?.length > 0 ? (this.selectedPath[0] ?? -1) : -1;
        },
        get selectedSchema() {
            if (!this.selectedBlock) return {};
            return BLOCK_SCHEMAS[this.selectedBlock.type]?.schema ?? {};
        },
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
            arr = arr ?? this.blocks;
            if (!path || path.length === 0) return null;
            let block = arr[path[0]];
            for (let i = 1; i < path.length; i++) {
                if (!block) return null;
                block = (block.children ?? [])[path[i]];
            }
            return block ?? null;
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
            for (let i = 0; i < arr.length; i++) {
                if (arr[i].id === blockId) return [...prefix, i];
                if (arr[i].children?.length) {
                    const found = this.findBlockPath(blockId, arr[i].children, [...prefix, i]);
                    if (found) return found;
                }
            }
            return null;
        },

        flattenTree(arr, depth, prefix) {
            arr    = arr    ?? this.blocks;
            depth  = depth  ?? 0;
            prefix = prefix ?? [];
            const result = [];
            for (let i = 0; i < arr.length; i++) {
                const block = arr[i];
                const path  = [...prefix, i];
                result.push({ block, depth, path });
                if (this.isContainerType(block.type) && block.children?.length && this.treeExpanded[block.id] !== false) {
                    result.push(...this.flattenTree(block.children, depth + 1, path));
                }
            }
            return result;
        },

        isSelectedPath(path) {
            if (!this.selectedPath || !path) return false;
            if (this.selectedPath.length !== path.length) return false;
            return this.selectedPath.every((v, i) => v === path[i]);
        },

        toggleTreeNode(blockId) {
            this.treeExpanded[blockId] = this.treeExpanded[blockId] !== false ? false : true;
        },

        // ── Canvas Actions ────────────────────────────────────────
        addBlock(type, atIndex = -1, parentPath = null) {
            this.pushHistory();
            const block = this.makeBlock(type);
            if (parentPath !== null) {
                // Add as child of container at parentPath
                const container = this.getBlockByPath(parentPath);
                if (!container) return;
                if (!container.children) container.children = [];
                container.children.push(block);
                this.selectedPath = [...parentPath, container.children.length - 1];
            } else {
                const arr = [...this.blocks];
                const idx = (atIndex >= 0 && atIndex <= arr.length) ? atIndex : arr.length;
                arr.splice(idx, 0, block);
                this.pages[this.currentPage].blocks = arr;
                this.selectedPath = [idx];
            }
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        addChildBlock(type) {
            if (!this.selectedPath || !this.isContainerType(this.selectedBlock?.type)) return;
            this.addBlock(type, -1, this.selectedPath);
        },

        // Remove block at selectedPath
        removeSelected() {
            if (!this.selectedPath) return;
            this.pushHistory();
            const siblings = this.getSiblingArray(this.selectedPath);
            if (!siblings) return;
            const idx = this.selectedPath[this.selectedPath.length - 1];
            siblings.splice(idx, 1);
            this.selectedPath = null;
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        duplicateSelected() {
            if (!this.selectedPath) return;
            this.pushHistory();
            const siblings = this.getSiblingArray(this.selectedPath);
            if (!siblings) return;
            const idx = this.selectedPath[this.selectedPath.length - 1];
            const clone = JSON.parse(JSON.stringify(siblings[idx]));
            clone.id = 'blk_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);
            // Regen child IDs recursively
            this._regenIds(clone);
            siblings.splice(idx + 1, 0, clone);
            this.selectedPath = [...this.selectedPath.slice(0, -1), idx + 1];
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        _regenIds(block) {
            block.id = 'blk_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);
            (block.children ?? []).forEach(c => this._regenIds(c));
        },

        selectBlock(pathOrIndex) {
            // Accept path array or legacy integer index
            const path = Array.isArray(pathOrIndex) ? pathOrIndex : (pathOrIndex >= 0 ? [pathOrIndex] : null);
            this.selectedPath = path;
            this.selectedTab = 'content';
            if (this.selectedBlock) {
                this.sendToIframe({ action: 'select', blockId: this.selectedBlock.id });
            }
        },

        // Move selected block within its sibling array
        moveSelectedUp() {
            if (!this.selectedPath) return;
            const siblings = this.getSiblingArray(this.selectedPath);
            const idx = this.selectedPath[this.selectedPath.length - 1];
            if (!siblings || idx <= 0) return;
            this.pushHistory();
            [siblings[idx - 1], siblings[idx]] = [siblings[idx], siblings[idx - 1]];
            this.selectedPath = [...this.selectedPath.slice(0, -1), idx - 1];
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        moveSelectedDown() {
            if (!this.selectedPath) return;
            const siblings = this.getSiblingArray(this.selectedPath);
            const idx = this.selectedPath[this.selectedPath.length - 1];
            if (!siblings || idx >= siblings.length - 1) return;
            this.pushHistory();
            [siblings[idx], siblings[idx + 1]] = [siblings[idx + 1], siblings[idx]];
            this.selectedPath = [...this.selectedPath.slice(0, -1), idx + 1];
            this.isDirty = true;
            this.autoSaveAndReload();
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
            this.pushHistory();
            const ch = this.selectedBlock.children;
            [ch[ci - 1], ch[ci]] = [ch[ci], ch[ci - 1]];
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        moveChildDown(ci) {
            const ch = this.selectedBlock?.children ?? [];
            if (ci >= ch.length - 1) return;
            this.pushHistory();
            [ch[ci], ch[ci + 1]] = [ch[ci + 1], ch[ci]];
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        removeChild(ci) {
            if (!this.selectedBlock?.children) return;
            this.pushHistory();
            this.selectedBlock.children.splice(ci, 1);
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        // Legacy: kept for undo/redo top-level swap
        moveBlock(from, to) {
            if (to < 0 || to >= this.blocks.length) return;
            this.pushHistory();
            const arr = [...this.blocks];
            const [item] = arr.splice(from, 1);
            arr.splice(to, 0, item);
            this.pages[this.currentPage].blocks = arr;
            this.selectedPath = [to];
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        // ── TB3-S2: Undo / Redo ──────────────────────────────────
        pushHistory() {
            if (this.historyIndex < this.history.length - 1) {
                this.history = this.history.slice(0, this.historyIndex + 1);
            }
            this.history.push(JSON.parse(JSON.stringify(this.blocks)));
            if (this.history.length > this.maxHistory) {
                this.history.shift();
            } else {
                this.historyIndex++;
            }
        },

        undo() {
            if (this.historyIndex <= 0) return;
            this.historyIndex--;
            this.pages[this.currentPage].blocks = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
            this.selectedPath = null;
            this.isDirty = true;
            this.autoSaveAndReload();
        },

        redo() {
            if (this.historyIndex >= this.history.length - 1) return;
            this.historyIndex++;
            this.pages[this.currentPage].blocks = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
            this.selectedPath = null;
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

        // ── TB3-S2: Per-block style overrides (TB5: breakpoint-aware) ─
        updateStyle(styleKey, value) {
            if (!this.selectedBlock) return;
            const bp = this.activeBreakpoint;
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
        switchPage(key) {
            if (this.isDirty) this.saveCurrentPage();
            this.currentPage  = key;
            this.selectedPath = null;
            this.selectedTab  = 'content';
            // Reset history for new page
            this.history     = [JSON.parse(JSON.stringify(this.pages[key]?.blocks ?? []))];
            this.historyIndex = 0;
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
            const d = e.data;
            if (!d || !d.action) return;

            switch (d.action) {
                case 'ready':
                    this.iframeReady   = true;
                    this.iframeLoading = false;
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
                    // TB4: search entire tree, get path array
                    const path = this.findBlockPath(d.blockId);
                    if (path) { this.selectedPath = path; this.selectedTab = 'content'; }
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
                    this.showToast(d.error ?? 'خطا در آپلود', 'error');
                }
            } catch (_) { this.showToast('خطای شبکه', 'error'); }
            finally { this.mediaModal.uploading = false; }
        },

        setDevice(d) { this.device = d; this.activeBreakpoint = d; },

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
        async insertTemplate(id) {
            try {
                const res = await fetch('{{ route('admin.theme-builder.canvas.insert-template', $theme) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' },
                    body: JSON.stringify({ template_id: id }),
                });
                const json = await res.json();
                if (json.success && json.blocks) {
                    this.blocks = [...this.blocks, ...json.blocks];
                    this.pushHistory();
                    this.isDirty = true;
                    this.showTemplateLibrary = false;
                    this.showToast('قالب درج شد', 'success');
                }
            } catch (e) {
                this.showToast('خطا در درج قالب', 'error');
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

            // TB3-S2: Initialize undo history with current state
            this.history = [JSON.parse(JSON.stringify(this.blocks))];
            this.historyIndex = 0;

            // TB3-02: Listen for postMessage from live preview iframe
            window.addEventListener('message', (e) => this.handleIframeMessage(e));

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
