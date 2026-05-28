<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت')</title>
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>tailwind.config = { theme: { extend: { fontFamily: { vazir: ['Vazirmatn', 'Tahoma', 'sans-serif'] } } } };</script>
        <style>
            @include('partials.vazirmatn-font-faces-inline')
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
            .sidebar-link{ display: flex; align-items: center; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; }
            form.mt-2 button{ display: flex; align-items: center; width: 100%; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; border: none; background: transparent; cursor: pointer; text-align: right; color: #fca5a5; }
            form.mt-2 button:hover{ background: rgba(220,38,38,0.2); color: #fecaca; }
            .admin-sidebar-scroll-area{ scrollbar-width: thin; scrollbar-color: rgba(148,163,184,0.45) transparent; }
            .admin-sidebar-scroll-area::-webkit-scrollbar{ width: 8px; }
            .admin-sidebar-scroll-area::-webkit-scrollbar-thumb{ background: rgba(148,163,184,0.35); border-radius: 8px; }
        </style>
    @endif
    @include('partials.platform-styles')
    @include('partials.platform-admin-sidebar-theme')
    @include('partials.dashboard-stat-tiles-css')
    <style>
        /* ─── Glass Sidebar ─── */
        #admin-sidebar {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-left: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 4px 0 24px rgba(15, 23, 42, 0.06);
        }
        #admin-sidebar[data-collapsed="true"] { width: 5rem; min-width: 5rem; }
        #admin-sidebar[data-collapsed="true"] .admin-sidebar-inner { width: 5rem; min-width: 5rem; }
        #admin-sidebar[data-collapsed="true"] .sidebar-text { display: none; }
        #admin-sidebar[data-collapsed="true"] .sidebar-group-label { display: none; }
        #admin-sidebar[data-collapsed="true"] .sidebar-group-caret { display: none; }

        /* ─── Sidebar Logo ─── */
        .admin-sidebar-inner .border-b {
            border-color: rgba(226, 232, 240, 0.5);
        }

        /* ─── Sidebar Links ─── */
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.7rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.75rem;
            color: #475569;
            transition: all 0.2s ease;
            margin-bottom: 1px;
        }
        .sidebar-link:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(168, 85, 247, 0.05));
            color: #6366f1;
            transform: translateX(-2px);
        }
        .sidebar-link.active {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(168, 85, 247, 0.08));
            color: #6366f1;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
        }
        .sidebar-link svg { transition: transform 0.2s ease; }
        .sidebar-link:hover svg { transform: scale(1.1); }

        /* ─── Sidebar Group Toggle ─── */
        .sidebar-group-toggle {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.6rem 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            background: transparent;
            border: none;
            cursor: pointer;
            border-radius: 0.75rem;
            transition: color 0.2s ease;
            text-align: right;
            gap: 0.5rem;
        }
        .sidebar-group-toggle:hover { color: #64748b; }

        /* ─── Group Items ─── */
        .sidebar-group-items {
            overflow: hidden;
            transition: max-height 0.35s ease, opacity 0.25s ease;
            max-height: 1000px;
            opacity: 1;
        }
        .sidebar-group-items.is-collapsed {
            max-height: 0;
            opacity: 0;
        }

        /* ─── Group Caret ─── */
        .sidebar-group-caret {
            width: 14px;
            height: 14px;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        .sidebar-group-caret.is-open { transform: rotate(180deg); }

        /* ─── Logout Button ─── */
        form.mt-2 button {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.7rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.75rem;
            border: none;
            background: transparent;
            cursor: pointer;
            text-align: right;
            color: #fca5a5;
            transition: all 0.2s ease;
        }
        form.mt-2 button:hover {
            background: rgba(239, 68, 68, 0.08);
            color: #f87171;
            transform: translateX(-2px);
        }

        /* ─── Scrollbar ─── */
        .admin-sidebar-scroll-area { scrollbar-width: thin; scrollbar-color: rgba(148,163,184,0.25) transparent; }
        .admin-sidebar-scroll-area::-webkit-scrollbar{ width: 5px; }
        .admin-sidebar-scroll-area::-webkit-scrollbar-thumb{ background: rgba(148,163,184,0.2); border-radius: 8px; }
        .admin-sidebar-scroll-area::-webkit-scrollbar-thumb:hover{ background: rgba(148,163,184,0.35); }
    </style>
    @stack('head')
</head>
<body class="min-h-screen bg-gray-50" style="background: var(--admin-bg);">

    <div class="flex h-screen overflow-hidden min-h-0" id="admin-layout-root">
        <!-- Sidebar: با دکمه تاگل در دسکتاپ جمع می‌شود (فقط آیکن‌ها). همبرگر فقط موبایل. -->
        <aside id="admin-sidebar" class="hidden md:flex md:flex-shrink-0 transition-[width] duration-300 ease-in-out w-64 min-h-0" data-collapsed="false">
            <div class="admin-sidebar-inner flex flex-col w-64 min-w-0 flex-1 min-h-0">
                <div class="admin-sidebar-scroll-area flex flex-col flex-1 min-h-0 overflow-y-auto overflow-x-hidden">
                    <!-- Logo / عنوان -->
                    <div class="flex items-center flex-shrink-0 px-4 py-5 border-b min-h-[4rem]">
                        <svg class="w-6 h-6 shrink-0" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="max-width:24px;max-height:24px">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span class="sidebar-text mr-3 text-xl font-bold whitespace-nowrap overflow-hidden">{{ auth()->user()->is_admin ? 'پنل مدیریت' : 'پنل من' }}</span>
                    </div>

                    <!-- Navigation: ادمین = منوی ادمین، مشتری = منوی مشتری -->
                    <nav class="flex-1 px-4 py-6 min-h-0">
                        @if(auth()->user()->is_admin)
                            @foreach(\App\Helpers\AdminMenu::getGroups() as $groupLabel => $keys)
                                @php $visibleKeys = array_values(array_filter($keys, fn ($k) => \App\Helpers\AdminMenu::isMenuItemVisible($k))); @endphp
                                @if(count($visibleKeys) > 0)
                                    <div class="admin-sidebar-group" data-group-index="{{ $loop->index }}">
                                        <button type="button" class="sidebar-group-toggle" data-group-toggle="{{ $loop->index }}" aria-expanded="false">
                                            <span class="sidebar-group-label sidebar-text">{{ $groupLabel }}</span>
                                            <svg class="sidebar-group-caret" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                        <div class="admin-sidebar-item-list sidebar-group-items is-collapsed" data-group-items="{{ $loop->index }}">
                                            @foreach($visibleKeys as $key)
                                                <a href="{{ route(\App\Helpers\AdminMenu::getRoute($key)) }}" class="sidebar-link {{ \App\Helpers\AdminMenu::isActive($key) ? 'active' : '' }}">
                                                    @include('admin.partials.sidebar-icon', ['key' => $key])
                                                    <span class="sidebar-text">{{ \App\Helpers\ModuleLabels::getTitle($key, null) }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            @foreach(\App\Helpers\CustomerMenu::getGroups() as $groupLabel => $keys)
                                @php $visibleKeys = array_values(array_filter($keys, fn ($k) => \App\Helpers\CustomerMenu::isVisible($k))); @endphp
                                @if(count($visibleKeys) > 0)
                                    <div class="admin-sidebar-group" data-group-index="{{ $loop->index }}">
                                        <button type="button" class="sidebar-group-toggle" data-group-toggle="{{ $loop->index }}" aria-expanded="false">
                                            <span class="sidebar-group-label sidebar-text">{{ $groupLabel }}</span>
                                            <svg class="sidebar-group-caret" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                        <div class="admin-sidebar-item-list sidebar-group-items is-collapsed" data-group-items="{{ $loop->index }}">
                                            @foreach($visibleKeys as $key)
                                                <a href="{{ route(\App\Helpers\CustomerMenu::getRoute($key)) }}" class="sidebar-link {{ \App\Helpers\CustomerMenu::isActive($key) ? 'active' : '' }}">
                                                    @include('admin.partials.customer-sidebar-icon', ['key' => $key])
                                                    <span class="sidebar-text">{{ \App\Helpers\ModuleLabels::getTitle($key) }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        <div class="pt-4 mt-4 border-t border-slate-600/40">
                            @if(auth()->user()->is_admin)
                            <a href="{{ route('dashboard') }}" class="sidebar-link">
                                <svg class="w-4 h-4 ml-2 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                <span class="sidebar-text">بازگشت به داشبورد</span>
                            </a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                                @csrf
                                <button type="submit" class="w-full flex items-center px-4 py-3 text-sm font-medium rounded-lg text-red-300 hover:bg-red-950/40 hover:text-red-100 border-0 bg-transparent cursor-pointer text-right">
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
                        <!-- دکمه منوی موبایل (فقط در صفحات کوچک) — Alpine-driven -->
                        <div class="md:hidden relative" x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false" @click.outside="mobileMenuOpen = false">
                            <button type="button"
                                    @click="mobileMenuOpen = !mobileMenuOpen"
                                    class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                                    aria-label="منوی ناوبری"
                                    :aria-expanded="mobileMenuOpen">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </button>
                            <div x-show="mobileMenuOpen"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                 class="absolute right-0 top-full mt-2 w-64 max-h-[80vh] overflow-y-auto bg-white rounded-xl shadow-lg border border-gray-200 py-2 z-50"
                                 role="menu" aria-label="منوی موبایل">
                                @if(auth()->user()->is_admin)
                                    @foreach(\App\Helpers\AdminMenu::getGroups() as $groupLabel => $keys)
                                        @php $hasVisible = false; foreach ($keys as $k) { if (\App\Helpers\AdminMenu::isMenuItemVisible($k)) { $hasVisible = true; break; } } @endphp
                                        @if($hasVisible)
                                            <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-100">{{ $groupLabel }}</div>
                                            @foreach($keys as $key)
                                                @if(\App\Helpers\AdminMenu::isMenuItemVisible($key))
                                                    <a href="{{ route(\App\Helpers\AdminMenu::getRoute($key)) }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50" role="menuitem">{{ \App\Helpers\ModuleLabels::getTitle($key) }}</a>
                                                @endif
                                            @endforeach
                                        @endif
                                    @endforeach
                                    <hr class="my-2">
                                    <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50" role="menuitem">بازگشت به داشبورد</a>
                                @else
                                    @foreach(\App\Helpers\CustomerMenu::getGroups() as $groupLabel => $keys)
                                        <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-100">{{ $groupLabel }}</div>
                                        @foreach($keys as $key)
                                            @if(\App\Helpers\CustomerMenu::isVisible($key))
                                                <a href="{{ route(\App\Helpers\CustomerMenu::getRoute($key)) }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50" role="menuitem">{{ \App\Helpers\ModuleLabels::getTitle($key) }}</a>
                                            @endif
                                        @endforeach
                                    @endforeach
                                @endif
                            </div>
                        </div>
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
        (function() {
            var toggles = document.querySelectorAll('#admin-sidebar [data-group-toggle]');
            toggles.forEach(function(btn) {
                var idx = btn.getAttribute('data-group-toggle');
                var group = btn.closest('.admin-sidebar-group');
                if (!group) return;
                var items = group.querySelector('[data-group-items]');
                var caret = group.querySelector('.sidebar-group-caret');
                if (!items || !caret) return;
                var storageKey = 'saas-admin-group-open-' + idx;
                var hasActive = !!group.querySelector('.sidebar-link.active');
                var open = hasActive || localStorage.getItem(storageKey) === 'true';
                function setOpen(isOpen) {
                    if (isOpen) {
                        items.classList.remove('is-collapsed');
                        btn.setAttribute('aria-expanded', 'true');
                        caret.classList.add('is-open');
                    } else {
                        items.classList.add('is-collapsed');
                        btn.setAttribute('aria-expanded', 'false');
                        caret.classList.remove('is-open');
                    }
                }
                setOpen(open);
                if (hasActive) {
                    localStorage.setItem(storageKey, 'true');
                }
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    open = items.classList.contains('is-collapsed');
                    localStorage.setItem(storageKey, open ? 'true' : 'false');
                    setOpen(open);
                });
            });
        })();
    </script>
</body>
</html>
