

<?php $__env->startSection('header', 'درخواست‌های تسویه نمایندگان'); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?><div class="alert alert-success mb-4"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if(session('error')): ?><div class="alert alert-danger mb-4"><?php echo e(session('error')); ?></div><?php endif; ?>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">نماینده</th>
                <th class="p-3 text-right">مبلغ (ریال)</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">تاریخ</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-t">
                    <td class="p-3"><?php echo e($req->reseller->company_name ?? $req->reseller->user->mobile ?? '—'); ?></td>
                    <td class="p-3 font-medium"><?php echo e(number_format($req->amount_irr)); ?></td>
                    <td class="p-3"><?php echo e($req->status === 'pending' ? 'در انتظار' : ($req->status === 'approved' ? 'تأیید شده' : $req->status)); ?></td>
                    <td class="p-3"><?php echo e(\App\Helpers\Jalali::format($req->created_at)); ?></td>
                    <td class="p-3">
                        <?php if($req->status !== 'paid' && $req->status !== 'rejected'): ?>
                            <form method="post" action="<?php echo e(route('admin.payout-requests.mark-paid', $req)); ?>" class="inline"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><button type="submit" class="text-green-600 hover:underline">تسویه شد</button></form>
                            <span class="text-gray-300 mx-1">|</span>
                            <form method="post" action="<?php echo e(route('admin.payout-requests.reject', $req)); ?>" class="inline" onsubmit="return confirm('رد شود؟');"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><input type="text" name="admin_note" placeholder="دلیل رد" class="form-control text-sm w-32 inline-block"> <button type="submit" class="text-red-600 hover:underline">رد</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">درخواستی وجود ندارد.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php echo e($requests->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/payout-requests/index.blade.php ENDPATH**/ ?>