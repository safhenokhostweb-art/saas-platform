{{--
  x-core-ui::confirm-modal — Canonical destructive action confirmation modal.
  Replaces native confirm() entirely.

  Usage:
    <x-core-ui::confirm-modal
        name="delete-item"
        title="حذف آیتم"
        message="آیا از حذف این آیتم مطمئن هستید؟"
        confirm-label="بله، حذف کن"
        cancel-label="انصراف"
        variant="danger"
        @confirmed="deleteItem()"
    />

  Trigger: $dispatch('open-modal', 'delete-item')
--}}
@props([
    'name'          => null,
    'title'         => 'تأیید عملیات',
    'message'       => 'آیا از انجام این عملیات مطمئن هستید؟',
    'confirmLabel'  => 'تأیید',
    'cancelLabel'   => 'انصراف',
    'variant'       => 'danger',  /* danger | warning | primary */
    'icon'          => true,
])

@php
$btnVariants = ['danger' => 'danger', 'warning' => 'warning', 'primary' => 'primary'];
$btnVariant  = $btnVariants[$variant] ?? 'danger';
$iconPaths = [
    'danger'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>',
    'warning' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>',
    'primary' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 5.25h.008v.008H12v-.008z"/>',
];
$iconColor = ['danger' => 'text-[var(--ui-danger)]', 'warning' => 'text-[var(--ui-warning)]', 'primary' => 'text-[var(--ui-indigo)]'];
$modalId = $name ?? 'confirm-' . uniqid();
@endphp

<div
    x-data="{
        open: false,
        loading: false,
        confirm() {
            this.loading = true;
            this.$dispatch('confirmed');
            setTimeout(() => { this.open = false; this.loading = false; }, 300);
        }
    }"
    @open-modal.window="if ($event.detail === '{{ $modalId }}') open = true"
    @close-modal.window="if ($event.detail === '{{ $modalId }}') open = false"
    @keydown.escape.window="if (open) open = false"
    {{ $attributes }}
>
    {{-- Backdrop --}}
    <div x-show="open"
         x-transition:enter="transition duration-[var(--ui-duration-moderate)]"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-[var(--ui-duration-normal)]"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 bg-[var(--ui-modal-overlay-bg)]"
         style="z-index: var(--ui-z-modal); backdrop-filter: var(--ui-modal-backdrop-blur); -webkit-backdrop-filter: var(--ui-modal-backdrop-blur);"
    ></div>

    {{-- Dialog --}}
    <div x-show="open"
         x-transition:enter="transition ease-[var(--ui-ease-spring)] duration-[var(--ui-duration-moderate)]"
         x-transition:enter-start="opacity-0 scale-90"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-[var(--ui-ease-default)] duration-[var(--ui-duration-normal)]"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.stop
         role="alertdialog"
         aria-modal="true"
         aria-labelledby="{{ $modalId }}-title"
         aria-describedby="{{ $modalId }}-desc"
         class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md
                bg-[var(--ui-surface)] rounded-[var(--ui-radius-2xl)] shadow-[var(--ui-shadow-modal)] p-6"
         style="z-index: calc(var(--ui-z-modal) + 1);"
    >
        <div class="flex gap-4">
            @if($icon)
                <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center
                            {{ $variant === 'danger' ? 'bg-[var(--ui-danger-bg)]' : ($variant === 'warning' ? 'bg-[var(--ui-warning-bg)]' : 'bg-[var(--ui-indigo-light)]') }}">
                    <svg class="w-5 h-5 {{ $iconColor[$variant] ?? $iconColor['danger'] }}"
                         fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        {!! $iconPaths[$variant] ?? $iconPaths['danger'] !!}
                    </svg>
                </div>
            @endif

            <div class="flex-1">
                <h3 id="{{ $modalId }}-title" class="text-base font-semibold text-[var(--ui-text-primary)] mb-1">
                    {{ $title }}
                </h3>
                <p id="{{ $modalId }}-desc" class="text-sm text-[var(--ui-text-muted)]">
                    {{ $message }}
                </p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-6">
            <x-core-ui::button variant="secondary" @click="open = false" :disabled="false">
                {{ $cancelLabel }}
            </x-core-ui::button>
            <x-core-ui::button :variant="$btnVariant" @click="confirm()" :loading="false" x-bind:loading="loading">
                {{ $confirmLabel }}
            </x-core-ui::button>
        </div>
    </div>
</div>
