

<?php $__env->startSection('header', 'صدور لایسنس — ' . $site->title); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="<?php echo e(route('admin.sites.index')); ?>" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="text-indigo-600 hover:underline"><?php echo e($site->title); ?></a>
        → صدور لایسنس
    </p>

    <?php if($errors->any()): ?>
        <div class="alert alert-danger mb-4" role="alert">
            <ul class="list-disc list-inside text-right mb-0">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm rounded-xl">
        <h5 class="font-bold mb-3">صدور لایسنس جدید</h5>
        <p class="text-sm text-gray-600 mb-4">کلید لایسنس فقط یک‌بار نمایش داده می‌شود. آن را در محل امن ذخیره کرده و به مشتری تحویل دهید.</p>
        <form method="POST" action="<?php echo e(route('admin.sites.license.store', $site)); ?>">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">دامنهٔ مجاز (اختیاری؛ خالی = هر دامنه)</label>
                <input type="text" name="domain" class="form-control" placeholder="example.ir" value="<?php echo e(old('domain', $site->domain)); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">تاریخ انقضا (شمسی، اختیاری)</label>
                <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'expires_at','placeholder' => '۱۴۰۳/۱۲/۲۹','value' => old('expires_at'),'class' => 'form-control']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'expires_at','placeholder' => '۱۴۰۳/۱۲/۲۹','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('expires_at')),'class' => 'form-control']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16)): ?>
<?php $attributes = $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16; ?>
<?php unset($__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale306a0c31e413f34dfe42b38d8fcaf16)): ?>
<?php $component = $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16; ?>
<?php unset($__componentOriginale306a0c31e413f34dfe42b38d8fcaf16); ?>
<?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary">صدور لایسنس</button>
            <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="btn btn-outline-secondary me-2">انصراف</a>
        </form>
    </div>
</div>
<?php $__env->startPush('head'); ?>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
<?php $__env->stopPush(); ?>
<?php $__env->startPush('scripts'); ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({
            format: 'YYYY/MM/DD',
            persianDigit: true,
            initialValueType: 'persian'
        });
    }
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/license-create.blade.php ENDPATH**/ ?>