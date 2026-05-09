<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت')</title>
    {{-- فونت آفلاین Vazirmatn (preload + crossorigin برای کش بهتر) --}}
    <link rel="preload" as="font" href="{{ asset('assets/fonts/vazirmatn/Vazirmatn-Regular.woff2') }}" type="font/woff2" crossorigin>
    <link rel="preload" as="font" href="{{ asset('assets/fonts/vazirmatn/Vazirmatn-Bold.woff2') }}" type="font/woff2" crossorigin>
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>@include('partials.vazirmatn-font-faces-inline')</style>
    @else
        <style>
            @include('partials.vazirmatn-font-faces-inline')
            /* بدون build — کلاس‌های ضروری برای آکاردئون و چیدمان */
            .hidden { display: none !important; }
            .flex { display: flex; }
            .items-center { align-items: center; }
            .justify-between { justify-content: space-between; }
            .w-full { width: 100%; }
            .min-h-screen { min-height: 100vh; }
            :root{
                --admin-bg:#f1f5f9;
                --admin-surface:#ffffff;
                --admin-surface-soft:#f8fafc;
                --admin-primary:#2563eb;
                --admin-primary-hover:#1d4ed8;
                --admin-border:#e2e8f0;
                --admin-shadow:0 8px 24px rgba(15, 23, 42, 0.06);
                --admin-radius:14px;
            }
            body{ font-family: Vazirmatn, Tahoma, sans-serif; direction: rtl; }
            .btn{ display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.625rem; font-weight: 500; }
            .btn-primary{ background: var(--admin-primary); color: #fff; }
            .btn-secondary,.btn-outline-secondary{ background: transparent; color: #374151; border: 2px solid #9ca3af; }
            .btn-outline-primary{ background: transparent; color: var(--admin-primary); border: 2px solid var(--admin-primary); }
            .btn-outline-primary:hover{ background: #eff6ff; }
            .form-control,.form-select{ width: 100%; padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; text-align: right; }
            .form-label{ display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem; text-align: right; }
            .form-check-input{ width: 1rem; height: 1rem; accent-color: #2563eb; }
            .card{ background: var(--admin-surface); border-radius: var(--admin-radius); box-shadow: var(--admin-shadow); border: 1px solid var(--admin-border); }
            .card-body{ padding: 1rem 1.5rem; }
            .alert{ padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; border: 1px solid; }
            .alert-success{ background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
            .alert-danger{ background: #fef2f2; border-color: #fecaca; color: #991b1b; }
            .sidebar-link{ display: flex; align-items: center; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; color: #374151; border-radius: 0.5rem; }
            .sidebar-link:hover{ background: #eff6ff; color: #2563eb; }
            .sidebar-link.active{ background: #eff6ff; color: #2563eb; }
            form.mt-2 button{ display: flex; align-items: center; width: 100%; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; border: none; background: transparent; cursor: pointer; text-align: right; color: #dc2626; }
            form.mt-2 button:hover{ background: #fef2f2; color: #b91c1c; }
        </style>
    @endif
    @include('partials.platform-styles')
    <style>
        #admin-sidebar[data-collapsed="true"] { width: 5rem; min-width: 5rem; }
        #admin-sidebar[data-collapsed="true"] .admin-sidebar-inner { width: 5rem; min-width: 5rem; }
        #admin-sidebar[data-collapsed="true"] .sidebar-text { display: none; }
        #admin-sidebar[data-collapsed="true"] [data-admin-group-caret] { display: none; }
        /* یوتیلیتی‌های مورد نیاز برای آکاردئون منو (در صورت عدم وجود در build تیلویند) */
        [data-admin-group-caret] { transition: transform .2s ease; }
        [data-admin-group-caret].rotate-180 { transform: rotate(180deg); }
        /* اسکرول داخلی برای زیرمنوهای طولانی (مثل «ماژول‌های پلتفرم») */
        [data-admin-group-items]:not(.hidden) {
            max-height: 50vh;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(37, 99, 235, 0.35) transparent;
            padding-right: 2px;
        }
        [data-admin-group-items]::-webkit-scrollbar { width: 6px; }
        [data-admin-group-items]::-webkit-scrollbar-track { background: transparent; }
        [data-admin-group-items]::-webkit-scrollbar-thumb { background: rgba(37, 99, 235, 0.25); border-radius: 6px; }
        [data-admin-group-items]::-webkit-scrollbar-thumb:hover { background: rgba(37, 99, 235, 0.45); }
        /* در حالت سایدبار جمع‌شده، اسکرول داخلی نخواهیم */
        #admin-sidebar[data-collapsed="true"] [data-admin-group-items]:not(.hidden) {
            max-height: none;
            overflow: visible;
        }
    </style>
    @stack('head')
</head>
<body class="min-h-screen bg-gray-50" style="background: var(--admin-bg);">

    <div class="flex h-screen overflow-hidden" id="admin-layout-root">
        <!-- Sidebar: با دکمه تاگل در دسکتاپ جمع می‌شود (فقط آیکن‌ها). همبرگر فقط موبایل. -->
        <aside id="admin-sidebar" class="hidden md:flex md:flex-shrink-0 transition-[width] duration-300 ease-in-out w-64" data-collapsed="false">
            <div class="admin-sidebar-inner flex flex-col w-64 min-w-0 flex-1">
                <div class="flex flex-col flex-grow bg-white border-l border-gray-200 overflow-y-auto overflow-x-hidden">
                    <!-- Logo / عنوان -->
                    <div class="flex items-center flex-shrink-0 px-4 py-5 border-b border-gray-200 min-h-[4rem]">
                        <svg class="w-6 h-6 text-blue-600 shrink-0" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="max-width:24px;max-height:24px">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span class="sidebar-text mr-3 text-xl font-bold text-gray-900 whitespace-nowrap overflow-hidden">{{ auth()->user()->is_admin ? 'پنل مدیریت' : 'پنل من' }}</span>
                    </div>

                    <!-- Navigation: ادمین = منوی ادمین، مشتری = منوی مشتری -->
                    <nav class="flex-1 px-4 py-6 overflow-y-auto">
                        @if(auth()->user()->is_admin)
                            @foreach(\App\Helpers\AdminMenu::getGroups() as $groupLabel => $keys)
                                @php $hasVisible = false; foreach ($keys as $k) { if (\App\Helpers\AdminMenu::isMenuItemVisible($k)) { $hasVisible = true; break; } } @endphp
                                @if($hasVisible)
                                    <div class="mb-4">
                                        <button
                                            type="button"
                                            class="w-full flex items-center justify-between px-3 py-1.5 text-xs font-semibold uppercase tracking-wider border-b border-gray-100 text-gray-500 hover:text-blue-600 hover:bg-blue-50/60 transition-colors"
                                            data-admin-group-toggle="{{ $loop->index }}"
                                            aria-expanded="false"
                                        >
                                            <span class="sidebar-text">{{ $groupLabel }}</span>
                                            <svg
                                                data-admin-group-caret
                                                class="w-4 h-4 transition-transform text-gray-500"
                                                viewBox="0 0 20 20"
                                                fill="none"
                                                aria-hidden="true"
                                            >
                                                <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                        <div class="mt-1 space-y-0.5 hidden" data-admin-group-items="{{ $loop->index }}">
                                            @foreach($keys as $key)
                                                @if(\App\Helpers\AdminMenu::isMenuItemVisible($key))
                                                    <a href="{{ route(\App\Helpers\AdminMenu::getRoute($key)) }}" class="sidebar-link {{ \App\Helpers\AdminMenu::isActive($key) ? 'active' : '' }}">
                                                        @include('admin.partials.sidebar-icon', ['key' => $key])
                                                        <span class="sidebar-text">{{ \App\Helpers\ModuleLabels::getTitle($key, null) }}</span>
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            @foreach(\App\Helpers\CustomerMenu::getGroups() as $groupLabel => $keys)
                                <div class="mb-4">
                                    <button
                                        type="button"
                                        class="w-full flex items-center justify-between px-3 py-1.5 text-xs font-semibold uppercase tracking-wider border-b border-gray-100 text-gray-500 hover:text-blue-600 hover:bg-blue-50/60 transition-colors"
                                        data-admin-group-toggle="{{ $loop->index }}"
                                        aria-expanded="false"
                                    >
                                        <span class="sidebar-text">{{ $groupLabel }}</span>
                                        <svg
                                            data-admin-group-caret
                                            class="w-4 h-4 transition-transform text-gray-500"
                                            viewBox="0 0 20 20"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                    <div class="mt-1 space-y-0.5 hidden" data-admin-group-items="{{ $loop->index }}">
                                        @foreach($keys as $key)
                                            @if(\App\Helpers\CustomerMenu::isVisible($key))
                                                <a href="{{ route(\App\Helpers\CustomerMenu::getRoute($key)) }}" class="sidebar-link {{ \App\Helpers\CustomerMenu::isActive($key) ? 'active' : '' }}">
                                                    @include('admin.partials.customer-sidebar-icon', ['key' => $key])
                                                    <span class="sidebar-text">{{ \App\Helpers\ModuleLabels::getTitle($key) }}</span>
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        <div class="pt-4 mt-4 border-t border-gray-200">
                            @if(auth()->user()->is_admin)
                            <a href="{{ route('dashboard') }}" class="sidebar-link">
                                <svg class="w-4 h-4 ml-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                <span class="sidebar-text">بازگشت به داشبورد</span>
                            </a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                                @csrf
                                <button type="submit" class="w-full flex items-center px-4 py-3 text-sm font-medium rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 border-0 bg-transparent cursor-pointer text-right">
                                    <svg class="w-4 h-4 ml-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    <span class="sidebar-text">خروج</span>
                                </button>
                            </form>
                        </div>
                    </nav>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Bar -->
            <header class="bg-white border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <!-- دکمه جمع کردن منوی کناری (فقط دسکتاپ؛ همبرگر فقط موبایل) -->
                        <button type="button" id="admin-sidebar-toggle" class="hidden md:inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors" aria-label="جمع کردن منو">
                            <svg id="admin-sidebar-toggle-icon-expand" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7M18 19l-7-7 7-7"/></svg>
                            <svg id="admin-sidebar-toggle-icon-collapse" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                        </button>
                        <!-- دکمه منوی موبایل (فقط در صفحات کوچک) -->
                        <details class="md:hidden relative group">
                            <summary class="list-none p-2 rounded-lg text-gray-600 hover:bg-gray-100 cursor-pointer" aria-label="منو">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </summary>
                            <div class="absolute right-0 top-full mt-2 w-64 max-h-[80vh] overflow-y-auto bg-white rounded-xl shadow-lg border border-gray-200 py-2 z-50">
                                @if(auth()->user()->is_admin)
                                    @foreach(\App\Helpers\AdminMenu::getGroups() as $groupLabel => $keys)
                                        @php $hasVisible = false; foreach ($keys as $k) { if (\App\Helpers\AdminMenu::isMenuItemVisible($k)) { $hasVisible = true; break; } } @endphp
                                        @if($hasVisible)
                                            <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-100">{{ $groupLabel }}</div>
                                            @foreach($keys as $key)
                                                @if(\App\Helpers\AdminMenu::isMenuItemVisible($key))
                                                    <a href="{{ route(\App\Helpers\AdminMenu::getRoute($key)) }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">{{ \App\Helpers\ModuleLabels::getTitle($key) }}</a>
                                                @endif
                                            @endforeach
                                        @endif
                                    @endforeach
                                    <hr class="my-2">
                                    <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">بازگشت به داشبورد</a>
                                @else
                                    @foreach(\App\Helpers\CustomerMenu::getGroups() as $groupLabel => $keys)
                                        <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-100">{{ $groupLabel }}</div>
                                        @foreach($keys as $key)
                                            @if(\App\Helpers\CustomerMenu::isVisible($key))
                                                <a href="{{ route(\App\Helpers\CustomerMenu::getRoute($key)) }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">{{ \App\Helpers\ModuleLabels::getTitle($key) }}</a>
                                            @endif
                                        @endforeach
                                    @endforeach
                                @endif
                            </div>
                        </details>
                        <h1 class="text-2xl font-bold text-gray-900">@yield('header', 'پنل مدیریت')</h1>
                    </div>
                    <div class="flex items-center space-x-4 space-x-reverse">
                        <span class="text-sm text-gray-600">{{ auth()->user()->mobile }}</span>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
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
            </main>
        </div>

    </div>

    @include('partials.guide-widget')
    @stack('scripts')
    @yield('scripts')
    <script>
        (function() {
            var sidebar = document.getElementById('admin-sidebar');
            var toggle = document.getElementById('admin-sidebar-toggle');
            var iconExpand = document.getElementById('admin-sidebar-toggle-icon-expand');
            var iconCollapse = document.getElementById('admin-sidebar-toggle-icon-collapse');
            var key = 'admin-sidebar-collapsed';
            if (sidebar && toggle) {
                var collapsed = localStorage.getItem(key) === 'true';
                function apply() {
                    sidebar.setAttribute('data-collapsed', collapsed ? 'true' : 'false');
                    if (iconExpand) iconExpand.classList.toggle('hidden', !collapsed);
                    if (iconCollapse) iconCollapse.classList.toggle('hidden', collapsed);
                }
                apply();
                toggle.addEventListener('click', function() {
                    collapsed = !collapsed;
                    localStorage.setItem(key, collapsed ? 'true' : 'false');
                    apply();
                });
            }
        })();
        // Sidebar menu groups accordion (collapse/expand)
        (function(){
            var toggles = document.querySelectorAll('[data-admin-group-toggle]');
            toggles.forEach(function(btn){
                var idx = btn.getAttribute('data-admin-group-toggle');
                var caret = btn.querySelector('[data-admin-group-caret]');
                var items = document.querySelector('[data-admin-group-items="' + idx + '"]');
                if (!items || !caret) return;

                var storageKey = 'admin-group-open-' + idx;
                var open = localStorage.getItem(storageKey) === 'true';

                function apply(isOpen){
                    if (isOpen){
                        items.classList.remove('hidden');
                        btn.setAttribute('aria-expanded', 'true');
                        caret.classList.add('rotate-180');
                        caret.classList.remove('text-gray-500');
                        caret.classList.add('text-blue-600');
                        btn.classList.add('text-blue-600');
                        btn.classList.remove('text-gray-500');
                    } else {
                        items.classList.add('hidden');
                        btn.setAttribute('aria-expanded', 'false');
                        caret.classList.remove('rotate-180');
                        caret.classList.add('text-gray-500');
                        caret.classList.remove('text-blue-600');
                        btn.classList.remove('text-blue-600');
                        btn.classList.add('text-gray-500');
                    }
                }

                apply(open);

                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    open = items.classList.contains('hidden');
                    localStorage.setItem(storageKey, open ? 'true' : 'false');
                    apply(open);
                });
            });
        })();
    </script>
</body>
</html>
