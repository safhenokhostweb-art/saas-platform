<?php $__env->startSection('header', 'ویرایش فرم: ' . $form->title); ?>

<?php $__env->startSection('content'); ?>

<p class="mb-4">
    <a href="<?php echo e(route('admin.forms.preview', $form)); ?>" class="btn btn-primary inline-flex items-center gap-1" target="_blank">مشاهده فرم نهایی (پیش‌نمایش برای تست)</a>
    <a href="<?php echo e(route('admin.forms.sites.edit', $form)); ?>" class="btn btn-secondary inline-flex items-center gap-1 mr-2">اتصال به سایت‌ها</a>
</p>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- افزودن فیلد -->
    <div class="bg-white p-6 rounded-xl shadow">
                    <?php if($errors->any()): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                <ul class="list-disc pr-5">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
            <?php endif; ?>
        <h2 class="font-bold mb-4">افزودن فیلد جدید</h2>

        <form method="POST" action="<?php echo e(route('admin.forms.fields.store', $form)); ?>" class="space-y-3">
            <?php echo csrf_field(); ?>

            <input name="label" class="w-full border rounded px-3 py-2" placeholder="عنوان فیلد">
            <input name="name" class="w-full border rounded px-3 py-2" placeholder="name">
            <select name="type" class="w-full border rounded px-3 py-2">
                <option value="text">متن</option>
                <option value="textarea">متن بلند</option>
                <option value="email">ایمیل</option>
                <option value="number">عدد</option>
                <option value="date">تاریخ (شمسی)</option>
                <option value="radio">رادیو</option>
                <option value="checkbox">چک‌باکس</option>
                <option value="select">لیست انتخابی</option>
            </select>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_required" value="1">
                الزامی
            </label>

            <button class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                افزودن فیلد
            </button>
        </form>
    </div>

    <!-- لیست فیلدها -->
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="font-bold mb-4">فیلدهای فرم</h2>

        <ul class="space-y-2">
            <?php $__currentLoopData = $form->fields()->orderBy('position')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="flex justify-between items-center border rounded p-2">
                <span><?php echo e($field->label); ?> (<?php echo e($field->type); ?>)</span>

                <div class="flex gap-2">
                    <form method="POST" action="<?php echo e(route('admin.forms.fields.toggle', $field)); ?>">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>

                        <button class="px-2 py-1 text-xs rounded bg-gray-200">
                            <?php echo e($field->is_active ? 'غیرفعال' : 'فعال'); ?>

                        </button>
                    </form>


                    <form method="POST" action="<?php echo e(route('admin.forms.fields.destroy', $field)); ?>">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('DELETE'); ?>
                        <button class="text-sm px-2 py-1 bg-red-500 text-white rounded">حذف</button>
                    </form>
                </div>
            </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>

</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/forms/edit.blade.php ENDPATH**/ ?>