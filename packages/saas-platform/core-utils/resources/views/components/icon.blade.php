{{--
  x-core-ui::icon — SaaS Platform Duotone Icon Component
  Unique icon set: background fill (opacity 0.12) + 2px stroke.

  Props:
    name     : string  — icon name (e.g. "home", "dashboard", "book")
    size     : string  — Tailwind size suffix: "4" → w-4 h-4 (default: null, uses class)
    fallback : bool    — show a generic square if icon not found (default: false)

  Usage:
    <x-core-ui::icon name="home" class="w-5 h-5 text-blue-600" />
    <x-core-ui::icon name="dashboard" size="6" class="text-indigo-500" />
    <x-core-ui::icon name="graduation" size="8" />
--}}
@props([
    'name'     => '',
    'size'     => null,
    'fallback' => false,
])

@php
    use SaaSPlatform\CoreUtils\Icons\IconRegistry;

    $svg      = IconRegistry::get($name);
    $sizeAttr = $size ? "w-{$size} h-{$size}" : '';
    $classes  = trim('inline-block shrink-0 ' . $sizeAttr);
@endphp

@if($svg)
<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
    focusable="false"
    {{ $attributes->merge(['class' => $classes]) }}
>{!! $svg !!}</svg>
@elseif($fallback)
<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
    {{ $attributes->merge(['class' => $classes]) }}
><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="3" width="18" height="18" rx="3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M3 3 21 21"/></svg>
@endif
