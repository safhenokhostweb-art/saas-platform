<nav class="navbar sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-6 flex-wrap">
                <a href="<?php echo e(route('home')); ?>" class="navbar-brand flex items-center">
                    <?php if(\App\Helpers\ResellerBranding::logoUrl()): ?>
                        <img src="<?php echo e(\App\Helpers\ResellerBranding::logoUrl()); ?>" alt="<?php echo e(\App\Helpers\ResellerBranding::platformName()); ?>" class="h-8 w-auto ml-2 shrink-0" style="max-height:2rem">
                    <?php else: ?>
                        <svg class="w-6 h-6 ml-2 shrink-0" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="max-width:24px;max-height:24px;flex-shrink:0">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    <?php endif; ?>
                    <span class="ml-2 font-bold"><?php echo e(\App\Helpers\ResellerBranding::platformName()); ?></span>
                </a>
                <?php $pageMenuPos = \App\Models\Setting::get('page_menu_position', 'top'); ?>
                <?php if($pageMenuPos === 'top'): ?>
                    <?php echo $__env->make('partials.pages-menu', ['position' => 'top'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                <?php endif; ?>
            </div>

            <div class="flex items-center space-x-6 space-x-reverse">
                <?php if(auth()->guard()->check()): ?>
                    <a href="<?php echo e(route('dashboard')); ?>" class="navbar-link <?php echo e(request()->routeIs('dashboard') ? 'text-blue-600' : ''); ?>">
                        <svg class="w-4 h-4 inline ml-1 shrink-0" width="16" height="16" style="max-width:16px;max-height:16px;vertical-align:middle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        داشبورد
                    </a>
                    <a href="<?php echo e(route('profile.edit')); ?>" class="navbar-link <?php echo e(request()->routeIs('profile.*') ? 'text-blue-600' : ''); ?>">
                        <svg class="w-4 h-4 inline ml-1 shrink-0" width="16" height="16" style="max-width:16px;max-height:16px;vertical-align:middle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        پروفایل
                    </a>
                    <?php if(auth()->user()->reseller): ?>
                        <a href="<?php echo e(route('reseller.dashboard')); ?>" class="navbar-link <?php echo e(request()->routeIs('reseller.*') ? 'text-blue-600' : ''); ?>">
                            <svg class="w-4 h-4 inline ml-1 shrink-0" width="16" height="16" style="max-width:16px;max-height:16px;vertical-align:middle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            پنل نمایندگی
                        </a>
                    <?php endif; ?>
                    <?php if(auth()->user()->is_admin): ?>
                        <a href="<?php echo e(route('admin.dashboard')); ?>" class="navbar-link <?php echo e(request()->routeIs('admin.*') ? 'text-blue-600' : ''); ?>">
                            <svg class="w-4 h-4 inline ml-1 shrink-0" width="16" height="16" style="max-width:16px;max-height:16px;vertical-align:middle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            پنل مدیریت
                        </a>
                    <?php endif; ?>
                    <form action="<?php echo e(route('logout')); ?>" method="POST" class="inline">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="navbar-link text-red-600 hover:text-red-700">
                            <svg class="w-4 h-4 inline ml-1 shrink-0" width="16" height="16" style="max-width:16px;max-height:16px;vertical-align:middle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            خروج
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?php echo e(route('login.show')); ?>" class="navbar-link <?php echo e(request()->routeIs('login.*') ? 'text-blue-600' : ''); ?>">
                        ورود
                    </a>
                    <?php if(\App\Helpers\PlatformSettings::isRegistrationOpen()): ?>
                    <a href="<?php echo e(route('register')); ?>" class="btn btn-primary">
                        ثبت نام
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/partials/navbar.blade.php ENDPATH**/ ?>