<?php $__env->startSection('header', 'دسترسی و منو'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.access.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<div class="max-w-4xl space-y-8">

    <!-- فقط پنل ادمین: نمایش آیتم‌های منوی کناری -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold text-lg mb-2">نمایش آیتم‌های منوی کناری <span class="text-blue-600">(فقط پنل ادمین)</span></h2>
        <p class="text-gray-500 text-sm mb-4">هر آیتمی که غیرفعال شود در منوی کناری <strong>پنل ادمین</strong> نمایش داده نمی‌شود. این تنظیم روی سایت مشتری اثری ندارد.</p>

        <form method="POST" action="<?php echo e(route('admin.access.update')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="section" value="admin_menu">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php $__currentLoopData = \App\Helpers\AdminMenu::MENU_KEYS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="menu_<?php echo e($key); ?>" value="1" class="form-check-input"
                               <?php echo e(($menuVisibility[$key] ?? true) ? 'checked' : ''); ?>>
                        <span><?php echo e(\App\Helpers\ModuleLabels::getTitle($key)); ?></span>
                    </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <div class="pt-4">
                <button type="submit" class="btn btn-primary" name="save_admin_menu" value="1">ذخیره منوی ادمین</button>
            </div>
        </form>
    </div>

    <!-- ماژول‌های مختص پلتفرم (فقط پنل ادمین) -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold text-lg mb-2">ماژول‌های مختص پلتفرم <span class="text-amber-600">(فقط پنل ادمین)</span></h2>
        <p class="text-gray-500 text-sm mb-4">این ماژول‌ها فقط در پنل ادمین پلتفرم وجود دارند و به سایت مشتری اختصاص داده نمی‌شوند. با غیرفعال کردن، لینک‌های مربوطه از منو و صفحات حذف می‌شوند.</p>

        <form method="POST" action="<?php echo e(route('admin.access.update')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="section" value="platform_modules">
            <div class="space-y-3">
                <?php $__currentLoopData = $platformModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $info): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex flex-wrap items-start gap-3 p-3 rounded-lg border border-slate-100 hover:bg-slate-50">
                        <label class="flex items-center gap-2 cursor-pointer flex-1">
                            <input type="checkbox" name="platform_module_<?php echo e($key); ?>" value="1" class="form-check-input"
                                   <?php echo e(\App\Helpers\PlatformModules::isEnabled($key) ? 'checked' : ''); ?>>
                            <span class="font-medium"><?php echo e($info['title']); ?></span>
                        </label>
                        <p class="text-gray-500 text-sm"><?php echo e($info['description']); ?></p>
                        <?php if(!empty($info['routes'])): ?>
                            <div class="flex gap-2 flex-wrap">
                                <?php $__currentLoopData = $info['routes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $routeName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php if(\Illuminate\Support\Facades\Route::has($routeName)): ?>
                                        <a href="<?php echo e(route($routeName)); ?>" class="text-indigo-600 text-sm whitespace-nowrap hover:underline"><?php echo e($label); ?></a>
                                    <?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        <?php elseif(!empty($info['route']) && \Illuminate\Support\Facades\Route::has($info['route'])): ?>
                            <a href="<?php echo e(route($info['route'])); ?>" class="text-indigo-600 text-sm whitespace-nowrap hover:underline">مدیریت</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php if(empty($platformModules)): ?>
                <p class="text-gray-500 text-sm">ماژول مختص پلتفرمی تعریف نشده است.</p>
            <?php else: ?>
                <div class="pt-4">
                    <button type="submit" class="btn btn-primary" name="save_platform_modules" value="1">ذخیره ماژول‌های پلتفرم</button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- فقط سایت مشتری: ماژول‌های قابل اختصاص به سایت -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold text-lg mb-2">ماژول‌ها برای سایت مشتری</h2>
        <p class="text-gray-500 text-sm mb-4">ماژول‌های فعال در لیست ماژول‌های قابل اختصاص به <strong>سایت مشتری</strong> قرار می‌گیرند. این تنظیم فقط روی ویرایش سایت و دسترسی مشتری اثر دارد و منوی پنل ادمین را تغییر نمی‌دهد.</p>

        <form method="POST" action="<?php echo e(route('admin.access.update')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="section" value="modules">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="modules[]" value="<?php echo e($module->id); ?>" class="form-check-input"
                               <?php echo e($module->is_active ? 'checked' : ''); ?>>
                        <span><?php echo e(\App\Helpers\ModuleLabels::getTitle($module->slug)); ?></span>
                        <?php if(\App\Helpers\ModuleLabels::getDescription($module->slug)): ?>
                            <span class="text-gray-400 text-xs">(<?php echo e(Str::limit(\App\Helpers\ModuleLabels::getDescription($module->slug), 30)); ?>)</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <?php if($modules->isEmpty()): ?>
                <p class="text-gray-500 text-sm">هنوز ماژولی تعریف نشده است. از بخش سایت‌ها می‌توانید ماژول ایجاد کنید یا در دیتابیس جدول modules را پر کنید.</p>
            <?php endif; ?>

            <div class="pt-4">
                <button type="submit" class="btn btn-primary" name="save_modules" value="1">ذخیره ماژول‌های مشتری</button>
            </div>
        </form>
    </div>

    <!-- منوی پنل مشتری (کاربر غیرادمین) -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold text-lg mb-2">منوی پنل مشتری <span class="text-green-600">(امکانات قابل مشاهده برای کاربر عادی)</span></h2>
        <p class="text-gray-500 text-sm mb-4">هر آیتمی که فعال باشد در منوی کناری <strong>پنل مشتری</strong> (داشبورد کاربران غیرادمین) نمایش داده می‌شود و کاربر به آن صفحه دسترسی دارد. غیرفعال کردن یک آیتم آن را از منو حذف و دسترسی را محدود می‌کند.</p>

        <form method="POST" action="<?php echo e(route('admin.access.update')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="section" value="customer_menu">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php $__currentLoopData = \App\Helpers\CustomerMenu::MENU_KEYS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="customer_menu_<?php echo e($key); ?>" value="1" class="form-check-input"
                               <?php echo e(($customerMenuVisibility[$key] ?? true) ? 'checked' : ''); ?>>
                        <span><?php echo e(\App\Helpers\ModuleLabels::getTitle($key)); ?></span>
                    </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <div class="pt-4">
                <button type="submit" class="btn btn-primary" name="save_customer_menu" value="1">ذخیره منوی پنل مشتری</button>
            </div>
        </form>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/access/index.blade.php ENDPATH**/ ?>