{{--
  x-core-ui::stats-strip — Canonical horizontal stats bar with 1-4 stat cards.
  Usage:
    <x-core-ui::stats-strip>
        <x-slot name="stats">
            @include('core-ui::components._stat-item', ['value'=>'42', 'label'=>'فرم‌ها', 'icon'=>'...', 'trend'=>'+12%'])
        </x-slot>
    </x-core-ui::stats-strip>
--}}

<div {{ $attributes->class(['grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6']) }}>
    {{ $slot }}
</div>
