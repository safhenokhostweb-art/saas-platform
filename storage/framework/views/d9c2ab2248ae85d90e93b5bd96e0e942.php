

<?php $__env->startSection('header', 'تیکت: ' . Str::limit($supportTicket->subject, 50)); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?><div class="alert alert-success mb-4"><?php echo e(session('success')); ?></div><?php endif; ?>

<div class="card p-4 max-w-2xl">
    <p><strong>سایت:</strong> <?php echo e($supportTicket->site->title ?? '—'); ?></p>
    <p><strong>ایمیل:</strong> <?php echo e($supportTicket->customer_email); ?></p>
    <p><strong>نام:</strong> <?php echo e($supportTicket->customer_name ?? '—'); ?></p>
    <p><strong>وضعیت:</strong> <?php echo e($supportTicket->status); ?></p>
    <p><strong>تاریخ:</strong> <?php echo e(\App\Helpers\Jalali::format($supportTicket->created_at)); ?></p>
    <hr class="my-4">
    <h3 class="font-bold mb-2">پیام</h3>
    <p class="whitespace-pre-wrap"><?php echo e($supportTicket->message); ?></p>
    <hr class="my-4">
    <form method="post" action="<?php echo e(route('admin.support-tickets.update-status', $supportTicket)); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PATCH'); ?>
        <label>تغییر وضعیت:</label>
        <select name="status" class="form-control w-auto inline-block">
            <option value="open" <?php echo e($supportTicket->status === 'open' ? 'selected' : ''); ?>>باز</option>
            <option value="in_progress" <?php echo e($supportTicket->status === 'in_progress' ? 'selected' : ''); ?>>در حال بررسی</option>
            <option value="closed" <?php echo e($supportTicket->status === 'closed' ? 'selected' : ''); ?>>بسته</option>
        </select>
        <button type="submit" class="btn btn-primary">ذخیره</button>
    </form>
</div>
<a href="<?php echo e(route('admin.support-tickets.index')); ?>" class="btn btn-secondary mt-4">بازگشت</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/support-tickets/show.blade.php ENDPATH**/ ?>