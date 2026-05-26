<?php $__env->startSection('header', 'مدیریت ماژول‌ها'); ?>

<?php $__env->startSection('content'); ?>
<?php if (isset($component)) { $__componentOriginalf7bde3cb510014c3681173d2166734b0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf7bde3cb510014c3681173d2166734b0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.module-features','data' => ['module' => 'modules']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('module-features'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['module' => 'modules']); ?>
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
    <span class='text-sm text-gray-500'>تعداد سایت‌ها: <?php echo e($sitesCount ?? 0); ?></span>
</p>

<form action="<?php echo e(route('admin.modules.bulk-update')); ?>" method="POST" id="bulk-form">
    <?php echo csrf_field(); ?>
    <div class='bg-white rounded shadow overflow-hidden'>
        <div class='overflow-x-auto'>
            <table class='w-full text-sm'>
                <thead class='bg-gray-50'>
                    <tr>
                        <th class='p-3 text-right w-8'><input type='checkbox' id='select-all'></th>
                        <th class='p-3 text-right'>عنوان</th>
                        <th class='p-3 text-right'>نوع</th>
                        <th class='p-3 text-right'>وضعیت</th>
                        <th class='p-3 text-right'>قیمت (ریال)</th>
                        <th class='p-3 text-right'>سایت‌های فعال</th>
                        <th class='p-3 text-right'>نسخه</th>
                        <th class='p-3 text-right'>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class='border-t hover:bg-gray-50'>
                        <td class='p-3'><input type='checkbox' name='module_ids[]' value="<?php echo e($module->id); ?>" class='module-checkbox'></td>
                        <td class='p-3 font-medium'><?php echo e($module->title ?? $module->slug); ?></td>
                        <td class='p-3'>
                            <?php
                                $typeLabels = ['core' => 'هسته', 'paid' => 'پولی', 'internal' => 'داخلی'];
                                $typeColors = ['core' => 'bg-blue-100 text-blue-700', 'paid' => 'bg-green-100 text-green-700', 'internal' => 'bg-gray-100 text-gray-700'];
                            ?>
                            <span class='px-2 py-0.5 text-xs rounded-full <?php echo e($typeColors[$module->type] ?? 'bg-gray-100'); ?>'>
                                <?php echo e($typeLabels[$module->type] ?? $module->type); ?>

                            </span>
                        </td>
                        <td class='p-3'>
                            <?php if($module->is_active): ?>
                                <span class='text-green-600 text-xs'>فعال</span>
                            <?php else: ?>
                                <span class='text-red-600 text-xs'>غیرفعال</span>
                            <?php endif; ?>
                        </td>
                        <td class='p-3' dir='ltr'>
                            <?php if($module->price_irr): ?>
                                <?php echo e(number_format($module->price_irr)); ?>

                            <?php else: ?>
                                <span class='text-gray-400'>—</span>
                            <?php endif; ?>
                        </td>
                        <td class='p-3'>
                            <?php $cnt = $moduleSiteCounts[$module->id] ?? 0; ?>
                            <?php if($cnt > 0): ?>
                                <span class='text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded'><?php echo e($cnt); ?></span>
                            <?php else: ?>
                                <span class='text-gray-400 text-xs'>0</span>
                            <?php endif; ?>
                        </td>
                        <td class='p-3 text-xs text-gray-500'><?php echo e($module->version ?? '1.0.0'); ?></td>
                        <td class='p-3'>
                            <a href="<?php echo e(route('admin.modules.edit', $module)); ?>" class='text-blue-600 hover:underline text-xs'>ویرایش</a>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan='8' class='p-4 text-center text-gray-500'>هیچ ماژولی یافت نشد.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if(count($modules) > 0): ?>
    <div class='mt-4 flex items-center gap-3'>
        <select name='bulk_action' class='form-control w-auto' required>
            <option value=''>— انتخاب عملیات گروهی —</option>
            <optgroup label='تغییر نوع'>
                <option value='set_type_internal'>تبدیل به داخلی</option>
                <option value='set_type_license'>تبدیل به مبتنی بر لایسنس</option>
                <option value='set_type_paid'>تبدیل به پولی</option>
            </optgroup>
            <optgroup label='مارکت‌پلیس'>
                <option value='marketplace_on'>فعال کردن نمایش در فروشگاه</option>
                <option value='marketplace_off'>غیرفعال کردن نمایش در فروشگاه</option>
            </optgroup>
            <optgroup label='اختصاص رایگان'>
                <option value='free_assignable_on'>فعال کردن اختصاص رایگان</option>
                <option value='free_assignable_off'>غیرفعال کردن اختصاص رایگان</option>
            </optgroup>
            <optgroup label='لایسنس'>
                <option value='license_required_on'>الزام لایسنس دستی</option>
                <option value='license_required_off'>لغو الزام لایسنس دستی</option>
            </optgroup>
        </select>
        <button type='submit' class='btn btn-primary' onclick="return confirm('عملیات گروهی اعمال شود؟')">اعمال</button>
    </div>
    <?php endif; ?>
</form>

<script>
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/modules/index.blade.php ENDPATH**/ ?>