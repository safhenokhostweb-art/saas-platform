{{--
  x-core-ui::toast — Canonical toast notification system.
  Usage: Place once per layout. Dispatch 'ui-toast' browser event to show:
    $dispatch('ui-toast', { message: 'Saved!', variant: 'success', duration: 4000 })
  Props:
    position : top-right | top-left | bottom-right | bottom-left | top-center (default: top-right)
--}}
@props([
    'position' => 'top-right',
])

@php
$positions = [
    'top-right'    => 'top-4 right-4 items-end',
    'top-left'     => 'top-4 left-4 items-start',
    'top-center'   => 'top-4 left-1/2 -translate-x-1/2 items-center',
    'bottom-right' => 'bottom-4 right-4 items-end',
    'bottom-left'  => 'bottom-4 left-4 items-start',
];
$pos = $positions[$position] ?? $positions['top-right'];
@endphp

<div
    x-data="{
        toasts: [],
        nextId: 0,
        add(payload) {
            const id = ++this.nextId;
            const duration = payload.duration ?? 4000;
            this.toasts.push({
                id,
                message:  payload.message  ?? '',
                variant:  payload.variant  ?? 'info',
                title:    payload.title    ?? null,
                duration,
                removing: false,
            });
            if (duration > 0) {
                setTimeout(() => this.remove(id), duration);
            }
        },
        remove(id) {
            const idx = this.toasts.findIndex(t => t.id === id);
            if (idx === -1) return;
            this.toasts[idx].removing = true;
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 300);
        },
    }"
    @ui-toast.window="add($event.detail)"
    class="fixed {{ $pos }} z-[var(--ui-z-toast)] flex flex-col gap-2 pointer-events-none w-full max-w-sm"
    aria-live="polite"
    aria-atomic="false"
    role="status"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="pointer-events-auto flex items-start gap-3 w-full rounded-[var(--ui-radius-xl)]
                   bg-[var(--ui-surface)] border shadow-[var(--ui-shadow-toast)] px-4 py-3
                   select-none"
            :class="{
                'border-[var(--ui-success-border)] bg-[var(--ui-success-light)]': toast.variant === 'success',
                'border-[var(--ui-danger-border)]  bg-[var(--ui-danger-light)]':  toast.variant === 'danger',
                'border-[var(--ui-warning-border)] bg-[var(--ui-warning-light)]': toast.variant === 'warning',
                'border-[var(--ui-info-border)]    bg-[var(--ui-info-light)]':    toast.variant === 'info',
                'border-[var(--ui-border)]':                                       !['success','danger','warning','info'].includes(toast.variant),
            }"
            x-show="!toast.removing"
            x-transition:enter="transition ease-[var(--ui-ease-spring)] duration-[var(--ui-duration-moderate)]"
            x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-[var(--ui-ease-default)] duration-[var(--ui-duration-normal)]"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            {{-- Icon --}}
            <svg class="w-5 h-5 shrink-0 mt-0.5"
                 :class="{
                     'text-[var(--ui-success)]': toast.variant === 'success',
                     'text-[var(--ui-danger)]':  toast.variant === 'danger',
                     'text-[var(--ui-warning)]': toast.variant === 'warning',
                     'text-[var(--ui-info)]':    toast.variant === 'info',
                     'text-[var(--ui-text-muted)]': !['success','danger','warning','info'].includes(toast.variant),
                 }"
                 viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <template x-if="toast.variant === 'success'">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                </template>
                <template x-if="toast.variant === 'danger'">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                </template>
                <template x-if="toast.variant === 'warning'">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </template>
                <template x-if="!['success','danger','warning'].includes(toast.variant)">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
                </template>
            </svg>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
                <p x-show="toast.title" x-text="toast.title"
                   class="text-sm font-semibold text-[var(--ui-text-primary)] mb-0.5"></p>
                <p x-text="toast.message" class="text-sm text-[var(--ui-text-secondary)]"></p>
            </div>

            {{-- Dismiss --}}
            <button type="button"
                    @click="remove(toast.id)"
                    class="shrink-0 rounded-[var(--ui-radius-md)] p-1 hover:bg-black/10
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current
                           transition-colors duration-[var(--ui-duration-fast)] text-[var(--ui-text-muted)]"
                    aria-label="بستن">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>
