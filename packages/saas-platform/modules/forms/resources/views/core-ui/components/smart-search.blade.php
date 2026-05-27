{{--
  Smart Search — inline contextual search bar with debounced suggestions.
  Usage:
    <x-core-ui::smart-search
        tenant-id="{{ $tenantId }}"
        :resources="['tickets','forms']"
        placeholder="جستجو..."
        class="w-full max-w-md"
    />
--}}
@props([
    'tenantId'    => '',
    'resources'   => [],
    'placeholder' => 'جستجو...',
    'autofocus'   => false,
    'compact'     => false,
    'maxResults'  => 8,
])

<div {{ $attributes->merge(['class' => 'relative']) }}
     x-data="smartSearch('{{ $tenantId }}', @js($resources), {{ $maxResults }})"
     @click.outside="close()"
     @keydown.escape="close()">

    {{-- Input --}}
    <div class="relative flex items-center">
        <svg class="absolute right-3 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <input x-ref="input"
               x-model="query"
               @input.debounce.200ms="search()"
               @focus="query && results.length ? open = true : null"
               @keydown.arrow-down.prevent="moveDown()"
               @keydown.arrow-up.prevent="moveUp()"
               @keydown.enter.prevent="executeSelected()"
               type="search"
               placeholder="{{ $placeholder }}"
               {{ $autofocus ? 'autofocus' : '' }}
               class="{{ $compact ? 'text-sm py-1.5 pl-3 pr-9' : 'text-sm py-2 pl-4 pr-10' }} w-full bg-white border border-gray-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition placeholder-gray-400"
               autocomplete="off"
               spellcheck="false"
               aria-label="{{ $placeholder }}"
               aria-expanded="false"
               :aria-expanded="open.toString()"
               role="combobox"
               aria-haspopup="listbox">

        {{-- Loading spinner --}}
        <template x-if="loading">
            <div class="absolute left-3 w-3.5 h-3.5 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin"></div>
        </template>

        {{-- Clear button --}}
        <template x-if="query && !loading">
            <button @click="clear()" class="absolute left-3 text-gray-400 hover:text-gray-600 transition"
                    aria-label="پاک کردن جستجو">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </template>
    </div>

    {{-- Dropdown Results --}}
    <template x-if="open && (results.length > 0 || (query.length >= 2 && !loading))">
        <div class="absolute top-full mt-1.5 right-0 w-full min-w-64 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50"
             role="listbox">

            <template x-if="results.length > 0">
                <div class="py-1.5">
                    {{-- Group by resource type --}}
                    <template x-for="(group, gKey) in groupedResults" :key="gKey">
                        <div>
                            <p class="px-3 py-1 text-xs font-bold uppercase tracking-widest text-gray-400"
                               x-text="resourceLabels[gKey] ?? gKey"></p>
                            <template x-for="(item, idx) in group" :key="idx">
                                <a :href="item.url ?? '#'"
                                   :id="`sr-${gKey}-${idx}`"
                                   :class="isSelected(gKey, idx) ? 'bg-indigo-50' : 'hover:bg-gray-50'"
                                   class="flex items-center gap-3 px-3 py-2 cursor-pointer transition text-sm group"
                                   role="option"
                                   :aria-selected="isSelected(gKey, idx).toString()"
                                   @mouseover="hoverGroup = gKey; hoverIdx = idx"
                                   @click="recordClick(item)">
                                    <span class="w-6 h-6 rounded-lg bg-gray-100 group-hover:bg-indigo-100 flex items-center justify-center text-xs shrink-0">
                                        <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                        </svg>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="truncate font-medium text-gray-800" x-text="item.label ?? item.title"></p>
                                        <p class="truncate text-xs text-gray-400" x-text="item.meta ?? item.description ?? ''"></p>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- No results --}}
            <template x-if="results.length === 0 && !loading && query.length >= 2">
                <div class="py-6 text-center">
                    <p class="text-sm text-gray-400">نتیجه‌ای یافت نشد برای «<span x-text="query" class="font-medium text-gray-600"></span>»</p>
                </div>
            </template>

            {{-- Footer hint --}}
            <template x-if="results.length > 0">
                <div class="border-t border-gray-100 px-3 py-1.5 bg-gray-50/50 flex items-center justify-between">
                    <span class="text-xs text-gray-400" x-text="`${results.length} نتیجه`"></span>
                    <span class="text-xs text-gray-400">Enter برای باز کردن</span>
                </div>
            </template>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
function smartSearch(tenantId, resources, maxResults) {
    return {
        query: '',
        results: [],
        open: false,
        loading: false,
        hoverGroup: null,
        hoverIdx: -1,
        flatResults: [],
        flatIndex: -1,
        resourceLabels: { tickets: 'تیکت‌ها', forms: 'فرم‌ها', submissions: 'ارسال‌ها', pages: 'صفحات', products: 'محصولات' },

        get groupedResults() {
            return this.results.reduce((acc, r) => {
                const g = r.resource_type ?? r.type ?? 'نتایج';
                acc[g] = acc[g] ?? [];
                acc[g].push(r);
                return acc;
            }, {});
        },

        isSelected(group, idx) {
            const flat = Object.values(this.groupedResults).flat();
            return flat.indexOf(Object.values(this.groupedResults[group] ?? [])[idx]) === this.flatIndex;
        },

        async search() {
            if (!this.query || this.query.length < 2) { this.results = []; this.open = false; return; }
            this.loading = true; this.open = true;
            try {
                const params = new URLSearchParams({ q: this.query, tenant_id: tenantId });
                if (resources.length) params.set('types', resources.join(','));
                const r = await fetch(`/api/search?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) {
                    const d = await r.json();
                    this.results = (d.results ?? []).slice(0, maxResults);
                    this.flatResults = this.results;
                    this.flatIndex = this.results.length > 0 ? 0 : -1;
                }
            } catch {}
            this.loading = false;
        },

        moveDown() {
            if (this.flatResults.length === 0) return;
            this.flatIndex = Math.min(this.flatIndex + 1, this.flatResults.length - 1);
        },
        moveUp() {
            this.flatIndex = Math.max(this.flatIndex - 1, 0);
        },
        executeSelected() {
            if (this.flatIndex >= 0 && this.flatResults[this.flatIndex]?.url) {
                window.location.href = this.flatResults[this.flatIndex].url;
            }
        },
        recordClick(item) {
            fetch('/api/search/click', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ resource_type: item.resource_type, resource_id: item.id, tenant_id: tenantId })
            }).catch(() => {});
        },
        clear() { this.query = ''; this.results = []; this.open = false; this.$refs.input?.focus(); },
        close() { this.open = false; }
    };
}
</script>
@endpush
@endonce
