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
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <style>
            @include('partials.vazirmatn-font-faces-inline')
            :root{
                --admin-bg:var(--ui-bg,#f1f5f9);
                --admin-surface:var(--ui-surface,#ffffff);
                --admin-surface-soft:var(--ui-surface-soft,#f8fafc);
                --admin-primary:var(--ui-indigo,#4f46e5);
                --admin-primary-hover:var(--ui-indigo-hover,#4338ca);
                --admin-border:var(--ui-border,#e2e8f0);
                --admin-shadow:var(--ui-shadow-card,0 8px 24px rgba(15,23,42,0.06));
                --admin-radius:var(--ui-radius-xl,14px);
            }
            body{ font-family: Vazirmatn, Tahoma, sans-serif; direction: rtl; }

            /* ── FE-02: Canonical button styles — mirrors x-core-ui::button ── */
            .btn{
                display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
                padding: .5rem 1rem; border-radius: .75rem; font-weight: 600; font-size: .875rem;
                border: 1px solid transparent; cursor: pointer; transition: all .15s ease;
                text-decoration: none; line-height: 1.25;
            }
            .btn-sm{ padding: .375rem .75rem; font-size: .8125rem; border-radius: .625rem; }
            .btn-lg{ padding: .625rem 1.25rem; font-size: 1rem; border-radius: .875rem; }
            .btn-primary{
                background: var(--ui-indigo,#4f46e5); color: #fff;
                box-shadow: 0 1px 3px rgba(79,70,229,.25);
            }
            .btn-primary:hover{ background: var(--ui-indigo-hover,#4338ca); }
            .btn-primary:focus-visible{ outline: 2px solid var(--ui-indigo,#4f46e5); outline-offset: 2px; }
            .btn-secondary{
                background: #f1f5f9; color: #475569; border-color: #e2e8f0;
            }
            .btn-secondary:hover{ background: #e2e8f0; }
            .btn-outline-secondary{
                background: transparent; color: var(--ui-text-secondary,#374151); border: 1px solid var(--ui-border-strong,#9ca3af);
            }
            .btn-outline-secondary:hover{ background: #f8fafc; }
            .btn-outline-primary{
                background: transparent; color: var(--ui-indigo,#4f46e5); border: 1px solid var(--ui-indigo,#4f46e5);
            }
            .btn-outline-primary:hover{ background: var(--ui-indigo-light,#eef2ff); }
            .btn-danger{
                background: var(--ui-danger,#dc2626); color: #fff; border-color: transparent;
                box-shadow: 0 1px 3px rgba(220,38,38,.25);
            }
            .btn-danger:hover{ background: var(--ui-danger-hover,#b91c1c); }
            .btn-success{
                background: var(--ui-success,#059669); color: #fff; border-color: transparent;
            }
            .btn-success:hover{ background: var(--ui-success-hover,#047857); }
            .btn-ghost{
                background: transparent; color: #64748b; border-color: transparent;
            }
            .btn-ghost:hover{ background: #f1f5f9; color: #374151; }
            .btn:disabled,.btn[disabled]{ opacity: .5; cursor: not-allowed; pointer-events: none; }

            /* ── FE-02: Form controls — canonical style ── */
            .form-control,.form-select{
                width: 100%; padding: .5rem 1rem; border: 1px solid var(--ui-border,#e2e8f0);
                border-radius: .625rem; text-align: right; background: #fff;
                font-family: inherit; font-size: .875rem; color: #1e293b;
                transition: border-color .15s, box-shadow .15s;
            }
            .form-control:focus,.form-select:focus{
                outline: none; border-color: var(--ui-indigo,#4f46e5);
                box-shadow: 0 0 0 3px rgba(79,70,229,.12);
            }
            .form-label{
                display: block; font-size: .875rem; font-weight: 500;
                color: var(--ui-text-secondary,#374151); margin-bottom: .5rem; text-align: right;
            }
            .form-check-input{ width: 1rem; height: 1rem; accent-color: var(--ui-indigo,#4f46e5); }

            /* ── Card & Alert ── */
            .card{ background: var(--ui-surface,#fff); border-radius: var(--ui-radius-xl,14px); box-shadow: 0 2px 8px rgba(15,23,42,.06); border: 1px solid var(--ui-border,#e2e8f0); }
            .card-body{ padding: 1rem 1.5rem; }
            .alert{ padding: .75rem 1rem; border-radius: .625rem; margin-bottom: 1rem; border: 1px solid; }
            .alert-success{ background: var(--ui-success-light,#f0fdf4); border-color: var(--ui-success-border,#bbf7d0); color: var(--ui-success-text,#166534); }
            .alert-danger{ background: var(--ui-danger-light,#fef2f2); border-color: var(--ui-danger-border,#fecaca); color: var(--ui-danger-text,#991b1b); }
            .alert-warning{ background: #fffbeb; border-color: #fde68a; color: #92400e; }
            .alert-info{ background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
            .sidebar-link{ display: flex; align-items: center; padding: 0.45rem 0.875rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; }
            form.mt-2 button{ display: flex; align-items: center; width: 100%; padding: 0.45rem 0.875rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; border: none; background: transparent; cursor: pointer; text-align: right; color: var(--ui-danger-border,#fca5a5); }
            form.mt-2 button:hover{ background: var(--ui-danger-bg,rgba(220,38,38,0.1)); color: var(--ui-danger,#dc2626); }
            .admin-sidebar-scroll-area{ scrollbar-width: thin; scrollbar-color: rgba(148,163,184,0.45) transparent; }
            .admin-sidebar-scroll-area::-webkit-scrollbar{ width: 8px; }
            .admin-sidebar-scroll-area::-webkit-scrollbar-thumb{ background: rgba(148,163,184,0.35); border-radius: 8px; }
            button,[type=button],[type=submit],[role=button],a.btn,.btn{ cursor: pointer; }
            [x-cloak]{ display:none !important; }
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
            border-color: color-mix(in srgb, var(--ui-border) 50%, transparent);
        }

        /* ─── Sidebar Links ─── */
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.45rem 0.875rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.75rem;
            color: var(--ui-text-muted);
            transition: all 0.2s ease;
            margin-bottom: 1px;
        }
        .sidebar-link:hover {
            background: var(--ui-hover-bg);
            color: var(--ui-indigo);
            transform: translateX(-2px);
        }
        .sidebar-link.active {
            background: var(--ui-active-bg);
            color: var(--ui-indigo);
            font-weight: 600;
            box-shadow: var(--ui-shadow-focus);
        }
        .sidebar-link svg { transition: transform 0.2s ease; }
        .sidebar-link:hover svg { transform: scale(1.1); }

        /* ─── Sidebar Group Toggle ─── */
        .sidebar-group-toggle {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.3rem 0.875rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ui-text-subtle);
            background: transparent;
            border: none;
            cursor: pointer;
            border-radius: 0.75rem;
            transition: color 0.2s ease;
            text-align: right;
            gap: 0.5rem;
        }
        .sidebar-group-toggle:hover { color: var(--ui-text-muted); }

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
            padding: 0.45rem 0.875rem;
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
    {{-- Theme Engine: must be LAST to override all static CSS vars above --}}
    @include('partials.theme-engine-tokens')
    @stack('head')
</head>
<body class="min-h-screen bg-gray-50" style="background: var(--admin-bg);">

    <div class="flex h-screen overflow-hidden min-h-0" id="admin-layout-root">
        <!-- Sidebar: با دکمه تاگل در دسکتاپ جمع می‌شود (فقط آیکن‌ها). همبرگر فقط موبایل. -->
        <aside id="admin-sidebar" class="hidden md:flex md:shrink-0 transition-[width] duration-300 ease-in-out w-64 min-h-0" data-collapsed="false">
            <div class="admin-sidebar-inner flex flex-col w-64 min-w-0 flex-1 min-h-0">
                <div class="admin-sidebar-scroll-area flex flex-col flex-1 min-h-0 overflow-y-auto overflow-x-hidden">
                    <!-- Logo / عنوان -->
                    <div class="flex items-center shrink-0 px-4 py-3 border-b min-h-14">
                        <svg class="w-6 h-6 shrink-0" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="max-width:24px;max-height:24px">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span class="sidebar-text mr-3 text-xl font-bold whitespace-nowrap overflow-hidden">{{ auth()->user()->is_admin ? 'پنل مدیریت' : 'پنل من' }}</span>
                    </div>

                    <!-- Navigation: ادمین = منوی ادمین، مشتری = منوی مشتری -->
                    <nav class="flex-1 px-3 py-3 min-h-0">
                        @if(auth()->user()->is_admin)
                            @foreach(\App\Helpers\AdminMenu::getGroups() as $groupLabel => $keys)
                                @php $visibleKeys = array_values(array_filter($keys, fn ($k) => \App\Helpers\AdminMenu::isMenuItemVisible($k))); @endphp
                                @if(count($visibleKeys) > 0)
                                    <div class="admin-sidebar-group" data-group-index="{{ $loop->index }}">
                                        {{-- Group toggle button — JS-driven collapse (Alpine Collapse plugin not loaded) --}}
                                        <button type="button" class="sidebar-group-toggle" data-group-toggle="{{ $loop->index }}" aria-expanded="false">
                                            <span class="sidebar-group-label sidebar-text">{{ $groupLabel }}</span>
                                            <svg class="sidebar-group-caret" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                        {{-- Collapsible items wrapper (JS-toggled); canonical x-core-ui::sidebar-group wraps items --}}
                                        <div class="admin-sidebar-item-list sidebar-group-items is-collapsed" data-group-items="{{ $loop->index }}">
                                            <x-core-ui::sidebar-group>
                                                @foreach($visibleKeys as $key)
                                                    <x-core-ui::navigation-item
                                                        href="{{ route(\App\Helpers\AdminMenu::getRoute($key)) }}"
                                                        :active="\App\Helpers\AdminMenu::isActive($key)"
                                                        class="sidebar-link">
                                                        <x-slot:icon>
                                                            @include('admin.partials.sidebar-icon-bare', ['key' => $key])
                                                        </x-slot:icon>
                                                        <span class="sidebar-text">{{ \App\Helpers\ModuleLabels::getTitle($key, null) }}</span>
                                                    </x-core-ui::navigation-item>
                                                @endforeach
                                            </x-core-ui::sidebar-group>
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
                                            <x-core-ui::sidebar-group>
                                                @foreach($visibleKeys as $key)
                                                    <x-core-ui::navigation-item
                                                        href="{{ route(\App\Helpers\CustomerMenu::getRoute($key)) }}"
                                                        :active="\App\Helpers\CustomerMenu::isActive($key)"
                                                        class="sidebar-link">
                                                        <x-slot:icon>
                                                            @include('admin.partials.customer-sidebar-icon-bare', ['key' => $key])
                                                        </x-slot:icon>
                                                        <span class="sidebar-text">{{ \App\Helpers\ModuleLabels::getTitle($key) }}</span>
                                                    </x-core-ui::navigation-item>
                                                @endforeach
                                            </x-core-ui::sidebar-group>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        <div class="sidebar-bottom-section pt-2 mt-2 border-t border-slate-600/40">
                            {{-- دکمه جمع/باز کردن سایدبار (داخل سایدبار، بالای خروج) --}}
                            <button type="button" id="admin-sidebar-inner-toggle" class="sidebar-collapse-btn">
                                <svg id="admin-sidebar-inner-icon-collapse" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                                </svg>
                                <svg id="admin-sidebar-inner-icon-expand" class="w-4 h-4 shrink-0 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7M18 19l-7-7 7-7"/>
                                </svg>
                                <span class="sidebar-text sidebar-collapse-text">جمع کردن منو</span>
                            </button>
                            @if(auth()->user()->is_admin)
                            <x-core-ui::navigation-item href="{{ route('dashboard') }}" class="sidebar-link">
                                <x-slot:icon>
                                    <svg class="w-4 h-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                    </svg>
                                </x-slot:icon>
                                <span class="sidebar-text">بازگشت به داشبورد</span>
                            </x-core-ui::navigation-item>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                                @csrf
                                <button type="submit" class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-lg text-red-300 hover:bg-red-950/40 hover:text-red-100 border-0 bg-transparent cursor-pointer text-right gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    {{-- ─── Education Recommendation Badge ────────────── --}}
                    @if(!empty($eduRecommendationCount) && $eduRecommendationCount > 0)
                    <a href="{{ route('admin.education.recommendations.index') }}"
                       class="relative inline-flex items-center justify-center w-9 h-9 rounded-xl text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                       title="{{ $eduRecommendationCount }} پیشنهاد آموزشی">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <span class="absolute -top-0.5 -left-0.5 min-w-[18px] h-[18px] flex items-center justify-center text-[10px] font-bold text-white bg-indigo-600 rounded-full px-1">
                            {{ $eduRecommendationCount > 9 ? '9+' : $eduRecommendationCount }}
                        </span>
                    </a>
                    @endif
                    {{-- ─── Notification Bell ───────────────────────── --}}
                    <div x-data="platformBell()" x-init="init()" @click.outside="open = false" class="relative" style="z-index:60">
                        <button @click="toggle()" type="button"
                                class="relative inline-flex items-center justify-center w-9 h-9 rounded-xl text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                                title="اعلان‌های سیستمی">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span x-show="unreadCount > 0" x-text="unreadCount > 99 ? '99+' : unreadCount"
                                  class="absolute -top-0.5 -left-0.5 min-w-[18px] h-[18px] flex items-center justify-center text-[10px] font-bold text-white bg-red-500 rounded-full px-1 pointer-events-none"
                                  style="display:none"></span>
                        </button>
                        {{-- Dropdown --}}
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                             class="absolute left-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden"
                             style="display:none">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                                <span class="text-xs font-bold text-gray-700">اعلان‌های سیستمی</span>
                                <button x-show="unreadCount > 0" @click="markAllRead()" class="text-xs text-indigo-600 hover:underline font-semibold">همه را خواندم</button>
                            </div>
                            <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                                <template x-if="items.length === 0">
                                    <div class="text-center py-8 text-gray-400 text-sm">اعلان جدیدی ندارید 🎉</div>
                                </template>
                                <template x-for="n in items" :key="n.id">
                                    <div class="flex items-start gap-3 px-4 py-3 hover:bg-indigo-50/60 transition-colors cursor-pointer" @click="markRead(n)">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 text-sm"
                                             :class="{'bg-blue-50': n.type==='info','bg-emerald-50': n.type==='success','bg-amber-50': n.type==='warning','bg-red-50': n.type==='error'}">
                                            <span x-text="n.type==='success'?'✅':n.type==='warning'?'⚠️':n.type==='error'?'🚨':'📢'"></span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-semibold text-gray-800 leading-snug" x-text="n.title"></p>
                                            <p class="text-[11px] text-gray-500 mt-0.5 truncate" x-text="n.body || ''"></p>
                                            <p class="text-[10px] text-gray-400 mt-0.5" x-text="n.created_at"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50 text-center">
                                <a href="{{ route('admin.system-alerts.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline">مشاهده همه اعلان‌ها →</a>
                            </div>
                        </div>
                    </div>
                    {{-- ─── Profile Dropdown ─────────────────────────── --}}
                    @php
                        $__adminUser    = auth()->user();
                        $__adminProfile = $__adminUser->profile;
                        $__adminAvatar  = $__adminProfile?->avatar ? url($__adminProfile->avatar) : null;
                        $__adminName    = $__adminProfile?->full_name ?: $__adminUser->mobile;
                        $__adminInitial = mb_substr($__adminName, 0, 1);
                    @endphp
                    <div x-data="{ open: false }" @click.outside="open = false" class="relative" style="z-index:50">
                        <button @click="open = !open" type="button"
                                class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-gray-100 transition-all">
                            @if($__adminAvatar)
                                <img src="{{ $__adminAvatar }}" class="w-8 h-8 rounded-full object-cover ring-2 ring-white shadow-sm admin-avatar-img" alt="{{ $__adminName }}">
                            @else
                                <div class="w-8 h-8 rounded-full bg-linear-to-br from-indigo-500 to-purple-500 flex items-center justify-center ring-2 ring-white shadow-sm admin-avatar-initials">
                                    <span class="text-xs font-bold text-white">{{ $__adminInitial }}</span>
                                </div>
                            @endif
                            <div class="hidden sm:block text-right">
                                <p class="text-xs font-semibold text-gray-700 leading-none">{{ $__adminName }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5 leading-none">{{ $__adminUser->mobile }}</p>
                            </div>
                            <svg class="w-3.5 h-3.5 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                             class="absolute left-0 top-full mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2"
                             style="display:none">
                            {{-- User info --}}
                            <div class="px-4 py-3 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    @if($__adminAvatar)
                                        <img src="{{ $__adminAvatar }}" class="w-10 h-10 rounded-full object-cover ring-2 ring-indigo-100 admin-avatar-img">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-linear-to-br from-indigo-500 to-purple-500 flex items-center justify-center admin-avatar-initials">
                                            <span class="text-sm font-bold text-white">{{ $__adminInitial }}</span>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $__adminName }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ $__adminUser->mobile }}</p>
                                    </div>
                                </div>
                            </div>
                            {{-- Links --}}
                            <div class="py-1">
                                <a href="{{ route('admin.profile.index') }}"
                                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                    مشاهده پروفایل
                                </a>
                                <a href="{{ route('admin.profile.index') }}"
                                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                                    ویرایش پروفایل
                                </a>
                                <a href="{{ route('admin.dashboard') }}"
                                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                                    داشبورد
                                </a>
                            </div>
                            <div class="border-t border-gray-100 pt-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                            class="flex w-full items-center gap-2.5 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                        خروج از حساب
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                @if(session('success'))
                    <div class="alert alert-success mb-6 flex items-center gap-2" role="alert"
                         x-data="{ show: true }" x-show="show" x-transition.duration.300ms
                         x-init="setTimeout(() => show = false, 5000)">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span class="flex-1">{{ session('success') }}</span>
                        <button @click="show=false" class="mr-auto opacity-60 hover:opacity-100 text-lg leading-none" aria-label="بستن">✕</button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger mb-6 flex items-center gap-2" role="alert"
                         x-data="{ show: true }" x-show="show" x-transition.duration.300ms>
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        <span class="flex-1">{{ session('error') }}</span>
                        <button @click="show=false" class="mr-auto opacity-60 hover:opacity-100 text-lg leading-none" aria-label="بستن">✕</button>
                    </div>
                @endif

                @if(session('info'))
                    <div class="alert alert-info mb-6 flex items-center gap-2" role="status"
                         x-data="{ show: true }" x-show="show" x-transition.duration.300ms
                         x-init="setTimeout(() => show = false, 6000)">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                        <span class="flex-1">{{ session('info') }}</span>
                        <button @click="show=false" class="mr-auto opacity-60 hover:opacity-100 text-lg leading-none" aria-label="بستن">✕</button>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert alert-warning mb-6 flex items-center gap-2" role="alert"
                         x-data="{ show: true }" x-show="show" x-transition.duration.300ms
                         x-init="setTimeout(() => show = false, 7000)">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <span class="flex-1">{{ session('warning') }}</span>
                        <button @click="show=false" class="mr-auto opacity-60 hover:opacity-100 text-lg leading-none" aria-label="بستن">✕</button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger mb-6" role="alert"
                         x-data="{ show: true }" x-show="show" x-transition.duration.300ms>
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

    @include('partials.floating-search-widget')
    @include('partials.notification-permission', ['context' => 'platform_admin'])
    @include('partials.admin-ticket-notifier')
    @stack('scripts')
    @yield('scripts')

    {{-- ─── Education: Walkthrough Runner ──────────────────────────────────── --}}
    @if(!empty($walkthroughTriggers) && $walkthroughTriggers->isNotEmpty() && config('education.walkthroughs_enabled', true))
    <div x-data="eduWalkthroughRunner({{ json_encode($walkthroughTriggers->values()->map(fn($w) => [
        'id'    => $w->id,
        'title' => $w->title,
        'steps' => $w->steps->map(fn($s) => ['title' => $s->title, 'body' => $s->body, 'selector' => $s->target_selector])->values(),
    ])->values()) }})" x-init="init()" x-cloak>
        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             class="fixed bottom-6 left-6 z-50 w-80 bg-white rounded-2xl shadow-2xl border border-indigo-100 overflow-hidden"
             role="dialog" aria-modal="true" :aria-label="'راهنمای گام‌به‌گام: ' + current().title"
             style="direction:rtl">
            <div class="bg-indigo-600 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-white opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    <span class="text-white text-sm font-semibold truncate max-w-[180px]" x-text="current().title"></span>
                </div>
                <button @click="dismiss()" class="text-white/70 hover:text-white transition-colors p-1 rounded" aria-label="بستن">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-4">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-xs text-gray-400" x-text="'گام ' + (stepIndex+1) + ' از ' + current().steps.length"></span>
                    <div class="flex gap-1">
                        <template x-for="(s, i) in current().steps" :key="i">
                            <div class="w-1.5 h-1.5 rounded-full transition-colors" :class="i <= stepIndex ? 'bg-indigo-500' : 'bg-gray-200'"></div>
                        </template>
                    </div>
                </div>
                <h4 class="text-sm font-semibold text-gray-800 mb-1" x-text="currentStep().title"></h4>
                <p class="text-xs text-gray-500 leading-relaxed" x-html="currentStep().body"></p>
            </div>
            <div class="px-4 pb-4 flex justify-between gap-2">
                <button @click="prev()" x-show="stepIndex > 0"
                        class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                    قبلی
                </button>
                <div class="flex-1"></div>
                <button @click="next()"
                        class="px-4 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors"
                        x-text="stepIndex < current().steps.length - 1 ? 'بعدی' : 'فهمیدم'"></button>
            </div>
        </div>

        <script>
        function eduWalkthroughRunner(walkthroughs) {
            return {
                open: false,
                walkthroughs: walkthroughs,
                wtIndex: 0,
                stepIndex: 0,
                csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
                init() {
                    if (!this.walkthroughs.length) return;
                    const seen = JSON.parse(localStorage.getItem('edu_seen_wts') || '[]');
                    const idx = this.walkthroughs.findIndex(w => !seen.includes(w.id));
                    if (idx === -1) return;
                    this.wtIndex = idx;
                    this.stepIndex = 0;
                    this.open = true;
                    this.$nextTick(() => { if (this.currentStep().selector) this.highlight(this.currentStep().selector); });
                },
                current() { return this.walkthroughs[this.wtIndex] || { title: '', steps: [] }; },
                currentStep() { return this.current().steps[this.stepIndex] || { title: '', body: '', selector: null }; },
                next() {
                    if (this.stepIndex < this.current().steps.length - 1) {
                        this.stepIndex++;
                        this.$nextTick(() => { if (this.currentStep().selector) this.highlight(this.currentStep().selector); });
                    } else {
                        this.complete();
                    }
                },
                prev() { if (this.stepIndex > 0) { this.stepIndex--; } },
                complete() {
                    const id = this.current().id;
                    this.markSeen(id);
                    fetch(`/admin/education/walkthroughs/${id}/complete`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' }
                    }).catch(() => {});
                    const next = this.walkthroughs.findIndex((w, i) => i > this.wtIndex && !JSON.parse(localStorage.getItem('edu_seen_wts') || '[]').includes(w.id));
                    if (next !== -1) { this.wtIndex = next; this.stepIndex = 0; } else { this.open = false; }
                },
                dismiss() {
                    this.markSeen(this.current().id);
                    this.open = false;
                },
                markSeen(id) {
                    const seen = JSON.parse(localStorage.getItem('edu_seen_wts') || '[]');
                    if (!seen.includes(id)) { seen.push(id); localStorage.setItem('edu_seen_wts', JSON.stringify(seen)); }
                },
                highlight(selector) {
                    document.querySelectorAll('.edu-wt-highlight').forEach(el => el.classList.remove('edu-wt-highlight'));
                    const el = document.querySelector(selector);
                    if (el) { el.classList.add('edu-wt-highlight'); el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
                },
            };
        }
        </script>
        <style>[x-cloak]{display:none!important}.edu-wt-highlight{outline:2px solid #4f46e5;outline-offset:3px;border-radius:6px;}</style>
    </div>
    @endif
    <script>
        (function() {
            var sidebar      = document.getElementById('admin-sidebar');
            var topToggle    = document.getElementById('admin-sidebar-toggle');
            var innerToggle  = document.getElementById('admin-sidebar-inner-toggle');
            var topExpand    = document.getElementById('admin-sidebar-toggle-icon-expand');
            var topCollapse  = document.getElementById('admin-sidebar-toggle-icon-collapse');
            var innerExpand  = document.getElementById('admin-sidebar-inner-icon-expand');
            var innerCollapse= document.getElementById('admin-sidebar-inner-icon-collapse');
            var innerText    = innerToggle ? innerToggle.querySelector('.sidebar-collapse-text') : null;
            var key          = 'admin-sidebar-collapsed';

            if (!sidebar) return;

            var collapsed = localStorage.getItem(key) === 'true';

            function apply() {
                sidebar.setAttribute('data-collapsed', collapsed ? 'true' : 'false');
                /* آیکن‌های topbar */
                if (topExpand)    topExpand.classList.toggle('hidden', !collapsed);
                if (topCollapse)  topCollapse.classList.toggle('hidden', collapsed);
                /* آیکن‌های دکمه داخل سایدبار */
                if (innerExpand)  innerExpand.classList.toggle('hidden', !collapsed);
                if (innerCollapse)innerCollapse.classList.toggle('hidden', collapsed);
                /* متن دکمه داخل سایدبار */
                if (innerText) innerText.textContent = collapsed ? 'باز کردن منو' : 'جمع کردن منو';
            }

            function doToggle() {
                collapsed = !collapsed;
                localStorage.setItem(key, collapsed ? 'true' : 'false');
                apply();
            }

            apply();
            if (topToggle)   topToggle.addEventListener('click', doToggle);
            if (innerToggle) innerToggle.addEventListener('click', doToggle);
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
    <script>
    function platformBell() {
        return {
            open: false,
            unreadCount: 0,
            items: [],
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
            _timer: null,
            init() {
                this.fetch();
                this._timer = setInterval(() => this.fetch(), 30000);
            },
            fetch() {
                fetch('{{ route('admin.system-alerts.unread-count') }}', {
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
                })
                .then(r => r.json())
                .then(d => { this.unreadCount = d.count || 0; this.items = d.items || []; })
                .catch(() => {});
            },
            toggle() {
                this.open = !this.open;
                if (this.open) this.fetch();
            },
            markRead(n) {
                fetch('/admin/system-alerts/' + n.id + '/read', {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json'}
                }).then(() => {
                    this.items = this.items.filter(i => i.id !== n.id);
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    if (n.link) window.location.href = n.link;
                }).catch(() => {});
            },
            markAllRead() {
                fetch('{{ route('admin.system-alerts.read-all') }}', {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json'}
                }).then(() => { this.unreadCount = 0; this.items = []; this.open = false; }).catch(() => {});
            },
        };
    }
    </script>
</body>
</html>
