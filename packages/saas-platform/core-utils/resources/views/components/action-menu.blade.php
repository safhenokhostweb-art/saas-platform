{{--
  x-core-ui::action-menu — Three-dot action menu for table rows.
  Usage:
    <x-core-ui::action-menu>
      <x-core-ui::dropdown-item href="{{ route('admin.forms.edit', $form) }}">ویرایش</x-core-ui::dropdown-item>
      <x-core-ui::dropdown-item variant="danger" @click.prevent="confirmDelete({{ $form->id }})">حذف</x-core-ui::dropdown-item>
    </x-core-ui::action-menu>
--}}

<div class="relative inline-block" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    <button
        type="button"
        @click.stop="open = !open"
        class="p-1.5 rounded-lg text-[var(--ui-text-subtle)] hover:bg-slate-100 hover:text-[var(--ui-text-secondary)] transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ui-focus-ring-color)]"
        aria-label="اقدامات"
        aria-haspopup="true"
        :aria-expanded="open"
    >
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3z"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute left-0 top-full mt-1 w-44 z-[var(--ui-z-dropdown)] origin-top-left bg-[var(--ui-surface)] border border-[var(--ui-border)] rounded-[var(--ui-radius-xl)] shadow-[var(--ui-shadow-lg)] overflow-hidden py-1"
        style="display:none;"
        @click="open = false"
    >
        {{ $slot }}
    </div>
</div>
