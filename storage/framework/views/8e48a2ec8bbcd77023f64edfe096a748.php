<?php $__env->startSection('header', 'مدیریت سایت‌ها'); ?>

<?php $__env->startSection('content'); ?>
<?php if (isset($component)) { $__componentOriginalf7bde3cb510014c3681173d2166734b0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf7bde3cb510014c3681173d2166734b0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.module-features','data' => ['module' => 'sites']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('module-features'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['module' => 'sites']); ?>
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
<?php if(session('success')): ?><div class='alert alert-success mb-4'><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if(session('error')): ?><div class='alert alert-danger mb-4'><?php echo e(session('error')); ?></div><?php endif; ?>

<p class='mb-4'>
    <a href="<?php echo e(route('admin.sites.create')); ?>" class='btn btn-primary'>ایجاد سایت جدید</a>
</p>

<?php if(isset($filterCustomer) || isset($filterModule)): ?>
<div class='mb-4 p-3 bg-blue-50 rounded border border-blue-200 text-sm'>
    فیلتر فعال:
    <?php if(isset($filterCustomer)): ?> مشتری: <strong><?php echo e($filterCustomer->name ?? $filterCustomer->company ?? '—'); ?></strong> <?php endif; ?>
    <?php if(isset($filterModule)): ?> ماژول: <strong><?php echo e($filterModule->title ?? $filterModule->slug); ?></strong> <?php endif; ?>
    <a href="<?php echo e(route('admin.sites.index')); ?>" class='mr-2 text-blue-600 hover:underline'>حذف فیلتر</a>
</div>
<?php endif; ?>

<div class='bg-white rounded shadow overflow-hidden'>
    <div class='overflow-x-auto'>
        <table class='w-full text-sm'>
            <thead class='bg-gray-50'>
                <tr>
                    <th class='p-3 text-right'>#</th>
                    <th class='p-3 text-right'>عنوان</th>
                    <th class='p-3 text-right'>مشتری</th>
                    <th class='p-3 text-right'>دامنه</th>
                    <th class='p-3 text-right'>وضعیت</th>
                    <th class='p-3 text-right'>ماژول‌ها</th>
                    <th class='p-3 text-right'>تاریخ ایجاد</th>
                    <th class='p-3 text-right'>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class='border-t hover:bg-gray-50'>
                    <td class='p-3'><?php echo e($site->id); ?></td>
                    <td class='p-3 font-medium'><?php echo e($site->title); ?></td>
                    <td class='p-3'><?php echo e($site->customer?->name ?? $site->customer?->company ?? '—'); ?></td>
                    <td class='p-3' dir='ltr'><?php echo e($site->domain ?? '—'); ?></td>
                    <td class='p-3'>
                        <?php if($site->is_active): ?>
                            <span class='px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700'>فعال</span>
                        <?php else: ?>
                            <span class='px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700'>غیرفعال</span>
                        <?php endif; ?>
                    </td>
                    <td class='p-3'>
                        <?php $activeModules = $site->modules->where('pivot.is_active', true); ?>
                        <?php if($activeModules->count() > 0): ?>
                            <div class='flex flex-wrap gap-1'>
                                <?php $__currentLoopData = $activeModules->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class='text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded'><?php echo e($module->title ?? $module->slug); ?></span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php if($activeModules->count() > 3): ?>
                                    <span class='text-xs text-gray-500'>+<?php echo e($activeModules->count() - 3); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class='text-gray-400'>—</span>
                        <?php endif; ?>
                    </td>
                    <td class='p-3 text-gray-500'><?php echo e($site->created_at->format('Y/m/d')); ?></td>
                    <td class='p-3'>
                        <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class='text-blue-600 hover:underline text-xs'>ویرایش</a>
                        <a href="<?php echo e(route('admin.sites.license.create', $site)); ?>" class='text-indigo-600 hover:underline text-xs mr-2'>صدور لایسنس</a>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan='8' class='p-4 text-center text-gray-500'>هیچ سایتی یافت نشد.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/index.blade.php ENDPATH**/ ?>