@props([
    'name' => 'persian_date',
    'value' => '',
    'placeholder' => '۱۴۰۳/۰۷/۱۵',
    'id' => null,
])
@php
    $id = $id ?? 'persian-date-' . preg_replace('/[^a-z0-9]/', '-', $name);
@endphp
<input type="text"
       name="{{ $name }}"
       id="{{ $id }}"
       value="{{ $value }}"
       placeholder="{{ $placeholder }}"
       autocomplete="off"
       dir="ltr"
       class="form-control persian-date-input"
       {{ $attributes->except(['class'])->merge([]) }}>
