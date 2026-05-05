

<?php $__env->startSection('header', 'تیکت‌های پشتیبانی'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.support-tickets.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php if(session('success')): ?><div class="alert alert-success mb-4"><?php echo e(session('success')); ?></div><?php endif; ?>

<p class="mb-4"><a href="<?php echo e(route('admin.support-tickets.create')); ?>" class="btn btn-primary">ثبت تیکت جدید</a></p>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">شماره</th>
                <th class="p-3 text-right">سایت</th>
                <th class="p-3 text-right">موضوع</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">تاریخ</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-t <?php echo e(app(\App\Services\SupportSlaService::class)->hasAnyBreach($t) ? 'bg-red-50' : ''); ?>">
                    <td class="p-3"><?php echo e($t->ticket_number ?? '—'); ?></td>
                    <td class="p-3"><?php echo e($t->site->title ?? '—'); ?></td>
                    <td class="p-3"><?php echo e(Str::limit($t->subject, 40)); ?></td>
                    <td class="p-3"><?php echo e($t->status === 'open' ? 'باز' : ($t->status === 'closed' ? 'بسته' : 'در حال بررسی')); ?></td>
                    <td class="p-3"><?php echo e(\App\Helpers\Jalali::format($t->created_at)); ?></td>
                    <td class="p-3"><a href="<?php echo e(route('admin.support-tickets.show', $t)); ?>" class="text-blue-600 hover:underline">مشاهده</a></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="6" class="p-4 text-center text-gray-500">تیکتی وجود ندارد.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php echo e($tickets->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/support-tickets/index.blade.php ENDPATH**/ ?>