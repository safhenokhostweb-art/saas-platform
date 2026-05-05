<?php $__env->startSection('header', 'لاگ فعالیت'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.activity-log.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php if(session('error')): ?>
<div class="alert alert-danger mb-6"><?php echo e(session('error')); ?></div>
<?php endif; ?>

<div class="mb-6 flex flex-wrap items-end gap-3">
    <form method="GET" action="<?php echo e(route('admin.activity-log.index')); ?>" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="form-label text-xs">کاربر</label>
            <select name="user_id" class="form-select w-40">
                <option value="">همه</option>
                <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($u->id); ?>" <?php echo e(request('user_id') == $u->id ? 'selected' : ''); ?>><?php echo e($u->mobile); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div>
            <label class="form-label text-xs">نوع عملیات</label>
            <select name="action" class="form-select w-48">
                <option value="">همه</option>
                <?php $__currentLoopData = $actionOptions ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($key); ?>" <?php echo e(request('action') === $key ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div>
            <label class="form-label text-xs">از تاریخ (شمسی)</label>
            <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'from','placeholder' => '۱۴۰۳/۰۱/۰۱','value' => request('from'),'class' => 'form-control w-44']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'from','placeholder' => '۱۴۰۳/۰۱/۰۱','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request('from')),'class' => 'form-control w-44']); ?>
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
        <div>
            <label class="form-label text-xs">تا تاریخ (شمسی)</label>
            <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'to','placeholder' => '۱۴۰۳/۱۲/۲۹','value' => request('to'),'class' => 'form-control w-44']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'to','placeholder' => '۱۴۰۳/۱۲/۲۹','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request('to')),'class' => 'form-control w-44']); ?>
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
        <button type="submit" class="btn btn-primary">فیلتر</button>
        <a href="<?php echo e(route('admin.activity-log.index')); ?>" class="btn btn-secondary">پاک کردن فیلتر</a>
    </form>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="p-3 font-bold">#</th>
                    <th class="p-3 font-bold">زمان</th>
                    <th class="p-3 font-bold">کاربر</th>
                    <th class="p-3 font-bold">عملیات</th>
                    <th class="p-3 font-bold">توضیح</th>
                    <th class="p-3 font-bold">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="p-3"><?php echo e($log->id); ?></td>
                    <td class="p-3 text-sm text-gray-600" title="<?php echo e(\App\Helpers\Jalali::formatFa($log->created_at, 'Y/m/d H:i:s')); ?>"><?php echo e(\App\Helpers\Jalali::formatFa($log->created_at, 'Y/m/d H:i')); ?></td>
                    <td class="p-3">
                        <?php if($log->user): ?>
                        <span class="font-medium"><?php echo e($log->user->mobile); ?></span>
                        <?php if($log->user->profile && $log->user->profile->full_name): ?>
                        <span class="text-gray-500 text-xs">(<?php echo e($log->user->profile->full_name); ?>)</span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-sm"><?php echo e(\App\Models\ActivityLog::actionLabel($log->action)); ?></td>
                    <td class="p-3 text-sm"><?php echo e($log->description ?? '—'); ?></td>
                    <td class="p-3 text-xs text-gray-500"><?php echo e($log->ip_address ?? '—'); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6" class="p-6 text-center text-gray-500">رکوردی یافت نشد.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if(isset($logs) && method_exists($logs, 'links')): ?>
    <div class="p-3 border-t border-gray-200">
        <?php echo e($logs->links()); ?>

    </div>
    <?php endif; ?>
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

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/activity-log/index.blade.php ENDPATH**/ ?>