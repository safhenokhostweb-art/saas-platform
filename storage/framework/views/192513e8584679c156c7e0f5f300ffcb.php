

<?php $__env->startSection('header', 'مدیریت کاربران پلتفرم'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.users.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<?php if (isset($component)) { $__componentOriginalf7bde3cb510014c3681173d2166734b0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf7bde3cb510014c3681173d2166734b0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.module-features','data' => ['module' => 'users']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('module-features'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['module' => 'users']); ?>
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

<div x-data="usersIndex()" x-init="init()" class="space-y-6">

    
    <?php if(isset($stats) && is_array($stats)): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-indigo-600"><?php echo e(number_format($stats['total'] ?? 0)); ?></div>
            <div class="text-xs text-gray-500 mt-1">کل کاربران</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-green-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-green-600"><?php echo e(number_format($stats['active'] ?? 0)); ?></div>
            <div class="text-xs text-gray-500 mt-1">فعال</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-red-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-red-600"><?php echo e(number_format($stats['inactive'] ?? 0)); ?></div>
            <div class="text-xs text-gray-500 mt-1">غیرفعال</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-amber-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-amber-600"><?php echo e(number_format($stats['locked'] ?? 0)); ?></div>
            <div class="text-xs text-gray-500 mt-1">قفل شده</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-blue-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-blue-600"><?php echo e(number_format($stats['admins'] ?? 0)); ?></div>

            <div class="text-xs text-gray-500 mt-1">ادمین</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-purple-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-purple-600"><?php echo e(number_format($stats['two_factor_enabled'] ?? 0)); ?></div>
            <div class="text-xs text-gray-500 mt-1">2FA فعال</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-teal-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-teal-600"><?php echo e(number_format($stats['new_today'] ?? 0)); ?></div>
            <div class="text-xs text-gray-500 mt-1">جدید امروز</div>
        </div>
    </div>
    <?php endif; ?>


    <?php echo $__env->make('admin.users._toast', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <?php echo $__env->make('admin.users._table', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <?php echo $__env->make('admin.users._bulk_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <?php echo $__env->make('admin.users._delete_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <form id="bulk-form" method="POST" class="hidden"
          data-routes='<?php echo e(json_encode(["activate" => route("admin.users.bulk-toggle"), "deactivate" => route("admin.users.bulk-toggle"), "delete" => route("admin.users.bulk-delete"), "restore" => route("admin.users.bulk-restore")])); ?>'>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="user_ids" id="bulk-ids">
        <input type="hidden" name="action" id="bulk-action-hidden">
    </form>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('usersIndex', usersIndex);
    });
</script>
<?php $__env->stopPush(); ?>

<script>
function sortByClick(field) {
    var url = new URL(window.location.href);
    var dir = url.searchParams.get('sort') === field && url.searchParams.get('direction') === 'asc' ? 'desc' : 'asc';
    url.searchParams.set('sort', field);
    url.searchParams.set('direction', dir);
    window.location.href = url.toString();
}
</script>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/users/index.blade.php ENDPATH**/ ?>