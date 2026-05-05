

<?php $__env->startSection('header', 'محصولات فروشگاه'); ?>

<?php $__env->startSection('content'); ?>
<a href="<?php echo e(route('admin.products.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded">افزودن محصول</a>
<?php if(session('success')): ?><div class="alert alert-success mt-2"><?php echo e(session('success')); ?></div><?php endif; ?>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">SKU / نام</th>
                <th class="p-3 text-right">نوع</th>
                <th class="p-3 text-right">قیمت (ریال)</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="border-t">
                    <td class="p-3"><strong><?php echo e($product->sku); ?></strong><span class="block text-gray-500"><?php echo e($product->name_fa); ?></span></td>
                    <td class="p-3"><?php echo e($product->typeLabel()); ?></td>
                    <td class="p-3"><?php echo e(number_format($product->price_irr)); ?></td>
                    <td class="p-3"><?php echo e($product->is_active ? 'فعال' : 'غیرفعال'); ?></td>
                    <td class="p-3">
                        <a href="<?php echo e(route('admin.products.edit', $product)); ?>" class="text-indigo-600 hover:underline">ویرایش</a>
                        <form method="POST" action="<?php echo e(route('admin.products.destroy', $product)); ?>" class="inline"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-red-600" onclick="return confirm('حذف شود؟');">حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php echo $__env->make('admin.products.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/products/index.blade.php ENDPATH**/ ?>