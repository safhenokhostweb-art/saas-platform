{{--
  x-core-ui::page-header — Canonical page header with title, subtitle, and action slot.
  Props:
    title    : string — page title
    subtitle : string — optional subtitle below title
    back     : string — optional back URL
    backLabel: string — back button label (default: بازگشت)
--}}
@props([
    'title'     => '',
    'subtitle'  => null,
    'back'      => null,
    'backLabel' => 'بازگشت',
])

<div {{ $attributes->class(['mb-6 sm:mb-8']) }}>
    {{-- Breadcrumb / Back --}}
    @if($back)
        <a href="{{ $back }}"
           class="inline-flex items-center gap-1.5 text-sm text-[var(--ui-text-muted)]
                  hover:text-[var(--ui-text-primary)] mb-3 group
                  transition-colors duration-[var(--ui-duration-fast)]">
            <svg class="w-4 h-4 transition-transform duration-[var(--ui-duration-fast)] group-hover:-translate-x-0.5"
                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
            {{ $backLabel }}
        </a>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-[var(--ui-text-primary)] truncate leading-tight">
                {{ $title }}
            </h1>
            @if($subtitle)
                <p class="mt-1 text-sm text-[var(--ui-text-muted)]">{{ $subtitle }}</p>
            @endif
        </div>

        @if(isset($actions))
            <div class="flex items-center gap-2 shrink-0">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
