<!DOCTYPE html>
@php
    // PREVIEW_EDITOR context: the Canvas editor is a preview surface and is
    // never indexable. The composed title is preserved as a MetaEngine
    // override rather than hand-written into <title>.
    $metaData = $metaData ?? app(\App\Services\Seo\SeoService::class)->resolveForPreview(
        null,
        ['title' => trim(($__env->yieldContent('title') ?: 'قالب‌ساز بصری') . ' — ' . config('app.name'))]
    );
@endphp
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-seo-meta :meta-data="$metaData" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; margin: 0; padding: 0; overflow: hidden; height: 100vh; background: #f1f5f9; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        [x-cloak] { display: none !important; }
    </style>
    @stack('head')
</head>
<body>
    @yield('content')
    @stack('scripts')
</body>
</html>
