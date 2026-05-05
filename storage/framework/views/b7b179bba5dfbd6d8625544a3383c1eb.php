<div class="mb-3 text-end">
    <label class="form-label">نام *</label>
    <input type="text" name="name" class="form-control"
           placeholder="نام مشتری را وارد کنید"
           value="<?php echo e(old('name', $customer->name ?? '')); ?>" required>
</div>

<div class="mb-3 text-end">
    <label class="form-label">موبایل *</label>
    <input type="text" name="mobile" class="form-control"
           placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹"
           value="<?php echo e(old('mobile', $customer->mobile ?? '')); ?>" required dir="rtl">
</div>

<div class="mb-3 text-end">
    <label class="form-label">ایمیل</label>
    <input type="email" name="email" class="form-control"
           placeholder="example@email.com"
           value="<?php echo e(old('email', $customer->email ?? '')); ?>">
</div>

<div class="mb-3 text-end">
    <label class="form-label">نام شرکت</label>
    <input type="text" name="company_name" class="form-control"
           placeholder="نام شرکت یا واحد"
           value="<?php echo e(old('company_name', $customer->company_name ?? '')); ?>">
</div>

<?php if(!empty($hasUserColumn)): ?>
<div class="mb-3 text-end">
    <label class="form-label">تخصیص به کاربر سایت (موبایل)</label>
    <select name="user_id" class="form-select">
        <option value="">— بدون تخصیص —</option>
        <?php $__currentLoopData = $users ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($u->id); ?>" <?php echo e(old('user_id', $customer->user_id ?? '') == $u->id ? 'selected' : ''); ?>>
                <?php echo e($u->mobile); ?> <?php if($u->profile && $u->profile->full_name): ?> (<?php echo e($u->profile->full_name); ?>) <?php endif; ?>
            </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <p class="text-gray-500 text-xs mt-1">کاربری که با این موبایل در سایت ثبت‌نام کرده را انتخاب کنید تا به این مشتری تخصیص داده شود.</p>
</div>
<?php else: ?>
<div class="mb-3 text-end p-3 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800">
    برای فعال‌سازی «تخصیص به کاربر سایت» در ترمینال اجرا کنید: <code class="bg-amber-100 px-1 rounded">php artisan migrate</code>
</div>
<?php endif; ?>

<?php if(!empty($hasResellerColumn) && isset($resellers)): ?>
<div class="mb-3 text-end">
    <label class="form-label">نماینده (Reseller)</label>
    <select name="reseller_id" class="form-select">
        <option value="">— بدون نماینده —</option>
        <?php $__currentLoopData = $resellers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($r->id); ?>" <?php echo e(old('reseller_id', $customer->reseller_id ?? '') == $r->id ? 'selected' : ''); ?>>
                <?php echo e($r->company_name ?: $r->user->mobile ?? 'Reseller #'.$r->id); ?>

            </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
</div>
<?php endif; ?>

<div class="mb-3 text-end">
    <label class="form-label">یادداشت</label>
    <textarea name="notes" class="form-control" rows="3"
              placeholder="یادداشت‌های مربوط به مشتری"><?php echo e(old('notes', $customer->notes ?? '')); ?></textarea>
</div>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/customers/form.blade.php ENDPATH**/ ?>