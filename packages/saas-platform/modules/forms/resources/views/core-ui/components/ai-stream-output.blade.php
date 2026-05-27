{{--
  AI Stream Output — animated output panel that simulates streaming AI responses.
  Usage:
    <x-core-ui::ai-stream-output
        x-bind:content="aiResponse"
        x-bind:loading="isLoading"
        :show-copy="true"
    />
--}}
@props([
    'content'       => '',
    'loading'       => false,
    'showCopy'      => true,
    'showMeta'      => false,
    'placeholder'   => 'منتظر پاسخ هوش مصنوعی...',
    'minHeight'     => '80px',
])

<div {{ $attributes->merge(['class' => 'ai-stream-output relative rounded-xl border border-gray-200 bg-gray-50/60 overflow-hidden']) }}
     x-data="aiStreamOutput()"
     :data-loading="loading">

    {{-- Loading state --}}
    <template x-if="loading">
        <div class="absolute inset-0 flex items-start gap-3 p-3">
            <div class="w-5 h-5 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-md flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-3 h-3 text-white animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2a7 7 0 0 1 7 7c0 2.62-1.44 4.92-3.58 6.17L15 17H9l-.42-1.83A7.004 7.004 0 0 1 5 9a7 7 0 0 1 7-7Z"/>
                </svg>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-1.5 h-5">
                    <span class="text-xs text-gray-400">{{ $placeholder }}</span>
                    <span class="flex gap-0.5 items-end h-4">
                        <span class="w-1 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                        <span class="w-1 h-3 bg-indigo-500 rounded-full animate-bounce" style="animation-delay:120ms"></span>
                        <span class="w-1 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay:240ms"></span>
                    </span>
                </div>
                <div class="mt-2 space-y-1.5">
                    <div class="h-3 bg-gray-200 rounded animate-pulse w-4/5"></div>
                    <div class="h-3 bg-gray-200 rounded animate-pulse w-full"></div>
                    <div class="h-3 bg-gray-200 rounded animate-pulse w-2/3"></div>
                </div>
            </div>
        </div>
    </template>

    {{-- Content --}}
    <template x-if="!loading">
        <div class="p-3">
            <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap" dir="auto"
                 x-text="content || ''"
                 style="min-height: {{ $minHeight }}"></div>

            @if($showCopy)
            <template x-if="content && content.length > 0">
                <div class="mt-2 flex justify-end">
                    <button @click="copy()"
                            class="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600 transition px-2 py-0.5 rounded hover:bg-gray-100"
                            :aria-label="copied ? 'کپی شد' : 'کپی متن'">
                        <template x-if="!copied">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
                            </svg>
                        </template>
                        <template x-if="copied">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                            </svg>
                        </template>
                        <span x-text="copied ? 'کپی شد!' : 'کپی'"></span>
                    </button>
                </div>
            </template>
            @endif
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
function aiStreamOutput() {
    return {
        copied: false,
        async copy() {
            const el = this.$el.querySelector('[x-text="content || \'\'"]');
            const text = el?.textContent ?? '';
            if (!text) return;
            try {
                await navigator.clipboard.writeText(text);
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            } catch {}
        }
    };
}
</script>
@endpush
@endonce
