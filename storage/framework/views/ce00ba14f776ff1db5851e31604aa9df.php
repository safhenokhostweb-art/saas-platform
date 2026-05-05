<?php $__env->startSection('header', 'صفحات ثابت و راهنما'); ?>

<?php $__env->startSection('content'); ?>

<?php
    $menuPosition = \App\Models\Setting::get('page_menu_position', 'top');
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-gray-600 text-sm">موقعیت نمایش فهرست صفحات در سایت:</span>
        <form method="POST" action="<?php echo e(route('admin.pages.menu-position')); ?>" class="inline-flex gap-2">
            <?php echo csrf_field(); ?>
            <select name="position" onchange="this.form.submit()" class="form-select w-48">
                <?php $__currentLoopData = \App\Models\Page::menuPositions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($key); ?>" <?php echo e($menuPosition === $key ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </form>
    </div>
    <a href="<?php echo e(route('admin.pages.create')); ?>" class="btn btn-primary">صفحه جدید</a>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <p class="text-gray-600 text-sm p-3 border-b bg-gray-50 border-gray-100">
        <span class="inline-flex items-center gap-1 ml-1" aria-hidden="true">⋮⋮</span>
        <strong>درگ اند دراپ:</strong> برای تغییر ترتیب نمایش صفحات در فهرست، روی ردیف را کشیده و رها کنید.
    </p>
    <table class="w-full text-right border-collapse">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
                <th class="p-3 w-10"></th>
                <th class="p-3 font-bold">#</th>
                <th class="p-3 font-bold">عنوان</th>
                <th class="p-3 font-bold">اسلاگ</th>
                <th class="p-3 font-bold">آیکن</th>
                <th class="p-3 font-bold">موقعیت منو</th>
                <th class="p-3 font-bold">ترتیب</th>
                <th class="p-3 font-bold">وضعیت</th>
                <th class="p-3 font-bold">عملیات</th>
            </tr>
        </thead>
        <tbody id="pages-sortable-tbody">
            <?php $__empty_1 = true; $__currentLoopData = $pages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50 pages-sortable-row" data-page-id="<?php echo e($p->id); ?>">
                    <td class="p-3 cursor-grab active:cursor-grabbing text-gray-400 select-none" title="کشیدن برای تغییر ترتیب"><span aria-hidden="true">⋮⋮</span></td>
                    <td class="p-3"><?php echo e($p->id); ?></td>
                    <td class="p-3 font-medium"><?php echo e($p->title); ?></td>
                    <td class="p-3 text-sm text-gray-600"><?php echo e($p->slug); ?></td>
                    <td class="p-3">
                        <?php if($p->icon_url): ?>
                            <img src="<?php echo e($p->icon_url); ?>" alt="" class="w-6 h-6 object-contain">
                        <?php else: ?>
                            <?php echo $__env->make('admin.pages.partials.icon-svg', ['name' => $p->icon_default ?? 'document', 'class' => 'w-6 h-6 inline-block text-gray-500'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-sm"><?php echo e(\App\Models\Page::menuPositions()[$p->menu_position] ?? $p->menu_position); ?></td>
                    <td class="p-3"><?php echo e($p->menu_order); ?></td>
                    <td class="p-3">
                        <?php if($p->is_active): ?>
                            <span class="text-green-600 text-sm">فعال</span>
                        <?php else: ?>
                            <span class="text-gray-400 text-sm">غیرفعال</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <a href="<?php echo e(route('page.show', $p->slug)); ?>" target="_blank" class="text-blue-600 text-sm ml-2">مشاهده</a>
                        <a href="<?php echo e(route('admin.pages.edit', $p)); ?>" class="text-blue-600 text-sm ml-2">ویرایش</a>
                        <form action="<?php echo e(route('admin.pages.destroy', $p)); ?>" method="POST" class="inline" onsubmit="return confirm('حذف شود؟');">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-red-600 text-sm">حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="9" class="p-6 text-center text-gray-500">صفحه‌ای وجود ندارد. <a href="<?php echo e(route('admin.pages.create')); ?>" class="text-blue-600">اولین صفحه را بسازید</a>.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php echo $__env->make('admin.pages.partials.pages-index-guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbody = document.getElementById('pages-sortable-tbody');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            animation: 150,
            handle: '.cursor-grab',
            onEnd: function() {
                var order = [];
                tbody.querySelectorAll('.pages-sortable-row[data-page-id]').forEach(function(tr) {
                    order.push(tr.getAttribute('data-page-id'));
                });
                if (order.length === 0) return;
                fetch('<?php echo e(route("admin.pages.reorder")); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ order: order })
                }).then(function() { window.location.reload(); });
            }
        });
    }
});
</script>
<?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/pages/index.blade.php ENDPATH**/ ?>