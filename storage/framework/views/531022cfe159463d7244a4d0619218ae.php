

<?php $__env->startSection('header', 'توزیع بروزرسانی — ' . $site->title); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="<?php echo e(route('admin.sites.index')); ?>" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="text-indigo-600 hover:underline"><?php echo e($site->title); ?></a>
        → توزیع بروزرسانی
    </p>

    <?php if(session('success')): ?>
        <div class="alert alert-success mb-4"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm rounded-xl mb-4">
        <h5 class="font-bold mb-3">ثبت توزیع بروزرسانی جدید</h5>
        <form method="POST" action="<?php echo e(route('admin.sites.updates.store', $site)); ?>">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">نسخه / برچسب</label>
                <input type="text" name="version_label" class="form-control" placeholder="مثال: 1.2.0 یا ۱۴۰۳/۰۷" value="<?php echo e(old('version_label')); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">تاریخ توزیع (شمسی)</label>
                <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'distributed_at','value' => old('distributed_at', \App\Helpers\Jalali::format(now())),'placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'distributed_at','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('distributed_at', \App\Helpers\Jalali::format(now()))),'placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control']); ?>
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
            <div class="mb-3">
                <label class="form-label">یادداشت (اختیاری)</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="توضیح کوتاه"><?php echo e(old('notes')); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">ثبت توزیع</button>
        </form>
    </div>

    <h5 class="font-bold mb-2">تاریخچه توزیع بروزرسانی</h5>
    <div class="bg-white rounded shadow overflow-hidden">
        <?php if($site->siteUpdates->isEmpty()): ?>
            <p class="p-4 text-gray-500">هنوز بروزرسانی‌ای ثبت نشده است.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-right">نسخه</th>
                        <th class="p-3 text-right">تاریخ توزیع</th>
                        <th class="p-3 text-right">یادداشت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $site->siteUpdates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $update): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="border-t">
                            <td class="p-3 font-medium"><?php echo e($update->version_label); ?></td>
                            <td class="p-3"><?php echo e($update->distributed_at ? \App\Helpers\Jalali::format($update->distributed_at) : '—'); ?></td>
                            <td class="p-3 text-gray-600"><?php echo e($update->notes ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php endif; ?>
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
    if (document.querySelector('.persian-date-input') && typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({ format: 'YYYY/MM/DD', persianDigit: true, initialValueType: 'persian' });
    }
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/updates.blade.php ENDPATH**/ ?>