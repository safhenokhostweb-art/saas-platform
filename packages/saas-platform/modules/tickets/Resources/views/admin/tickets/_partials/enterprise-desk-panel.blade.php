{{--
  Enterprise Support Desk Panel — AI suggestions, canned responses, collision detection.
  Rendered inside the ticket show view's Alpine context.
--}}

{{-- ── AI Reply Suggestions ─────────────────────────────────────────────────── --}}
<div x-data="aiReplyAssistant('{{ $ticket->id }}', '{{ addslashes($ticket->subject) }}')"
     class="mb-3">

    {{-- Trigger --}}
    <button @click="toggle()"
            :disabled="loading"
            class="flex items-center gap-2 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition group"
            title="پیشنهاد هوشمند پاسخ">
        <span class="w-5 h-5 rounded bg-indigo-100 group-hover:bg-indigo-200 flex items-center justify-center transition">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
            </svg>
        </span>
        <template x-if="loading"><span>در حال تحلیل...</span></template>
        <template x-if="!loading"><span>پیشنهاد AI</span></template>
    </button>

    {{-- Suggestions Panel --}}
    <template x-if="open && suggestions.length > 0">
        <div class="mt-2 p-3 bg-gradient-to-br from-indigo-50 to-purple-50 border border-indigo-100 rounded-xl space-y-2">
            <p class="text-xs font-semibold text-indigo-700 mb-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                </svg>
                پیشنهادهای هوشمند
            </p>
            <template x-for="(s, i) in suggestions" :key="i">
                <div class="bg-white rounded-lg p-3 border border-indigo-100 hover:border-indigo-300 transition cursor-pointer group"
                     @click="applysuggestion(s.text)">
                    <p class="text-sm text-gray-700 leading-relaxed" x-text="s.text"></p>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-xs text-gray-400"
                              x-text="'اطمینان: ' + Math.round((s.confidence ?? 0.5) * 100) + '%'"></span>
                        <button class="text-xs text-indigo-600 font-semibold opacity-0 group-hover:opacity-100 transition">
                            استفاده ←
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <template x-if="open && suggestions.length === 0 && !loading">
        <p class="text-xs text-gray-400 mt-1">پیشنهادی موجود نیست</p>
    </template>
</div>

{{-- ── Canned Responses ─────────────────────────────────────────────────────── --}}
<div x-data="cannedResponses()" class="mb-3">
    <button @click="open = !open"
            class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-indigo-600 transition font-medium">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/>
        </svg>
        پاسخ‌های آماده
    </button>

    <template x-if="open">
        <div class="mt-2 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden z-10 relative">
            <div class="p-2 border-b border-gray-100">
                <input x-model="search" type="text" placeholder="جستجو..."
                       class="w-full text-xs px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg outline-none focus:border-indigo-400">
            </div>
            <div class="max-h-48 overflow-y-auto">
                <template x-for="r in filteredResponses" :key="r.key">
                    <button @click="$dispatch('insert-canned', r.text); open = false"
                            class="w-full text-right px-3 py-2 hover:bg-indigo-50 transition text-sm border-b border-gray-50 last:border-0">
                        <p class="font-semibold text-gray-800 text-xs" x-text="r.title"></p>
                        <p class="text-xs text-gray-400 truncate" x-text="r.text.substring(0, 60) + '...'"></p>
                    </button>
                </template>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
function aiReplyAssistant(ticketId, subject) {
    return {
        open: false, loading: false, suggestions: [],
        toggle() {
            if (this.open) { this.open = false; return; }
            this.load();
        },
        async load() {
            this.loading = true; this.open = true;
            try {
                const r = await fetch(`/api/ai/ticket-reply-suggestions?ticket_id=${ticketId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (r.ok) { const d = await r.json(); this.suggestions = d.suggestions ?? []; }
            } catch {}
            this.loading = false;
        },
        applysuggestion(text) {
            // Dispatch to parent Alpine context
            this.$dispatch('apply-reply-suggestion', { text });
            this.open = false;
        }
    };
}

function cannedResponses() {
    return {
        open: false, search: '',
        responses: [
            { key: 'greet',      title: 'سلام و خوش‌آمد',           text: 'سلام،\n\nممنون از تماس با پشتیبانی. خوشحالیم که کمک کنیم.\n\n' },
            { key: 'need_info',  title: 'نیاز به اطلاعات بیشتر',    text: 'سلام،\n\nبرای بررسی دقیق‌تر، لطفاً اطلاعات زیر را ارسال کنید:\n\n' },
            { key: 'resolved',   title: 'مشکل حل شد',               text: 'سلام،\n\nخوشحالیم که مشکل حل شد. در صورت نیاز، در خدمتیم.\n\n' },
            { key: 'escalated',  title: 'ارجاع به تیم فنی',         text: 'سلام،\n\nتیکت به تیم تخصصی ارجاع شد. ظرف ۲۴ ساعت پاسخ خواهید گرفت.\n\n' },
            { key: 'apology',    title: 'عذرخواهی از تأخیر',        text: 'سلام،\n\nاز تأخیر در پاسخ‌گویی عذرخواهی می‌کنیم. بلافاصله بررسی می‌شود.\n\n' },
        ],
        get filteredResponses() {
            if (!this.search) return this.responses;
            const q = this.search.toLowerCase();
            return this.responses.filter(r => r.title.toLowerCase().includes(q) || r.text.toLowerCase().includes(q));
        }
    };
}
</script>
@endpush
