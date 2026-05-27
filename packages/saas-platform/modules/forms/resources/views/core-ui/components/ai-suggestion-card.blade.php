{{--
  AI Suggestion Card — a reviewable AI suggestion with accept/edit/reject actions.
  Usage:
    <x-core-ui::ai-suggestion-card
        :suggestion="$suggestion"
        domain="forms"
        @accepted="handleAccepted"
    />
  suggestion shape: {text, confidence, source, provider, tokens}
--}}
@props([
    'suggestion'    => [],
    'domain'        => 'general',
    'showMeta'      => true,
    'actionKey'     => '',
    'compact'       => false,
])

<div {{ $attributes->merge(['class' => 'ai-suggestion-card border border-indigo-200 rounded-2xl overflow-hidden bg-indigo-50/40']) }}
     x-data="aiSuggestionCard(@js($suggestion), '{{ $domain }}', '{{ $actionKey }}')"
     role="region"
     aria-label="پیشنهاد هوش مصنوعی">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-2 px-3 pt-2.5 pb-1">
        <div class="flex items-center gap-1.5">
            <span class="w-5 h-5 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-md flex items-center justify-center shrink-0">
                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2a7 7 0 0 1 7 7c0 2.62-1.44 4.92-3.58 6.17L15 17H9l-.42-1.83A7.004 7.004 0 0 1 5 9a7 7 0 0 1 7-7Zm0 18h0a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2Z"/>
                </svg>
            </span>
            <span class="text-xs font-semibold text-indigo-700">پیشنهاد هوش مصنوعی</span>
            @if($showMeta && !empty($suggestion['confidence']))
            <x-core-ui::ai-confidence-badge :score="$suggestion['confidence']" :source="$suggestion['source'] ?? null" compact />
            @endif
        </div>

        @if($showMeta && !empty($suggestion['provider']))
        <span class="text-xs text-gray-400 capitalize">{{ $suggestion['provider'] }}</span>
        @endif
    </div>

    {{-- Content --}}
    <div class="px-3 pb-2">
        <template x-if="!editing">
            <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap" x-text="text"></p>
        </template>
        <template x-if="editing">
            <textarea x-model="editedText"
                      class="w-full text-sm text-gray-800 border border-indigo-300 rounded-lg p-2 resize-y min-h-16 focus:ring-2 focus:ring-indigo-200 outline-none"
                      dir="auto"
                      rows="3"></textarea>
        </template>
    </div>

    {{-- Actions --}}
    <div class="border-t border-indigo-100 px-3 py-1.5 flex items-center gap-2 bg-white/60">
        <template x-if="!editing && !accepted && !rejected">
            <div class="flex items-center gap-2 w-full">
                <button @click="accept()"
                        class="flex items-center gap-1 text-xs font-medium text-emerald-700 hover:text-emerald-800 px-2 py-1 rounded-lg hover:bg-emerald-50 transition focus:outline-none focus:ring-2 focus:ring-emerald-300"
                        aria-label="قبول پیشنهاد">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    استفاده
                </button>
                <button @click="startEdit()"
                        class="flex items-center gap-1 text-xs font-medium text-gray-600 hover:text-gray-800 px-2 py-1 rounded-lg hover:bg-gray-100 transition focus:outline-none focus:ring-2 focus:ring-gray-300"
                        aria-label="ویرایش پیشنهاد">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                    </svg>
                    ویرایش
                </button>
                <button @click="reject()"
                        class="flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-700 px-2 py-1 rounded-lg hover:bg-red-50 transition mr-auto focus:outline-none focus:ring-2 focus:ring-red-300"
                        aria-label="رد پیشنهاد">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    رد
                </button>
            </div>
        </template>

        <template x-if="editing">
            <div class="flex items-center gap-2 w-full">
                <button @click="confirmEdit()"
                        class="text-xs font-medium text-indigo-700 hover:text-indigo-800 px-2 py-1 rounded-lg hover:bg-indigo-50 transition focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    تأیید ویرایش
                </button>
                <button @click="cancelEdit()"
                        class="text-xs font-medium text-gray-500 hover:text-gray-700 px-2 py-1 rounded-lg hover:bg-gray-100 transition focus:outline-none focus:ring-2 focus:ring-gray-300">
                    انصراف
                </button>
            </div>
        </template>

        <template x-if="accepted && !editing">
            <div class="flex items-center gap-1.5 text-xs text-emerald-700 font-medium w-full">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                </svg>
                استفاده شد
                <button @click="undo()" class="mr-auto text-xs text-gray-400 hover:text-gray-600 underline">بازگشت</button>
            </div>
        </template>

        <template x-if="rejected && !editing">
            <div class="flex items-center gap-1.5 text-xs text-gray-400 font-medium w-full">
                رد شد
                <button @click="undo()" class="mr-auto text-xs text-gray-400 hover:text-gray-600 underline">بازگشت</button>
            </div>
        </template>
    </div>
</div>

@once
@push('scripts')
<script>
function aiSuggestionCard(suggestion, domain, actionKey) {
    return {
        text: suggestion.text ?? '',
        editedText: suggestion.text ?? '',
        accepted: false,
        rejected: false,
        editing: false,
        suggestion,

        accept() {
            this.accepted = true;
            this.$dispatch('ai-suggestion-accepted', {
                text: this.text, domain, actionKey, suggestion: this.suggestion
            });
            this.recordFeedback('accepted');
        },
        reject() {
            this.rejected = true;
            this.$dispatch('ai-suggestion-rejected', { domain, actionKey, suggestion: this.suggestion });
            this.recordFeedback('rejected');
        },
        startEdit() {
            this.editedText = this.text;
            this.editing = true;
        },
        confirmEdit() {
            this.text = this.editedText;
            this.editing = false;
            this.accepted = true;
            this.$dispatch('ai-suggestion-accepted', {
                text: this.text, edited: true, domain, actionKey, suggestion: this.suggestion
            });
            this.recordFeedback('edited');
        },
        cancelEdit() {
            this.editing = false;
            this.editedText = this.text;
        },
        undo() {
            this.accepted = false;
            this.rejected = false;
            this.editing = false;
            this.$dispatch('ai-suggestion-undone', { domain, actionKey });
        },
        recordFeedback(outcome) {
            fetch('/api/ai/feedback', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ domain, action_key: actionKey, outcome, suggestion_hash: btoa(this.suggestion.text ?? '').slice(0, 32) })
            }).catch(() => {});
        }
    };
}
</script>
@endpush
@endonce
