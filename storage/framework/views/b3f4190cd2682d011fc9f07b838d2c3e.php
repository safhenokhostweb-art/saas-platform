<?php $__env->startSection('header', 'عنوان صفحه'); ?>


<?php $__env->startSection('content'); ?>
<div class="max-w-2xl mx-auto py-4">

    <h5 class="font-bold mb-3 text-lg">ویرایش مشتری</h5>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if($errors->any()): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                <ul class="list-disc pr-5">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
            <?php endif; ?>
            <form method="POST" action="<?php echo e(route('admin.customers.update', $customer)); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <?php echo $__env->make('admin.customers.form', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                <button class="btn btn-primary w-full mt-3">ذخیره تغییرات</button>
            </form>

        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/customers/edit.blade.php ENDPATH**/ ?>