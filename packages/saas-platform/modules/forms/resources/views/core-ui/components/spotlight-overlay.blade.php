{{-- Spotlight overlay — used as a standalone full-screen search portal --}}
@props([
    'tenantId' => '',
    'placeholder' => 'جستجوی سراسری...',
    'autofocus' => true,
])

<div x-data="spotlightSearch('{{ $tenantId }}')"
     x-init="init()"
     @keydown.window="onGlobalKey($event)"
     x-cloak>

    {{-- Floating trigger button --}}
    <button @click="toggle()"
            class="fixed bottom-6 left-6 z-50 w-12 h-12 rounded-full bg-indigo-600 text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition flex items-center justify-center"
            title="جستجوی سراسری (Ctrl+K)"
            aria-label="جستجو">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
    </button>

    <template x-if="visible">
        <div class="fixed inset-0 z-[9998] flex items-center justify-center p-4"
             role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-md" @click="close()"></div>

            <div class="relative w-full max-w-3xl bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl overflow-hidden border border-white/30"
                 @keydown.escape.window="close()">

                <div class="flex items-center gap-4 px-6 py-4">
                    <svg class="w-6 h-6 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                    </svg>
                    <input x-ref="input"
                           x-model="query"
                           @input.debounce.200ms="search()"
                           type="search"
                           placeholder="{{ $placeholder }}"
                           class="flex-1 text-lg text-gray-900 bg-transparent border-0 outline-none placeholder-gray-400 font-light"
                           autocomplete="off"
                           spellcheck="false">
                    <template x-if="loading">
                        <div class="w-5 h-5 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin shrink-0"></div>
                    </template>
                </div>

                <div class="border-t border-gray-100"></div>

                <div class="overflow-y-auto" style="max-height: 60vh">
                    <template x-if="results.length === 0 && !query">
                        <div class="px-6 py-8 text-center text-gray-400 text-sm">
                            تایپ کنید تا جستجو شروع شود...
                        </div>
                    </template>

                    <template x-if="results.length === 0 && query && !loading">
                        <div class="px-6 py-10 text-center">
                            <p class="text-gray-500">نتیجه‌ای برای «<span x-text="query" class="font-semibold text-gray-800"></span>» یافت نشد</p>
                        </div>
                    </template>

                    <template x-for="(group, gKey) in groupedResults" :key="gKey">
                        <div class="py-2">
                            <p class="px-6 py-1.5 text-xs font-bold text-gray-400 uppercase tracking-widest" x-text="gKey"></p>
                            <template x-for="(item, idx) in group" :key="idx">
                                <a :href="item.url ?? '#'"
                                   class="flex items-center gap-4 px-6 py-3 hover:bg-indigo-50 transition cursor-pointer group">
                                    <div class="w-9 h-9 rounded-xl bg-indigo-100 group-hover:bg-indigo-200 flex items-center justify-center shrink-0 transition">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-800 truncate" x-text="item.label ?? item.title"></p>
                                        <p class="text-xs text-gray-500 truncate" x-text="item.meta ?? item.description ?? ''"></p>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                                    </svg>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
function spotlightSearch(tenantId) {
    return {
        visible: false,
        query: '',
        results: [],
        loading: false,
        get groupedResults() {
            return this.results.reduce((acc, r) => {
                const g = r.resource_type ?? r.type ?? 'نتایج';
                acc[g] = acc[g] ?? [];
                acc[g].push(r);
                return acc;
            }, {});
        },
        onGlobalKey(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); this.toggle(); }
        },
        toggle() { this.visible = !this.visible; if (this.visible) this.$nextTick(() => this.$refs.input?.focus()); },
        close()  { this.visible = false; this.query = ''; this.results = []; },
        async search() {
            if (!this.query || this.query.length < 2) { this.results = []; return; }
            this.loading = true;
            try {
                const r = await fetch(`/api/search?q=${encodeURIComponent(this.query)}&tenant_id=${tenantId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (r.ok) {
                    const d = await r.json();
                    this.results = d.results ?? [];
                }
            } catch {}
            this.loading = false;
        },
        init() {}
    };
}
</script>
@endpush
@endonce
