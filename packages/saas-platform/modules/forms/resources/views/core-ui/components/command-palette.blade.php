{{--
  Command Palette — Ctrl/Cmd+K spotlight-style launcher.
  Usage: <x-core-ui::command-palette :commands="$commands" tenant-id="{{ $tenantId }}" />
--}}
@props([
    'commands' => [],
    'tenantId' => '',
    'placeholder' => 'جستجو یا دستور...',
])

<div x-data="commandPalette('{{ $tenantId }}')"
     x-init="init()"
     @keydown.window="handleGlobalKey($event)"
     x-cloak>

    {{-- ── Trigger: Ctrl+K badge ──────────────────────────────────────── --}}
    <button @click="open = true"
            class="hidden md:flex items-center gap-2 px-3 py-1.5 text-sm text-gray-500 bg-white border border-gray-200 rounded-lg shadow-sm hover:border-indigo-300 hover:text-indigo-600 transition"
            title="جستجو (Ctrl+K)"
            aria-label="باز کردن پالت دستور">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <span>جستجو</span>
        <kbd class="hidden lg:inline-flex items-center gap-0.5 text-xs border border-gray-200 rounded px-1 text-gray-400">⌘K</kbd>
    </button>

    {{-- ── Overlay ──────────────────────────────────────────────────────── --}}
    <template x-if="open">
        <div class="fixed inset-0 z-[9999] flex items-start justify-center pt-[15vh] px-4"
             role="dialog" aria-modal="true" aria-label="پالت دستور">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="close()"></div>

            {{-- Panel --}}
            <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden border border-white/30"
                 @keydown.escape="close()"
                 @keydown.arrow-down.prevent="moveDown()"
                 @keydown.arrow-up.prevent="moveUp()"
                 @keydown.enter.prevent="execute()">

                {{-- Search Input --}}
                <div class="flex items-center gap-3 px-4 py-3.5 border-b border-gray-100">
                    <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                    </svg>
                    <input x-ref="searchInput"
                           x-model="query"
                           @input="search()"
                           type="text"
                           :placeholder="'{{ $placeholder }}'"
                           class="flex-1 text-base text-gray-800 bg-transparent border-0 outline-none placeholder-gray-400"
                           autocomplete="off"
                           spellcheck="false">
                    <template x-if="query">
                        <button @click="query = ''; search()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </template>
                    <kbd class="hidden sm:flex items-center text-xs text-gray-400 border border-gray-200 rounded px-1.5 py-0.5">ESC</kbd>
                </div>

                {{-- Results --}}
                <div class="max-h-[50vh] overflow-y-auto py-2" x-ref="resultsList">

                    {{-- Recent (when no query) --}}
                    <template x-if="!query && recentItems.length > 0">
                        <div>
                            <p class="px-4 py-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">اخیر</p>
                            <template x-for="(item, i) in recentItems" :key="'r'+i">
                                <button @click="executeItem(item)"
                                        :class="selectedIndex === i ? 'bg-indigo-50' : 'hover:bg-gray-50'"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-right transition text-sm">
                                    <span class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 shrink-0 text-xs">↩</span>
                                    <span class="flex-1 text-gray-800 font-medium" x-text="item.label"></span>
                                    <span class="text-xs text-gray-400" x-text="item.category"></span>
                                </button>
                            </template>
                            <div class="border-t border-gray-100 my-2"></div>
                        </div>
                    </template>

                    {{-- Command results --}}
                    <template x-if="results.length > 0">
                        <div>
                            <template x-if="query">
                                <p class="px-4 py-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">نتایج</p>
                            </template>
                            <template x-for="(item, i) in results" :key="item.key ?? i">
                                <button @click="executeItem(item)"
                                        :id="'cmd-' + i"
                                        :class="selectedIndex === (recentItems.length > 0 && !query ? 0 : 0) + i ? 'bg-indigo-50 text-indigo-700' : 'hover:bg-gray-50'"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-right transition text-sm group">
                                    <span class="w-7 h-7 rounded-lg bg-gray-100 group-hover:bg-indigo-100 flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5 text-gray-500 group-hover:text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                        </svg>
                                    </span>
                                    <div class="flex-1 min-w-0 text-right">
                                        <p class="font-medium text-gray-800 truncate" x-text="item.label ?? item.title"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="item.description ?? item.meta ?? ''"></p>
                                    </div>
                                    <template x-if="item.shortcut">
                                        <kbd class="text-xs border border-gray-200 rounded px-1.5 py-0.5 text-gray-400 shrink-0" x-text="item.shortcut"></kbd>
                                    </template>
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- Empty --}}
                    <template x-if="query && results.length === 0 && !loading">
                        <div class="py-10 text-center text-sm text-gray-400">
                            <svg class="w-10 h-10 mx-auto mb-3 opacity-20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                            </svg>
                            نتیجه‌ای برای "<span x-text="query" class="font-medium text-gray-600"></span>" یافت نشد
                        </div>
                    </template>

                    <template x-if="loading">
                        <div class="py-8 text-center">
                            <div class="inline-block w-5 h-5 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </template>
                </div>

                {{-- Footer --}}
                <div class="flex items-center gap-4 px-4 py-2 border-t border-gray-100 bg-gray-50/50 text-xs text-gray-400">
                    <span class="flex items-center gap-1"><kbd class="border border-gray-200 rounded px-1">↑↓</kbd> انتخاب</span>
                    <span class="flex items-center gap-1"><kbd class="border border-gray-200 rounded px-1">↵</kbd> اجرا</span>
                    <span class="flex items-center gap-1"><kbd class="border border-gray-200 rounded px-1">ESC</kbd> بستن</span>
                </div>
            </div>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
function commandPalette(tenantId) {
    return {
        open: false,
        query: '',
        results: [],
        recentItems: [],
        selectedIndex: 0,
        loading: false,
        allCommands: @json($commands),
        debounceTimer: null,

        init() {
            this.$watch('open', v => { if (v) this.$nextTick(() => { this.$refs.searchInput?.focus(); this.search(); }); });
        },

        handleGlobalKey(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); this.open = !this.open; }
        },

        search() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(async () => {
                if (!this.query) {
                    this.results = this.allCommands.slice(0, 8);
                    return;
                }
                this.loading = true;
                const local = this.allCommands.filter(c =>
                    c.label?.toLowerCase().includes(this.query.toLowerCase()) ||
                    c.description?.toLowerCase().includes(this.query.toLowerCase())
                );
                // Also fetch search results
                try {
                    const r = await fetch(`/api/search/suggest?q=${encodeURIComponent(this.query)}&tenant_id=${tenantId}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (r.ok) {
                        const remote = await r.json();
                        this.results = [...local, ...(remote.results ?? [])].slice(0, 12);
                    } else {
                        this.results = local.slice(0, 12);
                    }
                } catch { this.results = local.slice(0, 12); }
                this.loading = false;
                this.selectedIndex = 0;
            }, 150);
        },

        moveDown() { this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1); },
        moveUp()   { this.selectedIndex = Math.max(this.selectedIndex - 1, 0); },

        execute() {
            const item = this.results[this.selectedIndex];
            if (item) this.executeItem(item);
        },

        executeItem(item) {
            this.recordRecent(item);
            if (item.url) { window.location.href = item.url; }
            else if (item.handler) { eval(item.handler); }
            this.close();
        },

        recordRecent(item) {
            if (!item.key) return;
            fetch(`/api/search/record-command`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                body: JSON.stringify({ command_key: item.key, tenant_id: tenantId })
            }).catch(() => {});
        },

        close() { this.open = false; this.query = ''; this.results = []; this.selectedIndex = 0; }
    };
}
</script>
@endpush
@endonce
