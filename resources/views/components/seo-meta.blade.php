{{--
    The single SEO head-tag renderer for this application.

    MetaEngine (SaaSPlatform\CoreUtils\SEO\MetaEngine, reached through
    App\Services\Seo\SeoService) is the one authority that decides WHAT these
    tags say; this component only decides how they are written out. A layout
    hands it an already-resolved MetaData and never renders a <title>,
    <meta name="description">, <link rel="canonical"> or <meta name="robots">
    by hand, so a page can never emit one of them twice.

    @param \SaaSPlatform\CoreUtils\SEO\MetaData|array<string,string>|null $metaData

    This component emits head TAGS ONLY. It must never emit <!DOCTYPE html>,
    <html>, <head>, <body> or a copy of its own $slot: a component that
    renders a document and its slot twice is a second, independent renderer of
    the same metadata and belongs in no layout at all.
--}}
@props([
    'metaData' => null,
    'array' => null,
])

@php
    $data = $metaData instanceof \SaaSPlatform\CoreUtils\SEO\MetaData
        ? $metaData->toArray()
        : (is_array($metaData) ? $metaData : (is_array($array) ? $array : []));

    $title = trim((string) ($data['title'] ?? '')) ?: config('app.name');
    $description = trim((string) ($data['description'] ?? ''));
    $canonical = trim((string) ($data['canonical'] ?? '')) ?: url()->current();
    $language = trim((string) ($data['language'] ?? '')) ?: 'fa';
    $direction = trim((string) ($data['direction'] ?? '')) ?: 'rtl';
    $robots = trim((string) ($data['robots'] ?? '')) ?: 'noindex, nofollow';
    $ogImage = trim((string) ($data['og_image'] ?? ''));
    $ogType = trim((string) ($data['og_type'] ?? '')) ?: 'website';
    $twitterCard = trim((string) ($data['twitter_card'] ?? '')) ?: 'summary';
@endphp

<title>{{ e($title) }}</title>
<meta name="description" content="{{ e($description) }}">
<link rel="canonical" href="{{ e($canonical) }}">
<meta name="robots" content="{{ e($robots) }}">

@if($ogImage)
    <meta property="og:image" content="{{ e($ogImage) }}">
@endif
<meta property="og:type" content="{{ e($ogType) }}">
<meta property="og:title" content="{{ e($title) }}">
<meta property="og:description" content="{{ e($description) }}">
<meta property="og:url" content="{{ e($canonical) }}">
<meta property="og:locale" content="{{ e(str_replace('-', '_', $language)) }}">

<meta name="twitter:card" content="{{ e($twitterCard) }}">
<meta name="twitter:title" content="{{ e($title) }}">
<meta name="twitter:description" content="{{ e($description) }}">
@if($ogImage)
    <meta name="twitter:image" content="{{ e($ogImage) }}">
@endif
