@props([
    'text' => '',
])
@php
    $text = (string) $text;
    $chars = $text !== '' ? preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) : [];
    $rotations = [-10, 8, -12, 9, -7, 11, -9, 7];
    $digitColors = ['#5b21b6', '#be123c', '#1e40af', '#047857', '#b45309', '#b91c1c'];
    $opColor = '#475569';
@endphp
<div
    class="persian-captcha-math"
    dir="ltr"
    role="img"
    aria-label="کپچای محاسباتی با اعداد فارسی"
>
    <div class="persian-captcha-math__noise" aria-hidden="true"></div>
    <div class="persian-captcha-math__inner">
        @foreach($chars as $i => $ch)
            @php
                $isDigit = preg_match('/^[۰-۹0-9]$/u', $ch);
                $isOp = in_array($ch, ['+', '-', '×', '÷', '=', '?', '؟', ' '], true);
                $r = $rotations[$i % count($rotations)];
                $y = ($i % 3 - 1) * 3;
                $c = $isDigit ? $digitColors[$i % count($digitColors)] : $opColor;
                $s = $isDigit ? ($i % 2 === 0 ? 1.06 : 0.94) : 1;
                $fontSize = $isOp && $ch !== ' ' ? '1.15rem' : '1.45rem';
            @endphp
            @if($ch === ' ')
                <span class="persian-captcha-math__sp">&nbsp;</span>
            @else
                <span
                    class="persian-captcha-math__glyph {{ $isDigit ? 'persian-captcha-math__glyph--digit' : 'persian-captcha-math__glyph--op' }}"
                    style="--pm-rot: {{ $r }}deg; --pm-y: {{ $y }}px; --pm-c: {{ $c }}; --pm-s: {{ $s }}; font-size: {{ $fontSize }};"
                >{{ $ch }}</span>
            @endif
        @endforeach
    </div>
</div>
<style>
.persian-captcha-math {
    position: relative;
    display: inline-flex;
    align-items: center;
    min-height: 3rem;
    padding: 0.45rem 0.85rem 0.55rem;
    border-radius: 0.75rem;
    background: linear-gradient(160deg, #f8fafc 0%, #e2e8f0 100%);
    border: 1px solid rgba(15, 23, 42, 0.1);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.9);
    user-select: none;
    overflow: hidden;
}
.persian-captcha-math__noise {
    position: absolute;
    inset: 0;
    opacity: 0.3;
    pointer-events: none;
    background: repeating-linear-gradient(
        -25deg,
        transparent,
        transparent 4px,
        rgba(59, 130, 246, 0.06) 4px,
        rgba(59, 130, 246, 0.06) 5px
    );
}
.persian-captcha-math__inner {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.08em;
}
.persian-captcha-math__glyph {
    display: inline-block;
    font-weight: 800;
    line-height: 1.2;
    font-family: Tahoma, sans-serif;
    color: var(--pm-c);
    transform: rotate(var(--pm-rot, 0deg)) translateY(var(--pm-y, 0)) scale(var(--pm-s, 1));
    text-shadow: 1px 1px 0 rgba(255,255,255,0.4);
}
.persian-captcha-math__glyph--op {
    font-weight: 700;
    opacity: 0.92;
}
</style>
