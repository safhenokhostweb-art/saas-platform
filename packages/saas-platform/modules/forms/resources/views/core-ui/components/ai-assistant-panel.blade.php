{{--
  AI Assistant Panel — full-featured sidebar/modal AI assistant.
  Supports: prompt input, generation progress, streamed output, suggestions, history.
  Usage:
    <x-core-ui::ai-assistant-panel
        domain="forms"
        endpoint="/api/ai/form-suggestions"
        :context="['form_title' => $form->title]"
        panel-id="form-ai-panel"
    />
--}}
@props([
    'domain'    => 'general',
    'endpoint'  => '/api/ai/suggestions',
    'context'   => [],
    'panelId'   => 'ai-panel',
    'title'     => 'دستیار هوش مصنوعی',
    'compact'   => false,
    'maxHistory'=> 10,
])

<div {{ $attributes->merge(['class' => 'ai-assistant-panel flex flex-col bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden']) }}
     x-data="aiAssistantPanel('{{ $domain }}', '{{ $endpoint }}', @js($context), {{ $maxHistory }})"
     id="{{ $panelId }}"
     role="complementary"
     aria-label="{{ $title }}">

    {{-- Header --}}
    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-violet-50/40">
        <span class="w-6 h-6 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2a7 7 0 0 1 7 7c0 2.62-1.44 4.92-3.58 6.17L15 17H9l-.42-1.83A7.004 7.004 0 0 1 5 9a7 7 0 0 1 7-7Zm0 18h0a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2Z"/>
            </svg>
        </span>
        <span class="text-xs font-bold text-indigo-800">{{ $title }}</span>

        <div class="mr-auto flex items-center gap-1">
            <template x-if="lastProvider">
                <span class="text-xs text-gray-400 capitalize" x-text="lastProvider"></span>
            </template>
            <button @click="clearHistory()"
                    title="پاک کردن تاریخچه"
                    class="text-gray-300 hover:text-gray-500 transition p-0.5 rounded focus:outline-none focus:ring-1 focus:ring-gray-300"
                    aria-label="پاک کردن تاریخچه">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- History / Output area --}}
    <div class="flex-1 overflow-y-auto p-3 space-y-3 min-h-0" style="max-height: 320px;" x-ref="outputArea">

        {{-- Empty state --}}
        <template x-if="history.length === 0 && !loading">
            <div class="flex flex-col items-center justify-center py-6 text-center">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-100 to-violet-100 rounded-2xl flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2a7 7 0 0 1 7 7c0 2.62-1.44 4.92-3.58 6.17L15 17H9l-.42-1.83A7.004 7.004 0 0 1 5 9a7 7 0 0 1 7-7Z"/>
                    </svg>
                </div>
                <p class="text-xs text-gray-400 font-medium">از دستیار هوش مصنوعی بپرسید</p>
                <p class="text-xs text-gray-300 mt-0.5">برای دریافت پیشنهاد، سوال خود را تایپ کنید</p>
            </div>
        </template>

        {{-- History items --}}
        <template x-for="(item, idx) in history" :key="idx">
            <div>
                {{-- User prompt --}}
                <div class="flex justify-end mb-1">
                    <div class="max-w-[85%] bg-indigo-600 text-white text-xs px-2.5 py-1.5 rounded-xl rounded-br-sm">
                        <p x-text="item.prompt" class="leading-relaxed whitespace-pre-wrap" dir="auto"></p>
                    </div>
                </div>
                {{-- AI response --}}
                <div class="max-w-[92%]">
                    <x-core-ui::ai-stream-output
                        x-bind:content="item.response"
                        x-bind:loading="false"
                        :show-copy="true"
                        min-height="40px"
                    />
                    <div class="flex items-center gap-2 mt-1">
                        <x-core-ui::ai-confidence-badge x-bind:score="item.confidence ?? 1" x-bind:source="item.source ?? 'ai'" compact />
                        <button @click="useResponse(item.response)"
                                class="text-xs text-indigo-600 hover:text-indigo-800 underline"
                                :aria-label="'استفاده از پاسخ ' + (idx + 1)">
                            استفاده
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Loading state --}}
        <template x-if="loading">
            <div>
                <x-core-ui::ai-generation-progress
                    x-bind:steps="generationSteps"
                    x-bind:current-step="generationStep"
                    x-bind:loading="loading"
                    compact
                />
                <x-core-ui::ai-stream-output loading placeholder="هوش مصنوعی در حال پردازش..." min-height="60px" />
            </div>
        </template>

        {{-- Error --}}
        <template x-if="error">
            <div class="flex items-center gap-2 p-2 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                </svg>
                <span x-text="errorMessage"></span>
                <button @click="error = false" class="mr-auto text-red-400 hover:text-red-600">×</button>
            </div>
        </template>
    </div>

    {{-- Input area --}}
    <div class="border-t border-gray-100 px-3 py-2 bg-gray-50/50">

        {{-- Quick prompt chips --}}
        <template x-if="quickPrompts.length > 0 && history.length === 0">
            <div class="flex flex-wrap gap-1 mb-2">
                <template x-for="(qp, qi) in quickPrompts" :key="qi">
                    <button @click="prompt = qp; submit()"
                            class="text-xs text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-full px-2 py-0.5 transition focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            x-text="qp"></button>
                </template>
            </div>
        </template>

        <div class="flex gap-2 items-end">
            <textarea x-model="prompt"
                      @keydown.enter.prevent.stop="if ($event.shiftKey) prompt += '\n'; else submit()"
                      :disabled="loading"
                      placeholder="سوال یا دستور خود را بنویسید..."
                      class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2 resize-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition placeholder-gray-400 disabled:opacity-50"
                      rows="1"
                      dir="auto"
                      style="min-height:36px;max-height:100px"
                      @input="autoResize($el)"
                      aria-label="پیام به دستیار"></textarea>
            <button @click="submit()"
                    :disabled="loading || !prompt.trim()"
                    class="w-8 h-8 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl flex items-center justify-center transition shrink-0 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    :aria-label="loading ? 'در حال پردازش' : 'ارسال'"
                    :aria-busy="loading">
                <template x-if="!loading">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                    </svg>
                </template>
                <template x-if="loading">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                </template>
            </button>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function aiAssistantPanel(domain, endpoint, context, maxHistory) {
    return {
        prompt: '',
        loading: false,
        error: false,
        errorMessage: '',
        history: [],
        lastProvider: null,
        generationStep: 0,
        generationSteps: [
            { key: 'thinking',   label: 'در حال فکر کردن...' },
            { key: 'generating', label: 'در حال نوشتن...' },
            { key: 'finalizing', label: 'نهایی‌سازی...' },
        ],
        quickPrompts: domain === 'forms'
            ? ['پیشنهاد فیلد بده', 'بررسی دسترسی‌پذیری', 'اتوماسیون پیشنهاد بده']
            : domain === 'tickets'
            ? ['پیشنهاد پاسخ بده', 'خلاصه تیکت', 'تحلیل احساسات']
            : [],

        async submit() {
            if (!this.prompt.trim() || this.loading) return;
            const userPrompt = this.prompt.trim();
            this.prompt = '';
            this.loading = true;
            this.error = false;
            this.generationStep = 0;

            try {
                const stepInterval = setInterval(() => {
                    if (this.generationStep < this.generationSteps.length - 1) this.generationStep++;
                }, 700);

                const r = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ prompt: userPrompt, domain, context })
                });

                clearInterval(stepInterval);
                this.generationStep = this.generationSteps.length - 1;

                if (!r.ok) throw new Error('AI request failed');

                const d = await r.json();
                this.history.push({
                    prompt: userPrompt,
                    response: d.text ?? d.result ?? '',
                    confidence: d.confidence ?? 1,
                    source: d.source ?? 'ai',
                    provider: d.provider ?? null,
                });
                this.lastProvider = d.provider ?? null;

                if (this.history.length > maxHistory) {
                    this.history = this.history.slice(-maxHistory);
                }

                this.$dispatch('ai-response', { prompt: userPrompt, response: d, domain });
                await this.$nextTick();
                this.$refs.outputArea?.scrollTo({ top: this.$refs.outputArea.scrollHeight, behavior: 'smooth' });

            } catch (e) {
                this.error = true;
                this.errorMessage = 'خطا در دریافت پاسخ. لطفاً دوباره تلاش کنید.';
            }

            this.loading = false;
        },

        useResponse(text) {
            this.$dispatch('ai-insert', { text, domain });
        },

        clearHistory() {
            this.history = [];
            this.error = false;
        },

        autoResize(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 100) + 'px';
        }
    };
}
</script>
@endpush
@endonce
