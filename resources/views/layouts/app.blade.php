<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', \App\Helpers\ResellerBranding::platformName())</title>
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>tailwind.config = { theme: { extend: { fontFamily: { vazir: ['Vazirmatn', 'Tahoma', 'sans-serif'] } } } };</script>
        <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            body{ font-family: Vazirmatn, Tahoma, sans-serif; direction: rtl; margin: 0; background: #f9fafb; }
            .btn{ display: inline-flex; align-items: center; justify-content: center; padding: 0.625rem 1.25rem; border-radius: 0.5rem; font-weight: 500; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
            .btn-primary{ background: #4f46e5; color: #fff; }
            .btn-primary:hover{ background: #4338ca; }
            .btn.w-full{ width: 100%; }
            .form-control{ width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; text-align: right; font-size: 1rem; box-sizing: border-box; }
            .form-control:focus{ outline: none; border-color: #818cf8; box-shadow: 0 0 0 2px rgba(79,70,229,0.2); }
            .form-label{ display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.375rem; text-align: right; }
            .card{ background: #fff; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; box-sizing: border-box; }
            .card.p-6{ padding: 1.5rem; }
            .alert{ padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; border: 1px solid; }
            .alert-success{ background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
            .alert-danger{ background: #fef2f2; border-color: #fecaca; color: #991b1b; }
            .flex{ display: flex; }
            .justify-center{ justify-content: center; }
            .w-full{ width: 100%; }
            .max-w-md{ max-width: 28rem; }
            .space-y-4 > * + *{ margin-top: 1rem; }
            .mb-6{ margin-bottom: 1.5rem; }
            .mt-4{ margin-top: 1rem; }
            .text-center{ text-align: center; }
            .text-xl{ font-size: 1.25rem; }
            .px-4{ padding-right: 1rem; padding-left: 1rem; }
            .navbar{ background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-bottom: 1px solid #e5e7eb; }
            .navbar .container{ width: 100%; max-width: 1280px; margin: 0 auto; padding: 0 1rem; box-sizing: border-box; }
            .navbar .container > div{ display: flex !important; align-items: center !important; justify-content: space-between !important; min-height: 4rem; flex-wrap: wrap; }
            .navbar-brand svg{ width: 24px !important; height: 24px !important; max-width: 24px !important; max-height: 24px !important; display: inline-block; flex-shrink: 0; }
            .navbar-link svg{ width: 16px !important; height: 16px !important; max-width: 16px !important; max-height: 16px !important; display: inline-block; flex-shrink: 0; }
            .navbar-brand{ display: inline-flex !important; align-items: center; font-weight: 700; color: #4f46e5; text-decoration: none; }
            .navbar-link{ display: inline-flex; align-items: center; color: #374151; text-decoration: none; font-weight: 500; padding: 0.5rem; }
            main.container{ max-width: 1280px; margin: 0 auto; padding: 2rem 1rem; box-sizing: border-box; }
        </style>
    @endif
    @include('partials.platform-styles')
    @yield('head')
    @stack('head')
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">

@include('partials.navbar')

@php $pageMenuGlobal = \App\Models\Setting::get('page_menu_position', 'top'); @endphp
@if(in_array($pageMenuGlobal, ['left', 'right'], true))
<div class="flex container mx-auto px-4 py-8 gap-6">
    @if($pageMenuGlobal === 'right')
        <aside class="w-52 shrink-0 border-l border-gray-200 pl-6">
            @include('partials.pages-menu', ['position' => 'right'])
        </aside>
    @endif
    <main class="flex-1 min-w-0">
@else
<main class="container mx-auto px-4 py-8">
@endif
    @if(session('success'))
        <div class="alert alert-success mb-6" role="alert">
            <div class="flex items-center">
                <svg class="w-4 h-4 ml-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger mb-6" role="alert">
            <div class="flex items-center">
                <svg class="w-4 h-4 ml-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                {{ session('error') }}
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-6" role="alert">
            <div class="font-bold mb-2">خطاهای زیر رخ داد:</div>
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
@if(in_array($pageMenuGlobal, ['left', 'right'], true))
    </main>
    @if($pageMenuGlobal === 'left')
        <aside class="w-52 shrink-0 border-r border-gray-200 pr-6">
            @include('partials.pages-menu', ['position' => 'left'])
        </aside>
    @endif
</div>
@else
</main>
@endif

<footer class="bg-white border-t border-gray-200 mt-12">
    <div class="container mx-auto px-4 py-6">
        @php $footerPageMenu = \App\Models\Setting::get('page_menu_position', 'top'); @endphp
        @if($footerPageMenu === 'bottom')
            <div class="flex flex-wrap justify-center gap-4 mb-4">
                @include('partials.pages-menu', ['position' => 'bottom'])
            </div>
        @endif
        <div class="text-center text-gray-600 text-sm">
            <p>&copy; {{ \App\Helpers\Jalali::formatFa(now(), 'Y') }} {{ \App\Helpers\PlatformSettings::platformName() }}. تمامی حقوق محفوظ است.</p>
        </div>
    </div>
</footer>

@stack('scripts')
@yield('scripts')

</body>
</html>
