<?php $__env->startSection('header','فرم‌ها'); ?>

<?php $__env->startSection('content'); ?>
<a href="<?php echo e(route('admin.forms.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded">
    ساخت فرم
</a>

<?php if (isset($component)) { $__componentOriginalf7bde3cb510014c3681173d2166734b0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf7bde3cb510014c3681173d2166734b0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.module-features','data' => ['module' => 'forms']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('module-features'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['module' => 'forms']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf7bde3cb510014c3681173d2166734b0)): ?>
<?php $attributes = $__attributesOriginalf7bde3cb510014c3681173d2166734b0; ?>
<?php unset($__attributesOriginalf7bde3cb510014c3681173d2166734b0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf7bde3cb510014c3681173d2166734b0)): ?>
<?php $component = $__componentOriginalf7bde3cb510014c3681173d2166734b0; ?>
<?php unset($__componentOriginalf7bde3cb510014c3681173d2166734b0); ?>
<?php endif; ?>

<div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
    <?php $__currentLoopData = $forms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $form): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="bg-white p-4 rounded shadow">
            <div class="font-bold"><?php echo e($form->title); ?></div>
            <a href="<?php echo e(route('admin.forms.edit',$form)); ?>" class="text-indigo-600 text-sm">مدیریت فیلدها</a>
            <span class="mx-1">|</span>
            <a href="<?php echo e(route('admin.forms.preview', $form)); ?>" class="text-indigo-600 text-sm" target="_blank">مشاهده فرم نهایی</a>
            <span class="mx-1">|</span>
            <a href="<?php echo e(route('admin.forms.sites.edit', $form)); ?>" class="text-indigo-600 text-sm">اتصال به سایت‌ها</a>
            <form method="POST" action="<?php echo e(route('admin.forms.destroy', $form)); ?>"
      onsubmit="return confirm('حذف کامل فرم؟');">
    <?php echo csrf_field(); ?>
    <?php echo method_field('DELETE'); ?>

    <button class="bg-red-600 text-white px-3 py-1 rounded text-sm">
        حذف فرم
    </button>
</form>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<?php echo $__env->make('admin.forms.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/forms/index.blade.php ENDPATH**/ ?>