@props([
    'code' => '',
    'id' => null,
])
@php
    $code = (string) $code;
    $chars = $code !== '' ? preg_split('//u', $code, -1, PREG_SPLIT_NO_EMPTY) : [];
    $boxId = $id ?? 'pcap-' . hash('crc32b', $code !== '' ? $code : 'empty');
    $rotations = [-16, 11, -9, 14, -12, 8, -13, 10, -7, 15];
    $colors = ['#6d28d9', '#be185d', '#1d4ed8', '#047857', '#b45309', '#b91c1c', '#0f766e', '#4338ca', '#a21caf', '#c2410c'];
    $yOffsets = [-4, 5, -3, 6, -5, 3, 4, -6, 2, -2];
    $scales = [1.05, 0.95, 1.08, 0.92, 1.02, 0.97, 1.06, 0.94];
@endphp
<div
    id="{{ $boxId }}"
    class="persian-captcha-challenge"
    dir="ltr"
    role="img"
    aria-label="کد امنیتی با ارقام فارسی؛ ترتیب از چپ به راست"
>
    <div class="persian-captcha-challenge__noise" aria-hidden="true"></div>
    <div class="persian-captcha-challenge__inner">
        @foreach($chars as $i => $ch)
            @php
                $r = $rotations[$i % count($rotations)];
                $c = $colors[$i % count($colors)];
                $y = $yOffsets[$i % count($yOffsets)];
                $s = $scales[$i % count($scales)];
            @endphp
            <span
                class="persian-captcha-challenge__digit"
                style="--pcap-rot: {{ $r }}deg; --pcap-y: {{ $y }}px; --pcap-s: {{ $s }}; --pcap-c: {{ $c }};"
            >{{ $ch }}</span>
        @endforeach
    </div>
</div>
<style>
.persian-captcha-challenge {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 3.25rem;
    padding: 0.5rem 1rem 0.65rem;
    border-radius: 0.75rem;
    background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 45%, #cbd5e1 100%);
    border: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.85), 0 2px 8px rgba(15, 23, 42, 0.08);
    user-select: none;
    -webkit-user-select: none;
    overflow: hidden;
}
.persian-captcha-challenge__noise {
    position: absolute;
    inset: 0;
    opacity: 0.35;
    pointer-events: none;
    background-image:
        repeating-linear-gradient(105deg, transparent, transparent 3px, rgba(99,102,241,0.07) 3px, rgba(99,102,241,0.07) 4px),
        repeating-linear-gradient(-18deg, transparent, transparent 5px, rgba(244,63,94,0.06) 5px, rgba(244,63,94,0.06) 6px);
}
.persian-captcha-challenge__inner {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: flex-end;
    gap: 0.12em;
}
.persian-captcha-challenge__digit {
    display: inline-block;
    font-size: clamp(1.5rem, 4vw, 1.85rem);
    font-weight: 800;
    line-height: 1;
    font-family: Tahoma, "Segoe UI", sans-serif;
    color: var(--pcap-c, #1e293b);
    transform: rotate(var(--pcap-rot, 0deg)) translateY(var(--pcap-y, 0)) scale(var(--pcap-s, 1));
    text-shadow:
        1px 1px 0 rgba(255,255,255,0.45),
        -1px -1px 0 rgba(0,0,0,0.12),
        0 0 1px rgba(0,0,0,0.15);
    letter-spacing: 0;
}
</style>
