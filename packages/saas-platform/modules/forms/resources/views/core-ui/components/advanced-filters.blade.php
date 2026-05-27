@props([
    'filters'    => [],  // [['name' => '', 'label' => '', 'type' => 'text|select|date', 'options' => []]]
    'action'     => '',
    'method'     => 'GET',
    'activeFilters' => [],  // current active filter values
])

<div {{ $attributes }} x-data="{ expanded: false, activeCount: {{ count(array_filter($activeFilters)) }} }">

    {{-- Filter toggle bar --}}
    <div class="flex items-center gap-2 mb-3">
        <button @click="expanded = !expanded"
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/>
            </svg>
            فیلترها
            <template x-if="activeCount > 0">
                <span class="flex items-center justify-center h-4 w-4 rounded-full bg-indigo-600 text-white text-[10px] font-bold" x-text="activeCount"></span>
            </template>
            <svg class="w-3.5 h-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        @if(count(array_filter($activeFilters)) > 0)
        <a href="{{ $action }}" class="text-xs font-semibold text-gray-400 hover:text-red-500 transition-colors flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            پاک کردن فیلترها
        </a>
        @endif
    </div>

    {{-- Expanded filter panel --}}
    <div x-show="expanded"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl p-4 mb-4 shadow-sm"
         style="display:none">

        <form action="{{ $action }}" method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}">
            @if(strtoupper($method) !== 'GET') @csrf @method($method) @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-{{ min(count($filters), 4) }} gap-3">
                @foreach($filters as $filter)
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ $filter['label'] }}</label>
                    @if(($filter['type'] ?? 'text') === 'select')
                    <select name="{{ $filter['name'] }}"
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
                        <option value="">همه</option>
                        @foreach($filter['options'] ?? [] as $value => $label)
                        <option value="{{ $value }}" {{ ($activeFilters[$filter['name']] ?? '') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @elseif(($filter['type'] ?? 'text') === 'date')
                    <input type="date" name="{{ $filter['name'] }}"
                           value="{{ $activeFilters[$filter['name']] ?? '' }}"
                           class="w-full px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
                    @else
                    <input type="text" name="{{ $filter['name'] }}"
                           value="{{ $activeFilters[$filter['name']] ?? '' }}"
                           placeholder="{{ $filter['placeholder'] ?? '' }}"
                           class="w-full px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
                    @endif
                </div>
                @endforeach
            </div>

            <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-100">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition-colors shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    اعمال فیلتر
                </button>
                <button type="button" @click="expanded = false"
                        class="px-4 py-2 rounded-xl text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                    بستن
                </button>
            </div>
        </form>
    </div>
</div>
