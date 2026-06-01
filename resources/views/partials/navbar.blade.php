<nav class="navbar sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-6 flex-wrap">
                <a href="{{ route('home') }}" class="navbar-brand flex items-center">
                    @if(\App\Helpers\ResellerBranding::logoUrl())
                        <img src="{{ \App\Helpers\ResellerBranding::logoUrl() }}" alt="{{ \App\Helpers\ResellerBranding::platformName() }}" class="h-8 w-auto ml-2 shrink-0" style="max-height:2rem">
                    @else
                        <svg class="w-6 h-6 ml-2 shrink-0" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="max-width:24px;max-height:24px;flex-shrink:0">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    @endif
                    <span class="ml-2 font-bold">{{ \App\Helpers\ResellerBranding::platformName() }}</span>
                </a>
                @php $pageMenuPos = \App\Models\Setting::get('page_menu_position', 'top'); @endphp
                @if($pageMenuPos === 'top')
                    @include('partials.pages-menu', ['position' => 'top'])
                @endif
            </div>

            <div class="flex items-center gap-3">
                @auth
                    @php
                        $__navUser    = auth()->user();
                        $__navProfile = $__navUser->profile;
                        $__navAvatar  = $__navProfile?->avatar ? url($__navProfile->avatar) : null;
                        $__navName    = $__navProfile?->full_name ?: $__navUser->mobile;
                        $__navInitial = mb_substr($__navName, 0, 1);
                    @endphp

                    {{-- Profile Dropdown --}}
                    <div x-data="{ open: false }" @click.outside="open = false" class="relative" style="z-index:100">
                        <button @click="open = !open" type="button"
                                class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl hover:bg-gray-100 transition-all">
                            @if($__navAvatar)
                                <img src="{{ $__navAvatar }}" class="w-8 h-8 rounded-full object-cover ring-2 ring-white shadow-sm" alt="{{ $__navName }}">
                            @else
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center ring-2 ring-white shadow-sm">
                                    <span class="text-xs font-bold text-white">{{ $__navInitial }}</span>
                                </div>
                            @endif
                            <span class="hidden sm:block text-sm font-medium text-gray-700 max-w-[120px] truncate">{{ $__navName }}</span>
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
                                    @if($__navAvatar)
                                        <img src="{{ $__navAvatar }}" class="w-10 h-10 rounded-full object-cover ring-2 ring-indigo-100">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center">
                                            <span class="text-sm font-bold text-white">{{ $__navInitial }}</span>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $__navName }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ $__navUser->mobile }}</p>
                                    </div>
                                </div>
                            </div>
                            {{-- Links --}}
                            <div class="py-1">
                                <a href="{{ route('dashboard') }}"
                                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors {{ request()->routeIs('dashboard') ? 'text-indigo-600 bg-indigo-50' : '' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                                    داشبورد
                                </a>
                                <a href="{{ route('profile.edit') }}"
                                   class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors {{ request()->routeIs('profile.*') ? 'text-indigo-600 bg-indigo-50' : '' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                    پروفایل من
                                </a>
                                @if($__navUser->reseller)
                                    <a href="{{ route('reseller.dashboard') }}"
                                       class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        پنل نمایندگی
                                    </a>
                                @endif
                                @if($__navUser->is_admin)
                                    <a href="{{ route('admin.dashboard') }}"
                                       class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        پنل مدیریت
                                    </a>
                                @endif
                            </div>
                            <div class="border-t border-gray-100 pt-1">
                                <form action="{{ route('logout') }}" method="POST">
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
                @else
                    <a href="{{ route('login.show') }}" class="navbar-link">ورود</a>
                    @if(\App\Helpers\PlatformSettings::isRegistrationOpen())
                        <a href="{{ route('register') }}" class="btn btn-primary">ثبت نام</a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</nav>
