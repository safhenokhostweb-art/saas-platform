{{--
  x-core-ui::data-table — Enterprise canonical table component.
  Features:
    - Sticky headers
    - Density modes (compact | default | comfortable)
    - Responsive horizontal scroll
    - Keyboard navigation (arrow keys)
    - Loading skeleton rows
    - Empty state
    - Bulk selection with checkbox column
    - Sortable column headers
    - Token-driven styling

  Props:
    headers  : array  — [['key'=>'id', 'label'=>'شناسه', 'sortable'=>true, 'class'=>'w-16']]
    rows     : int    — number of skeleton rows shown while loading (default: 5)
    loading  : bool   — show skeleton instead of slot
    density  : compact | default | comfortable (default: default)
    selectable: bool  — show checkbox column for bulk selection
    empty    : string — empty state message
    sort-key : string — currently sorted column key
    sort-dir : string — asc | desc
--}}
@props([
    'headers'    => [],
    'rows'       => 5,
    'loading'    => false,
    'density'    => 'default',
    'selectable' => false,
    'empty'      => 'موردی یافت نشد.',
    'sortKey'    => null,
    'sortDir'    => 'asc',
])

@php
$padY = match($density) {
    'compact'      => 'py-2',
    'comfortable'  => 'py-4',
    default        => 'py-3',
};
$padX = 'px-4';
$rowH = match($density) {
    'compact'     => 'var(--ui-table-row-height-compact)',
    'comfortable' => 'var(--ui-table-row-height-comfortable)',
    default       => 'var(--ui-table-row-height-default)',
};
$colCount = count($headers) + ($selectable ? 1 : 0);
@endphp

<div
    x-data="{
        selectedAll: false,
        selected: [],
        sortKey: '{{ $sortKey }}',
        sortDir: '{{ $sortDir }}',
        toggleAll(count) {
            this.selectedAll = !this.selectedAll;
            if (this.selectedAll) {
                this.selected = Array.from({ length: count }, (_, i) => i);
            } else {
                this.selected = [];
                this.selectedAll = false;
            }
            this.$dispatch('selection-change', { selected: this.selected, all: this.selectedAll });
        },
        toggleRow(idx) {
            const pos = this.selected.indexOf(idx);
            if (pos >= 0) { this.selected.splice(pos, 1); this.selectedAll = false; }
            else { this.selected.push(idx); }
            this.$dispatch('selection-change', { selected: this.selected, all: this.selectedAll });
        },
        requestSort(key) {
            if (!key) return;
            if (this.sortKey === key) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDir = 'asc';
            }
            this.$dispatch('sort-change', { key: this.sortKey, dir: this.sortDir });
        },
    }"
    {{ $attributes->only('class') }}
>
    {{-- Bulk actions bar --}}
    @if($selectable)
        <div x-show="selected.length > 0"
             x-transition:enter="transition ease-[var(--ui-ease-default)] duration-[var(--ui-duration-normal)]"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="flex items-center gap-3 px-4 py-2 mb-2 bg-[var(--ui-indigo-light)]
                    border border-[var(--ui-indigo)]/20 rounded-[var(--ui-radius-xl)]
                    text-sm font-medium text-[var(--ui-indigo)]">
            <span x-text="`${selected.length} آیتم انتخاب شده`"></span>
            <div class="flex-1"></div>
            {{ $bulkActions ?? '' }}
            <button type="button" @click="selected = []; selectedAll = false;"
                    class="text-xs text-[var(--ui-text-muted)] hover:text-[var(--ui-text-primary)]
                           underline underline-offset-2 transition-colors duration-[var(--ui-duration-fast)]">
                لغو انتخاب
            </button>
        </div>
    @endif

    {{-- Table wrapper --}}
    <div class="overflow-hidden rounded-[var(--ui-radius-xl)] border border-[var(--ui-border)] shadow-[var(--ui-shadow-xs)]">
        <div class="overflow-x-auto" role="region" aria-label="جدول داده">
            <table class="w-full text-sm text-[var(--ui-text-primary)]"
                   role="grid"
                   @keydown.arrow-down.prevent="
                       const next = $el.querySelector('[role=row]:focus')?.nextElementSibling;
                       if (next) next.focus();
                   "
                   @keydown.arrow-up.prevent="
                       const prev = $el.querySelector('[role=row]:focus')?.previousElementSibling;
                       if (prev) prev.focus();
                   ">

                {{-- Header --}}
                @if(count($headers) > 0)
                    <thead class="bg-[var(--ui-table-header-bg)] border-b border-[var(--ui-border)] sticky top-0 z-[var(--ui-z-raised)]">
                        <tr role="row">
                            {{-- Select-all checkbox --}}
                            @if($selectable)
                                <th class="{{ $padX }} {{ $padY }} w-12" scope="col">
                                    <input type="checkbox"
                                           x-model="selectedAll"
                                           @change="toggleAll({{ count($headers) }})"
                                           class="rounded border-[var(--ui-border-strong)] text-[var(--ui-indigo)]
                                                  focus:ring-2 focus:ring-[var(--ui-indigo)] focus:ring-offset-1
                                                  cursor-pointer w-4 h-4"
                                           aria-label="انتخاب همه">
                                </th>
                            @endif

                            @foreach($headers as $header)
                                @php
                                    $hKey  = is_array($header) ? ($header['key'] ?? null) : null;
                                    $hLabel= is_array($header) ? ($header['label'] ?? $header) : $header;
                                    $hSort = is_array($header) ? ($header['sortable'] ?? false) : false;
                                    $hCls  = is_array($header) ? ($header['class'] ?? '') : '';
                                @endphp
                                <th
                                    scope="col"
                                    class="{{ $padX }} {{ $padY }} text-[var(--ui-table-header-font)] font-[var(--ui-table-header-weight)]
                                           text-[var(--ui-table-header-text)] text-right {{ $hCls }}
                                           {{ $hSort ? 'cursor-pointer select-none hover:text-[var(--ui-text-primary)] transition-colors duration-[var(--ui-duration-fast)]' : '' }}"
                                    @if($hSort && $hKey)
                                        @click="requestSort('{{ $hKey }}')"
                                        :aria-sort="sortKey === '{{ $hKey }}' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'"
                                    @endif
                                >
                                    <div class="flex items-center gap-1.5 {{ $hSort ? 'group' : '' }}">
                                        <span>{{ $hLabel }}</span>
                                        @if($hSort && $hKey)
                                            <svg class="w-3.5 h-3.5 shrink-0 transition-all duration-[var(--ui-duration-fast)]"
                                                 :class="{
                                                     'text-[var(--ui-table-sort-active-color)]': sortKey === '{{ $hKey }}',
                                                     'text-[var(--ui-table-sort-icon-color)] opacity-0 group-hover:opacity-100': sortKey !== '{{ $hKey }}',
                                                     'rotate-180': sortKey === '{{ $hKey }}' && sortDir === 'desc',
                                                 }"
                                                 fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                @endif

                {{-- Body --}}
                <tbody class="bg-[var(--ui-surface)] divide-y divide-[var(--ui-table-row-border)]">
                    @if($loading)
                        {{-- Skeleton rows --}}
                        @for($i = 0; $i < $rows; $i++)
                            <tr>
                                @if($selectable)
                                    <td class="{{ $padX }} {{ $padY }}">
                                        <div class="ui-skeleton w-4 h-4 rounded"></div>
                                    </td>
                                @endif
                                @foreach($headers as $header)
                                    <td class="{{ $padX }} {{ $padY }}">
                                        <div class="ui-skeleton h-3 rounded-full"
                                             style="width: {{ rand(40, 90) }}%"></div>
                                    </td>
                                @endforeach
                            </tr>
                        @endfor
                    @else
                        {{ $slot }}
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Empty state --}}
        @if(!$loading && isset($emptyState))
            <div class="py-12 px-4 text-center">
                {{ $emptyState }}
            </div>
        @elseif(!$loading && !isset($emptyState))
            <div x-show="{{ isset($showEmpty) ? 'true' : 'false' }}"
                 class="py-12 px-4 text-center text-sm text-[var(--ui-text-muted)]">
                {{ $empty }}
            </div>
        @endif
    </div>
</div>
