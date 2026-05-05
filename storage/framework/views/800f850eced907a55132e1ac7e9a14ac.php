

<?php $__env->startSection('header', 'منوهای سایت: ' . $site->title); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.menus.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<div class="max-w-4xl">
    <div class="flex justify-between items-center mb-4">
        <a href="<?php echo e(route('admin.sites.index')); ?>" class="text-indigo-600 hover:underline">← بازگشت به سایت‌ها</a>
        <a href="<?php echo e(route('admin.sites.menus.create', $site)); ?>" class="btn btn-primary">افزودن منو</a>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success mb-4"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <?php if($menus->isEmpty()): ?>
        <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
            <p>هنوز منویی ایجاد نشده است.</p>
            <a href="<?php echo e(route('admin.sites.menus.create', $site)); ?>" class="btn btn-primary mt-4">افزودن اولین منو</a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-right">نام</th>
                        <th class="p-3 text-right">موقعیت</th>
                        <th class="p-3 text-right">تعداد آیتم</th>
                        <th class="p-3 text-right">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $menus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $menu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-3 font-medium"><?php echo e($menu->name); ?></td>
                            <td class="p-3"><?php echo e(\App\Models\Menu::locations()[$menu->location] ?? $menu->location); ?></td>
                            <td class="p-3"><?php echo e($menu->all_items_count ?? 0); ?></td>
                            <td class="p-3">
                                <a href="<?php echo e(route('admin.sites.menus.edit', [$site, $menu])); ?>" class="text-indigo-600 hover:underline">ویرایش</a>
                                <form method="POST" action="<?php echo e(route('admin.sites.menus.destroy', [$site, $menu])); ?>" class="inline" onsubmit="return confirm('حذف این منو؟');">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="text-red-600 hover:underline mr-2">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/menus/index.blade.php ENDPATH**/ ?>