<!doctype html>
@php
    $seoService = app(\App\Services\Seo\SeoService::class);
    $metaData = $seoService->resolveForTenantPublic();
    $metaArray = $metaData->toArray();
@endphp
<html lang="{{ $metaArray['language'] }}" dir="{{ $metaArray['direction'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-seo-meta :meta-data="$metaData" />

    {{-- Vite or fallback Tailwind --}}
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            vazir: ['Vazirmatn', 'Tahoma', 'sans-serif'],
                        },
                    },
                },
            };
        </script>
        <style>
            @include('partials.vazirmatn-font-faces-inline')
            body {
                font-family: Vazirmatn, Tahoma, sans-serif;
                direction: rtl;
                margin: 0;
                background: #f9fafb;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.625rem 1.25rem;
                border-radius: 0.5rem;
                font-weight: 500;
                border: none;
                cursor: pointer;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            }
            .btn-primary {
                background: #4f46e5;
                color: #fff;
            }
            .btn-primary:hover {
                background: #4338ca;
            }
            .form-input {
                width: 100%;
                padding: 0.75rem 1rem;
                border: 1px solid #d1d5db;
                border-radius: 0.75rem;
                text-align: right;
                font-size: 1rem;
                box-sizing: border-box;
            }
            .form-input:focus {
                outline: none;
                border-color: #818cf8;
                box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
            }
            .form-label {
                display: block;
                font-size: 0.875rem;
                font-weight: 500;
                color: #374151;
                margin-bottom: 0.375rem;
                text-align: right;
            }
        </style>
    @endif

    @stack('head')
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">

    @yield('breadcrumb')

    @yield('content')

    @stack('json-ld')

    @stack('scripts')
</body>
</html>