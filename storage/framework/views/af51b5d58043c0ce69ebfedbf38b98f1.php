

<?php $__env->startSection('header', 'اعلان‌های سایت — ' . $site->title); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="<?php echo e(route('admin.sites.index')); ?>" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="text-indigo-600 hover:underline"><?php echo e($site->title); ?></a>
        → اعلان‌ها
    </p>

    <?php if(session('success')): ?>
        <div class="alert alert-success mb-4"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm rounded-xl mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="font-bold mb-0">اعلان‌های ارسالی از پلتفرم</h5>
            <div class="flex gap-2 flex-wrap">
                <a href="<?php echo e(route('admin.sites.notifications.create', $site)); ?>" class="btn btn-primary">اعلان جدید (این سایت)</a>
                <a href="<?php echo e(route('admin.sites.notifications.create-bulk')); ?>" class="btn btn-outline-secondary">ارسال به چند سایت</a>
                <a href="<?php echo e(route('admin.notification-templates.index')); ?>" class="btn btn-outline-secondary">قالب‌های خوش‌آمدگویی</a>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-4">این اعلان‌ها فقط در پنل مدیریت سایت مشتری به ادمین نمایش داده می‌شوند. اگر ماژول اعلان برای این سایت فعال نیست، از صفحهٔ <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="text-indigo-600">ویرایش سایت</a> یا <a href="<?php echo e(route('admin.sites.modules.edit', $site)); ?>" class="text-indigo-600">ماژول‌ها</a> آن را فعال کنید.</p>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>اولویت</th>
                        <th>مخاطب</th>
                        <th>موقعیت</th>
                        <th>تصویر</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($n->title); ?></td>
                            <td><?php
                                $p = $n->priority ?? 'normal';
                                $labels = ['low' => 'کم', 'normal' => 'عادی', 'high' => 'مهم', 'urgent' => 'فوری'];
                                $classes = ['low' => 'bg-gray-100 text-gray-700', 'normal' => 'bg-sky-100 text-sky-800', 'high' => 'bg-amber-100 text-amber-800', 'urgent' => 'bg-red-100 text-red-800'];
                            ?>
                                <span class="badge <?php echo e($classes[$p] ?? 'bg-sky-100 text-sky-800'); ?>"><?php echo e($labels[$p] ?? 'عادی'); ?></span></td>
                            <td><span class="badge bg-amber-100 text-amber-800">ادمین</span></td>
                            <td><?php echo e($n->position === 'top' ? 'بالا' : ($n->position === 'left' ? 'چپ' : 'راست')); ?></td>
                            <td><?php echo e($n->image_path ? '✓' : '—'); ?></td>
                            <td><?php echo e($n->is_active ? 'فعال' : 'غیرفعال'); ?></td>
                            <td>
                                <a href="<?php echo e(route('admin.sites.notifications.edit', [$site, $n])); ?>" class="btn btn-sm btn-outline-primary">ویرایش</a>
                                <form method="POST" action="<?php echo e(route('admin.sites.notifications.destroy', [$site, $n])); ?>" class="d-inline" onsubmit="return confirm('حذف این اعلان؟');">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7" class="text-center text-gray-500 py-4">هنوز اعلانی ارسال نشده است. <a href="<?php echo e(route('admin.sites.notifications.create', $site)); ?>" class="text-indigo-600">اولین اعلان را ایجاد کنید</a></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($notifications->hasPages()): ?>
            <div class="mt-3"><?php echo e($notifications->links()); ?></div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/notifications/index.blade.php ENDPATH**/ ?>