{{--
  AI Generation Progress — multi-step AI task progress bar with status.
  Usage:
    <x-core-ui::ai-generation-progress
        x-bind:steps="generationSteps"
        x-bind:current-step="currentStep"
        x-bind:loading="isGenerating"
    />
  steps shape: [{key, label, icon?}]
  currentStep: index (0-based) or step key string
--}}
@props([
    'steps'       => [['key' => 'thinking', 'label' => 'در حال پردازش...', 'icon' => '🤔']],
    'currentStep' => 0,
    'loading'     => false,
    'compact'     => false,
    'errorMessage'=> '',
])

<div {{ $attributes->merge(['class' => 'ai-generation-progress']) }}
     x-data="aiGenerationProgress(@js($steps), @js($currentStep), @js($loading), '{{ $errorMessage }}')"
     role="progressbar"
     :aria-valuenow="progressPct"
     aria-valuemin="0"
     aria-valuemax="100"
     :aria-label="'پیشرفت هوش مصنوعی: ' + progressPct + '٪'">

    <template x-if="hasError">
        <div class="flex items-center gap-2 p-2 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
            </svg>
            <span x-text="errorMessage"></span>
        </div>
    </template>

    <template x-if="!hasError">
        <div>
            {{-- Progress Bar --}}
            <div class="relative h-1.5 bg-gray-100 rounded-full overflow-hidden mb-2">
                <div class="absolute inset-y-0 right-0 bg-gradient-to-l from-indigo-500 to-violet-500 rounded-full transition-all duration-500"
                     :style="`width: ${progressPct}%`"></div>
                <template x-if="isLoading">
                    <div class="absolute inset-y-0 right-0 bg-gradient-to-l from-indigo-300/50 to-violet-300/50 animate-pulse rounded-full w-full"></div>
                </template>
            </div>

            {{-- Step indicators --}}
            @if(!$compact)
            <div class="flex items-center gap-1" :class="steps.length > 4 ? 'flex-wrap' : ''">
                <template x-for="(step, idx) in steps" :key="idx">
                    <div class="flex items-center gap-1">
                        <div class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs transition"
                             :class="{
                                 'bg-indigo-100 text-indigo-700 font-medium': currentIdx === idx && isLoading,
                                 'bg-emerald-50 text-emerald-700': currentIdx > idx || (!isLoading && currentIdx === idx),
                                 'text-gray-400': currentIdx < idx && isLoading,
                                 'bg-gray-50': currentIdx < idx
                             }">
                            <template x-if="currentIdx > idx || (!isLoading && currentIdx >= idx)">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                </svg>
                            </template>
                            <template x-if="currentIdx === idx && isLoading">
                                <svg class="w-2.5 h-2.5 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                </svg>
                            </template>
                            <span x-text="step.label"></span>
                        </div>
                        <template x-if="idx < steps.length - 1">
                            <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                            </svg>
                        </template>
                    </div>
                </template>
            </div>
            @endif

            @if($compact)
            <div class="flex items-center gap-1.5 text-xs text-gray-500">
                <template x-if="isLoading">
                    <svg class="w-3.5 h-3.5 animate-spin text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                </template>
                <span x-text="currentLabel"></span>
            </div>
            @endif
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
function aiGenerationProgress(steps, currentStep, loading, errorMsg) {
    return {
        steps: steps || [],
        currentStep: currentStep ?? 0,
        isLoading: loading,
        hasError: !!errorMsg,
        errorMessage: errorMsg || '',

        get currentIdx() {
            if (typeof this.currentStep === 'string') {
                return this.steps.findIndex(s => s.key === this.currentStep);
            }
            return this.currentStep;
        },

        get progressPct() {
            if (this.steps.length === 0) return this.isLoading ? 50 : 100;
            if (!this.isLoading) return 100;
            const idx = Math.max(0, this.currentIdx);
            return Math.min(95, Math.round((idx / this.steps.length) * 100) + (this.isLoading ? 5 : 0));
        },

        get currentLabel() {
            const s = this.steps[this.currentIdx];
            return s?.label ?? 'در حال پردازش...';
        }
    };
}
</script>
@endpush
@endonce
