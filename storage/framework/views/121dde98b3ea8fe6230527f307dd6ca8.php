

<?php $__env->startSection('header', 'قالب‌های اعلان خوش‌آمدگویی'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="<?php echo e(route('admin.access.index')); ?>" class="text-indigo-600 hover:underline">دسترسی و منو</a>
        →
        <a href="<?php echo e(route('admin.sites.notifications.create-bulk')); ?>" class="text-indigo-600 hover:underline">اعلان</a>
        →
        قالب‌های خوش‌آمدگویی
    </p>

    <div class="card p-4 shadow-sm rounded-xl mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="font-bold mb-1">اعلان‌های خودکار برای سایت‌های جدید</h5>
                <p class="text-sm text-gray-600 mb-0">این قالب‌ها به‌صورت خودکار به هر سایت جدید (پس از ایجاد و راه‌اندازی) ارسال می‌شوند.</p>
            </div>
            <a href="<?php echo e(route('admin.notification-templates.create')); ?>" class="btn btn-primary">قالب جدید</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>مخاطب</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($t->title); ?></td>
                            <td><span class="badge <?php echo e(($t->audience ?? 'admin') === 'admin' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'); ?>"><?php echo e(($t->audience ?? 'admin') === 'admin' ? 'ادمین' : 'کاربر نهایی'); ?></span></td>
                            <td><?php echo e($t->is_active ? 'فعال' : 'غیرفعال'); ?></td>
                            <td>
                                <a href="<?php echo e(route('admin.notification-templates.edit', $t)); ?>" class="btn btn-sm btn-outline-primary">ویرایش</a>
                                <form method="POST" action="<?php echo e(route('admin.notification-templates.destroy', $t)); ?>" class="d-inline" onsubmit="return confirm('حذف این قالب؟');">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4" class="text-center text-gray-500 py-4">هنوز قالبی تعریف نشده است. <a href="<?php echo e(route('admin.notification-templates.create')); ?>" class="text-indigo-600">اولین قالب را ایجاد کنید</a></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/notification-templates/index.blade.php ENDPATH**/ ?>