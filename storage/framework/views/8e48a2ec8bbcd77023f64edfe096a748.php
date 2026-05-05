<?php $__env->startSection('header','سایت‌ها'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.sites.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<div class="flex flex-wrap gap-2 items-center">
    <a href="<?php echo e(route('admin.sites.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
        افزودن سایت
    </a>
    <?php if(\App\Helpers\PlatformModules::isEnabled('notifications')): ?>
    <a href="<?php echo e(route('admin.sites.notifications.create-bulk')); ?>" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded hover:bg-slate-50">
        ارسال اعلان به چند سایت
    </a>
    <?php endif; ?>
</div>
<?php if(isset($filterCustomer) && $filterCustomer): ?>
    <p class="mt-2 text-sm text-gray-600">
        نمایش سایت‌های مشتری: <strong><?php echo e($filterCustomer->company_name ?? $filterCustomer->name); ?></strong>
        <a href="<?php echo e(route('admin.sites.index')); ?>" class="text-indigo-600 mr-2">نمایش همه</a>
    </p>
<?php endif; ?>
<?php if(isset($filterModule) && $filterModule): ?>
    <p class="mt-2 text-sm text-gray-600">
        فیلتر ماژول: <strong><?php echo e($filterModule->title); ?></strong>
        <a href="<?php echo e(route('admin.sites.index')); ?>" class="text-indigo-600 mr-2">نمایش همه</a>
    </p>
<?php endif; ?>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">عنوان / دامنه</th>
                <th class="p-3 text-right">مشتری</th>
                <th class="p-3 text-right">ماژول‌های خریداری‌شده</th>
                <th class="p-3 text-right">انقضای پشتیبانی</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $supportStatus = $site->supportStatus(); ?>
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3">
                        <span class="font-medium"><?php echo e($site->title); ?></span>
                        <?php if($site->domain): ?>
                            <span class="text-gray-500 block text-xs"><?php echo e($site->domain); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <a href="<?php echo e(route('admin.customers.edit', $site->customer)); ?>" class="text-indigo-600 hover:underline"><?php echo e($site->customer->company_name ?? $site->customer->name); ?></a>
                    </td>
                    <td class="p-3">
                        <?php $allModules = \App\Models\Module::where('is_active', true)->get(); ?>
                        <?php if($allModules->isEmpty()): ?>
                            <span class="text-gray-400">—</span>
                        <?php else: ?>
                            <div class="flex flex-wrap items-center gap-2">
                                <?php $__currentLoopData = $allModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php $siteMod = $site->modules->firstWhere('id', $mod->id); $isActive = $siteMod && $siteMod->pivot->is_active; ?>
                                    <form method="post" action="<?php echo e(route('admin.sites.modules.toggle', $site)); ?>" class="inline" data-module="<?php echo e($mod->slug); ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="module_id" value="<?php echo e($mod->id); ?>">
                                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs border transition <?php echo e($isActive ? 'bg-indigo-100 border-indigo-300 text-indigo-700' : 'bg-gray-50 border-gray-200 text-gray-500'); ?>" title="<?php echo e($isActive ? 'غیرفعال' : 'فعال'); ?>">
                                            <span class="w-2 h-2 rounded-full <?php echo e($isActive ? 'bg-green-500' : 'bg-gray-300'); ?>"></span>
                                            <?php echo e($mod->title); ?>

                                        </button>
                                    </form>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                            <a href="<?php echo e(route('admin.sites.modules.edit', $site)); ?>" class="text-indigo-600 text-xs block mt-1">مدیریت کامل ماژول‌ها</a>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <?php if($site->support_expires_at): ?>
                            <span class="
                                <?php if($supportStatus === 'expired'): ?> text-red-600 font-medium
                                <?php elseif($supportStatus === 'expiring_soon'): ?> text-amber-600
                                <?php else: ?> text-gray-600 <?php endif; ?>">
                                <?php echo e(\App\Helpers\Jalali::format($site->support_expires_at)); ?>

                            </span>
                            <?php if($supportStatus === 'expired'): ?>
                                <span class="block text-xs text-red-600">منقضی شده</span>
                            <?php elseif($supportStatus === 'expiring_soon'): ?>
                                <span class="block text-xs text-amber-600">به‌زودی منقضی</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="text-indigo-600 hover:underline">ویرایش</a>
                        <?php if(\App\Helpers\PlatformModules::isEnabled('notifications')): ?>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="<?php echo e(route('admin.sites.notifications.index', $site)); ?>" class="text-indigo-600 hover:underline" title="ارسال اعلان به این سایت">اعلان‌ها</a>
                        <?php endif; ?>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="<?php echo e(route('admin.sites.modules.edit', $site)); ?>" class="text-indigo-600 hover:underline">ماژول‌ها</a>
                        <?php if(\App\Helpers\AdminMenu::isVisible('sites_menus')): ?>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="<?php echo e(route('admin.sites.menus.index', $site)); ?>" class="text-indigo-600 hover:underline" title="منو و ناوبری">منوها</a>
                        <?php endif; ?>
                        <?php if(\Illuminate\Support\Facades\Schema::hasTable('seo_redirects')): ?>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="<?php echo e(route('admin.sites.seo.index', $site)); ?>" class="text-indigo-600 hover:underline" title="سئو پیشرفته">سئو</a>
                        <?php endif; ?>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="<?php echo e(route('admin.sites.updates.index', $site)); ?>" class="text-indigo-600 hover:underline">بروزرسانی</a>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="<?php echo e(route('admin.sites.license.create', $site)); ?>" class="text-indigo-600 hover:underline">لایسنس</a>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="<?php echo e(route('admin.captcha.site', $site)); ?>" class="text-indigo-600 hover:underline">کپچا</a>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/index.blade.php ENDPATH**/ ?>