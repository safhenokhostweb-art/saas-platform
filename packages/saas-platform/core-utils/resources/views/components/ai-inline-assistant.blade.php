{{--
  core-ui::ai-inline-assistant
  Lightweight inline AI button + popover for any text field.
  Usage: <x-core-ui::ai-inline-assistant :fieldId="'reply_body'" :operation="'ticket-assistant'" :action="'suggest_reply'" />
--}}
@props([
    'fieldId'   => '',
    'operation' => 'suggest',
    'action'    => 'suggest_reply',
    'ticketId'  => null,
    'formId'    => null,
    'label'     => 'پیشنهاد AI',
])

<div
    x-data="{
        open:     false,
        loading:  false,
        result:   null,
        error:    null,
        fieldId:  '{{ $fieldId }}',
        operation:'{{ $operation }}',
        action:   '{{ $action }}',
        ticketId: {{ $ticketId ?? 'null' }},
        formId:   {{ $formId ?? 'null' }},

        async run() {
            this.loading = true;
            this.error   = null;
            this.result  = null;
            this.open    = true;

            const aiStore = Alpine.store('aiRuntime');
            if (!aiStore) { this.error = 'AI runtime not loaded'; this.loading = false; return; }

            const payload = { action: this.action };
            if (this.ticketId) payload.ticket_id = this.ticketId;
            if (this.formId)   payload.form_id   = this.formId;

            // Include current field value as context
            const el = document.getElementById(this.fieldId);
            if (el?.value) payload.draft = el.value;

            const out = await aiStore.execute(this.operation, payload);
            if (out) {
                const r = out.result ?? out;
                this.result = typeof r === 'string' ? r : (r.text ?? r.suggestion ?? r.reply ?? JSON.stringify(r));
            } else if (aiStore.error) {
                this.error = aiStore.error.message ?? 'خطای AI';
            }
            this.loading = false;
        },

        apply() {
            const el = document.getElementById(this.fieldId);
            if (el && this.result) {
                el.value = this.result;
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.open = false;
        },

        insert() {
            const el = document.getElementById(this.fieldId);
            if (el && this.result) {
                const start = el.selectionStart ?? el.value.length;
                el.value = el.value.slice(0, start) + '\n' + this.result + el.value.slice(el.selectionEnd ?? start);
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.open = false;
        }
    }"
    @keydown.escape.window="open = false"
    class="relative inline-flex items-center"
>
    <button
        @click="run()"
        type="button"
        :disabled="loading"
        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50 disabled:cursor-wait transition-colors"
        :aria-busy="loading"
    >
        <span x-show="!loading" aria-hidden="true">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </span>
        <span x-show="loading" class="animate-spin" aria-hidden="true">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </span>
        <span x-text="loading ? 'در حال تولید...' : '{{ $label }}'"></span>
    </button>

    {{-- Popover --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        @click.outside="open = false"
        class="absolute bottom-full right-0 mb-2 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden"
        role="dialog"
        aria-label="پیشنهاد هوش مصنوعی"
        style="display:none"
    >
        <div class="flex items-center justify-between px-3 py-2 bg-indigo-50 border-b border-indigo-100">
            <span class="text-xs font-semibold text-indigo-700">پیشنهاد هوش مصنوعی</span>
            <button @click="open=false" type="button" class="text-indigo-400 hover:text-indigo-600 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 p-0.5" aria-label="بستن">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Loading --}}
        <div x-show="loading" class="p-4 space-y-2 animate-pulse">
            <div class="h-3 bg-gray-200 rounded w-full"></div>
            <div class="h-3 bg-gray-100 rounded w-4/5"></div>
            <div class="h-3 bg-gray-100 rounded w-3/5"></div>
        </div>

        {{-- Error --}}
        <div x-show="!loading && error" class="p-4 text-xs text-red-600 bg-red-50">
            <span x-text="error"></span>
        </div>

        {{-- Result --}}
        <div x-show="!loading && result" class="p-3">
            <p class="text-xs text-gray-700 leading-relaxed whitespace-pre-wrap max-h-48 overflow-y-auto" x-text="result"></p>
            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                <button
                    @click="apply()"
                    type="button"
                    class="flex-1 px-3 py-1.5 text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                >
                    جایگزین کردن
                </button>
                <button
                    @click="insert()"
                    type="button"
                    class="flex-1 px-3 py-1.5 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                >
                    درج
                </button>
                <button
                    @click="run()"
                    type="button"
                    class="px-2 py-1.5 text-xs rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                    title="تولید مجدد"
                >
                    ↻
                </button>
            </div>
        </div>
    </div>
</div>
