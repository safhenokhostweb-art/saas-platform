{{--
  x-core-ui::skeleton — Canonical loading skeleton.
  Props:
    lines  : int    — number of skeleton text lines (default: 3)
    avatar : bool   — show avatar circle placeholder
    image  : bool   — show image rectangle placeholder
    height : string — custom height for image placeholder (default: h-40)
    rounded: bool   — rounded corners on image placeholder
--}}
@props([
    'lines'   => 3,
    'avatar'  => false,
    'image'   => false,
    'height'  => 'h-40',
    'rounded' => true,
])

<div {{ $attributes->class(['animate-pulse']) }} aria-busy="true" aria-label="در حال بارگذاری...">
    @if($image)
        <div class="ui-skeleton {{ $height }} w-full mb-4 {{ $rounded ? 'rounded-[var(--ui-radius-xl)]' : '' }}"></div>
    @endif

    @if($avatar)
        <div class="flex items-center gap-3 mb-4">
            <div class="ui-skeleton w-10 h-10 rounded-full shrink-0"></div>
            <div class="flex-1 space-y-2">
                <div class="ui-skeleton h-3 w-1/2 rounded-full"></div>
                <div class="ui-skeleton h-2 w-1/3 rounded-full"></div>
            </div>
        </div>
    @endif

    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        <div class="space-y-3">
            @for($i = 0; $i < $lines; $i++)
                <div class="ui-skeleton h-3 rounded-full"
                     style="width: {{ $i === $lines - 1 ? '60%' : '100%' }}"></div>
            @endfor
        </div>
    @endif
</div>
