{{-- Schema-driven repeater (Phase 0.1, nested in Phase 0.2b — ADR-061).
     One template for every list depth: it includes itself for a child
     `repeater` field until depth 2, the registry's own maxDepth cap.
     Each depth owns its Alpine loop variables (item0/itemIndex0, …), so an
     inner list can always name its parent's index. Every control is
     generated from the level's normalized `fields` contract; nothing here
     knows which block it is editing. Outer scope: `schemaDef` (the root
     prop's definition) and `propKey`.

     @param int    $d     depth (0 = the prop's own list)
     @param string $list  JS expression: this list's path
     @param string $def   JS expression: this list's definition --}}
@php
    $item = "item{$d}";
    $idx = "itemIndex{$d}";
    $fd = "fieldDef{$d}";
    $fk = "fieldKey{$d}";
    $lid = "repeaterListId({$list})";
    $childList = substr($list, 0, -1) . ", {$idx}, {$fk}]";
    $surface = $d % 2 === 0 ? 'bg-slate-50' : 'bg-white';
@endphp
<div class="{{ $d === 0 ? 'space-y-2 mt-1' : 'space-y-1.5' }}" :data-testid="'tb-repeater-' + {!! $lid !!}">
    <div role="list" class="{{ $d === 0 ? 'space-y-2' : 'space-y-1.5' }}" :aria-label="{!! $def !!}.label || ''">
    <template x-for="({!! $item !!}, {!! $idx !!}) in repeaterItems({!! $list !!}, schemaDef)" :key="repeaterItemKey({!! $item !!}, {!! $idx !!})">
        <div role="listitem" class="rounded-xl border {{ $surface }} transition"
            :class="!repeaterCompactField({!! $def !!}) && isRepeaterItemOpen({!! $list !!}, {!! $item !!}, {!! $idx !!}) ? 'border-indigo-200 shadow-sm' : 'border-slate-200'"
            :data-testid="`tb-repeater-item-${ {!! $lid !!} }-${ {!! $idx !!} }`">
            <div class="flex items-center gap-1 px-2 py-1.5">
                <template x-if="!repeaterCompactField({!! $def !!})">
                    <button type="button" @click="toggleRepeaterItem({!! $list !!}, {!! $item !!}, {!! $idx !!})"
                        :aria-expanded="isRepeaterItemOpen({!! $list !!}, {!! $item !!}, {!! $idx !!}) ? 'true' : 'false'"
                        :data-testid="`tb-repeater-toggle-${ {!! $lid !!} }-${ {!! $idx !!} }`"
                        class="flex-1 min-w-0 flex items-center gap-1.5 text-start py-1 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300">
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform" :class="isRepeaterItemOpen({!! $list !!}, {!! $item !!}, {!! $idx !!}) ? '-rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <span class="text-xs font-semibold text-slate-700 truncate" x-text="repeaterItemTitle({!! $def !!}, {!! $item !!}, {!! $idx !!})"></span>
                    </button>
                </template>
                <template x-if="repeaterCompactField({!! $def !!})">
                    <input type="text" :id="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${repeaterCompactField({!! $def !!})}`"
                        :value="{!! $item !!}[repeaterCompactField({!! $def !!})] ?? ''"
                        @input="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, repeaterCompactField({!! $def !!}), $event.target.value)"
                        :aria-label="({!! $def !!}.fields[repeaterCompactField({!! $def !!})].label || repeaterCompactField({!! $def !!})) + ' ' + faDigits({!! $idx !!} + 1)"
                        :placeholder="{!! $def !!}.fields[repeaterCompactField({!! $def !!})].label || ''"
                        class="flex-1 min-w-0 text-xs px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </template>
                <button type="button" @click="moveRepeaterItem({!! $list !!}, schemaDef, {!! $idx !!}, -1)" :disabled="{!! $idx !!} === 0"
                    class="w-6 h-6 shrink-0 flex items-center justify-center rounded-md text-slate-500 hover:bg-white hover:text-slate-800 disabled:opacity-30 disabled:pointer-events-none"
                    :aria-label="'انتقال ' + repeaterItemTitle({!! $def !!}, {!! $item !!}, {!! $idx !!}) + ' به بالا'" title="انتقال به بالا" :data-testid="`tb-repeater-up-${ {!! $lid !!} }-${ {!! $idx !!} }`">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                </button>
                <button type="button" @click="moveRepeaterItem({!! $list !!}, schemaDef, {!! $idx !!}, 1)" :disabled="{!! $idx !!} >= repeaterItems({!! $list !!}, schemaDef).length - 1"
                    class="w-6 h-6 shrink-0 flex items-center justify-center rounded-md text-slate-500 hover:bg-white hover:text-slate-800 disabled:opacity-30 disabled:pointer-events-none"
                    :aria-label="'انتقال ' + repeaterItemTitle({!! $def !!}, {!! $item !!}, {!! $idx !!}) + ' به پایین'" title="انتقال به پایین" :data-testid="`tb-repeater-down-${ {!! $lid !!} }-${ {!! $idx !!} }`">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <button type="button" @click="duplicateRepeaterItem({!! $list !!}, schemaDef, {!! $idx !!})"
                    class="w-6 h-6 shrink-0 flex items-center justify-center rounded-md text-slate-500 hover:bg-white hover:text-indigo-600"
                    :aria-label="'تکثیر ' + repeaterItemTitle({!! $def !!}, {!! $item !!}, {!! $idx !!})" title="تکثیر" :data-testid="`tb-repeater-duplicate-${ {!! $lid !!} }-${ {!! $idx !!} }`">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
                <button type="button" @click="removeRepeaterItem({!! $list !!}, schemaDef, {!! $idx !!})"
                    class="w-6 h-6 shrink-0 flex items-center justify-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                    :aria-label="'حذف ' + repeaterItemTitle({!! $def !!}, {!! $item !!}, {!! $idx !!})" title="حذف (با Ctrl+Z برمی‌گردد)" :data-testid="`tb-repeater-remove-${ {!! $lid !!} }-${ {!! $idx !!} }`">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>

            {{-- A compact row has no card body at all (x-if, not x-show), so
                 its one input is the only element with that id. --}}
            <template x-if="!repeaterCompactField({!! $def !!})">
            <div x-show="isRepeaterItemOpen({!! $list !!}, {!! $item !!}, {!! $idx !!})" class="px-3 pb-3 pt-2 space-y-2.5 border-t border-slate-200">
                <template x-for="({!! $fd !!}, {!! $fk !!}) in ({!! $def !!}.fields || {})" :key="{!! $fk !!}">
                    <div x-show="repeaterFieldVisible({!! $fd !!}, {!! $item !!})">
                        <template x-if="fieldControl({!! $fd !!}) !== 'repeater'">
                            <label class="block text-[11px] font-medium text-slate-500 mb-1"
                                :for="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`" x-text="{!! $fd !!}.label || {!! $fk !!}"></label>
                        </template>

                        <template x-if="fieldControl({!! $fd !!}) === 'text' || fieldControl({!! $fd !!}) === 'icon'">
                            <input type="text" :id="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`"
                                :value="{!! $item !!}[{!! $fk !!}] ?? ''"
                                @input="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, $event.target.value)"
                                class="w-full text-xs px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        </template>

                        <template x-if="fieldControl({!! $fd !!}) === 'textarea' || fieldControl({!! $fd !!}) === 'rich_text'">
                            <textarea :id="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`" rows="2"
                                :value="{!! $item !!}[{!! $fk !!}] ?? ''"
                                @input="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, $event.target.value)"
                                class="w-full text-xs px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg resize-y focus:outline-none focus:ring-2 focus:ring-indigo-300"></textarea>
                        </template>

                        <template x-if="fieldControl({!! $fd !!}) === 'number' || fieldControl({!! $fd !!}) === 'range'">
                            <input type="number" :id="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`" dir="ltr"
                                :value="{!! $item !!}[{!! $fk !!}] ?? {!! $fd !!}.default ?? ''"
                                :min="{!! $fd !!}.min ?? null" :max="{!! $fd !!}.max ?? null" :step="{!! $fd !!}.step ?? 'any'"
                                @input="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, clampNumber($event.target.value, {!! $fd !!}))"
                                class="w-full text-xs px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        </template>

                        <template x-if="fieldControl({!! $fd !!}) === 'toggle'">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <span class="relative">
                                    <input type="checkbox" role="switch" class="sr-only peer" :id="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`"
                                        :checked="isTruthy({!! $item !!}[{!! $fk !!}])"
                                        :aria-checked="isTruthy({!! $item !!}[{!! $fk !!}]) ? 'true' : 'false'"
                                        @change="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, $event.target.checked)">
                                    <span class="block w-9 h-5 bg-slate-200 peer-checked:bg-indigo-500 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-300 rounded-full transition"></span>
                                    <span class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:-translate-x-4 transition-transform"></span>
                                </span>
                                <span class="text-xs text-slate-600" x-text="isTruthy({!! $item !!}[{!! $fk !!}]) ? 'فعال' : 'غیرفعال'"></span>
                            </label>
                        </template>

                        <template x-if="fieldControl({!! $fd !!}) === 'select'">
                            <select :id="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`"
                                @change="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, $event.target.value)"
                                class="w-full text-xs px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                <template x-for="opt in selectOptions({!! $fd !!})" :key="opt.value">
                                    <option :value="opt.value" :selected="String({!! $item !!}[{!! $fk !!}] ?? {!! $fd !!}.default ?? '') === opt.value" x-text="opt.label"></option>
                                </template>
                            </select>
                        </template>

                        <template x-if="fieldControl({!! $fd !!}) === 'color'">
                            <div class="flex items-center gap-1.5">
                                <input type="color" :value="colorInputValue({!! $item !!}[{!! $fk !!}], {!! $fd !!}.default)"
                                    @input="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, $event.target.value)"
                                    class="w-8 h-8 shrink-0 rounded-lg border border-slate-200 p-0.5 cursor-pointer bg-white"
                                    :aria-label="({!! $fd !!}.label || {!! $fk !!}) + ' — انتخابگر رنگ'">
                                <input type="text" dir="ltr" :id="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`"
                                    :value="{!! $item !!}[{!! $fk !!}] ?? ''" placeholder="#000000 / rgba() / خالی"
                                    @input="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, $event.target.value.trim())"
                                    class="flex-1 min-w-0 text-[11px] px-2 py-1.5 bg-white border border-slate-200 rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                <button type="button" x-show="({!! $item !!}[{!! $fk !!}] ?? '') !== ''"
                                    @click="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, '')"
                                    class="w-7 h-7 shrink-0 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-rose-600"
                                    :aria-label="'پاک کردن ' + ({!! $fd !!}.label || {!! $fk !!})" title="پاک کردن">×</button>
                            </div>
                        </template>
                        {{-- Phase 0.5 (ADR-064): the same theme swatches and transparent as
                             the top-level colour control (opacity: type an rgba() value). --}}
                        <template x-if="fieldControl({!! $fd !!}) === 'color'">
                            <div class="flex flex-wrap items-center gap-1 mt-1" role="group" :aria-label="({!! $fd !!}.label || {!! $fk !!}) + ' — رنگ‌های قالب'">
                                <template x-for="t in colorPalette()" :key="t.token">
                                    <button type="button" @click="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, t.css)" :title="t.label" :aria-label="t.label"
                                        :aria-pressed="colorParse({!! $item !!}[{!! $fk !!}] ?? '').token === t.token ? 'true' : 'false'"
                                        :class="colorParse({!! $item !!}[{!! $fk !!}] ?? '').token === t.token ? 'ring-2 ring-indigo-500' : 'ring-1 ring-slate-200'"
                                        class="w-5 h-5 rounded-full border-2 border-white" :style="'background:' + t.value"></button>
                                </template>
                                <button type="button" @click="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, 'transparent')"
                                    class="px-1.5 h-5 rounded-full text-[10px] text-slate-600 bg-white ring-1 ring-slate-200">بی‌رنگ</button>
                            </div>
                        </template>

                        <template x-if="fieldControl({!! $fd !!}) === 'link'">
                            <div>
                                <input type="text" dir="ltr" inputmode="url" :id="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`"
                                    :value="typeof {!! $item !!}[{!! $fk !!}] === 'string' ? {!! $item !!}[{!! $fk !!}] : ({!! $item !!}[{!! $fk !!}]?.url ?? '')"
                                    @input="updateRepeaterLink({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, $event.target.value)"
                                    :aria-invalid="isUnsafeLink({!! $item !!}[{!! $fk !!}]) ? 'true' : 'false'"
                                    placeholder="/products یا https://…"
                                    class="w-full text-xs px-2.5 py-1.5 bg-white border rounded-lg focus:outline-none focus:ring-2"
                                    :class="isUnsafeLink({!! $item !!}[{!! $fk !!}]) ? 'border-rose-300 focus:ring-rose-200' : 'border-slate-200 focus:ring-indigo-300'">
                                <p x-show="isUnsafeLink({!! $item !!}[{!! $fk !!}])" class="mt-1 text-[10px] text-rose-600" role="alert">این آدرس امن نیست و در سایت منتشر نمی‌شود؛ از ‎/مسیر‎، ‎https://‎، ‎mailto:‎ یا ‎tel:‎ استفاده کنید.</p>
                            </div>
                        </template>

                        <template x-if="fieldControl({!! $fd !!}) === 'media'">
                            <div class="space-y-1.5">
                                <template x-if="typeof {!! $item !!}[{!! $fk !!}] === 'string' && {!! $item !!}[{!! $fk !!}] !== ''">
                                    <div class="relative h-20 rounded-lg overflow-hidden border border-slate-200 bg-white">
                                        <img :src="{!! $item !!}[{!! $fk !!}]" alt="" class="w-full h-full object-cover">
                                    </div>
                                </template>
                                <div class="flex items-center gap-1.5">
                                    <input type="text" dir="ltr" :id="`rf-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`"
                                        :value="typeof {!! $item !!}[{!! $fk !!}] === 'string' ? {!! $item !!}[{!! $fk !!}] : ''"
                                        @input="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, $event.target.value)"
                                        placeholder="آدرس تصویر"
                                        class="flex-1 min-w-0 text-xs px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                    <button type="button" @click="pickRepeaterMedia({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!})"
                                        class="px-2.5 py-1.5 shrink-0 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 border border-indigo-200 rounded-lg text-xs font-medium transition"
                                        :data-testid="`tb-repeater-media-${ {!! $lid !!} }-${ {!! $idx !!} }-${ {!! $fk !!} }`">کتابخانه</button>
                                    <button type="button" x-show="({!! $item !!}[{!! $fk !!}] ?? '') !== ''"
                                        @click="updateRepeaterField({!! $list !!}, schemaDef, {!! $idx !!}, {!! $fk !!}, '')"
                                        class="w-7 h-7 shrink-0 flex items-center justify-center rounded-lg border border-rose-200 bg-white text-rose-500 hover:bg-rose-50"
                                        :aria-label="'حذف ' + ({!! $fd !!}.label || {!! $fk !!})" title="حذف تصویر">×</button>
                                </div>
                            </div>
                        </template>

@if ($d < 2)
                        <template x-if="fieldControl({!! $fd !!}) === 'repeater'">
                            <div class="ps-2 border-s-2 border-indigo-100">
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <span class="text-[11px] font-semibold text-slate-600" x-text="{!! $fd !!}.label || {!! $fk !!}"></span>
                                    <span class="text-[10px] text-slate-400 shrink-0" x-text="faDigits(repeaterItems({!! $childList !!}, schemaDef).length) + ' مورد'"></span>
                                </div>
                                @include('admin.theme-builder.partials.canvas-repeater', ['d' => $d + 1, 'list' => $childList, 'def' => $fd])
                            </div>
                        </template>
@endif

                        <template x-if="!isSupportedRepeaterControl({!! $fd !!}, {{ $d }})">
                            <p class="text-[10px] text-amber-600" x-text="fieldControl({!! $fd !!}) === 'repeater' ? 'فهرست‌های بیش از سه سطح تودرتو در این ویرایشگر پشتیبانی نمی‌شوند.' : 'این نوع فیلد در ویرایشگر لیست پشتیبانی نمی‌شود.'"></p>
                        </template>
                    </div>
                </template>
            </div>
            </template>
        </div>
    </template>
    </div>

    <p x-show="repeaterItems({!! $list !!}, schemaDef).length === 0" class="text-center text-[11px] text-slate-400 {{ $d === 0 ? 'py-3' : 'py-2' }} border border-dashed border-slate-200 rounded-xl">هنوز موردی اضافه نشده است.</p>

    <button type="button" @click="addRepeaterItem({!! $list !!}, schemaDef)" :data-testid="'tb-repeater-add-' + {!! $lid !!}"
        class="w-full {{ $d === 0 ? 'py-2 rounded-xl' : 'py-1.5 rounded-lg' }} bg-indigo-50 border border-indigo-200 text-indigo-600 text-xs font-semibold hover:bg-indigo-100 flex items-center justify-center gap-1.5 transition">
        <span aria-hidden="true">+</span>
        <span x-text="'افزودن ' + ({!! $def !!}.item_label || 'مورد')"></span>
    </button>
</div>
