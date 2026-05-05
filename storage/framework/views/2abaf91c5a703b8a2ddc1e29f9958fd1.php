

<?php $__env->startSection('header', 'ساختار تم (Theme Builder)'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex flex-wrap gap-2 items-center justify-between">
    <div class="flex flex-wrap gap-2 items-center">
        <a href="<?php echo e(route('admin.theme-builder.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            ایجاد تم جدید
        </a>
        <a href="<?php echo e(route('admin.theme-builder.create', ['site_id' => 0])); ?>" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded hover:bg-slate-50">
            قالب پلتفرم
        </a>
        <form action="<?php echo e(route('admin.theme-builder.import')); ?>" method="post" enctype="multipart/form-data" class="inline-flex items-center gap-2">
            <?php echo csrf_field(); ?>
            <input type="file" name="file" accept=".json" class="form-control w-auto max-w-[200px]" required>
            <input type="hidden" name="site_id" value="<?php echo e($siteId ?? 0); ?>">
            <button type="submit" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded hover:bg-slate-50">وارد کردن تم</button>
        </form>
    </div>
    <form method="get" class="flex items-center gap-2">
        <label class="text-sm text-gray-600">سایت:</label>
        <select name="site_id" onchange="this.form.submit()" class="form-select w-48">
            <option value="0" <?php echo e(($siteId ?? 0) == 0 ? 'selected' : ''); ?>>همه / قالب‌های پلتفرم</option>
            <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s->id); ?>" <?php echo e(($siteId ?? 0) == $s->id ? 'selected' : ''); ?>><?php echo e($s->title); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </form>
</div>

<?php if(session('success')): ?>
    <div class="mt-4 alert alert-success"><?php echo e(session('success')); ?></div>
<?php endif; ?>
<?php if(session('error')): ?>
    <div class="mt-4 alert alert-danger"><?php echo e(session('error')); ?></div>
<?php endif; ?>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">نام / شناسه</th>
                <th class="p-3 text-right">سایت</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">آخرین تغییر</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $themes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $theme): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3">
                        <span class="font-medium"><?php echo e($theme->name); ?></span>
                        <span class="text-gray-500 block text-xs"><?php echo e($theme->slug); ?></span>
                    </td>
                    <td class="p-3">
                        <?php if($theme->site_id): ?>
                            <a href="<?php echo e(route('admin.sites.edit', $theme->site)); ?>" class="text-indigo-600"><?php echo e($theme->site->title ?? '—'); ?></a>
                        <?php else: ?>
                            <span class="text-gray-400">قالب پلتفرم</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <?php if($theme->isPublished()): ?>
                            <span class="text-green-600 font-medium">منتشر شده</span>
                        <?php else: ?>
                            <span class="text-amber-600">پیش‌نویس</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-gray-600"><?php echo e($theme->updated_at?->format('Y/m/d H:i')); ?></td>
                    <td class="p-3">
                        <a href="<?php echo e(route('admin.theme-builder.edit', $theme)); ?>" class="text-indigo-600 hover:underline ml-2">ویرایش</a>
                        <a href="<?php echo e(route('admin.theme-builder.export', $theme)); ?>" class="text-indigo-600 hover:underline ml-2">خروجی JSON</a>
                        <a href="<?php echo e(route('admin.theme-builder.clone-form', $theme)); ?>" class="text-indigo-600 hover:underline ml-2">کپی</a>
                        <form action="<?php echo e(route('admin.theme-builder.destroy', $theme)); ?>" method="post" class="inline" onsubmit="return confirm('حذف این تم؟');">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-red-600 hover:underline ml-2">حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="5" class="p-6 text-center text-gray-500">تمی یافت نشد. یک تم جدید ایجاد کنید یا از فایل JSON وارد کنید.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="p-3 border-t"><?php echo e($themes->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/theme-builder/index.blade.php ENDPATH**/ ?>