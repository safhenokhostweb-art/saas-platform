<div class="mb-3">
    <label class="form-label">کاربر (موبایل)</label>
    <select name="user_id" class="form-select" required>
        <option value="">— انتخاب کنید —</option>
        <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($u->id); ?>" <?php echo e(old('user_id', $reseller->user_id ?? null) == $u->id ? 'selected' : ''); ?>>
                <?php echo e($u->mobile); ?> <?php if($u->profile && $u->profile->full_name): ?> — <?php echo e($u->profile->full_name); ?> <?php endif; ?>
            </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <small class="text-gray-500">فقط کاربرانی که هنوز نماینده نیستند نمایش داده می‌شوند.</small>
</div>
<div class="mb-3">
    <label class="form-label">نام شرکت (اختیاری)</label>
    <input type="text" name="company_name" class="form-control" value="<?php echo e(old('company_name', $reseller->company_name ?? '')); ?>" placeholder="مثال: شرکت فروش نرم‌افزار">
</div>
<div class="mb-3">
    <label class="form-label">درصد کمیسیون</label>
    <input type="number" name="commission_rate" class="form-control" value="<?php echo e(old('commission_rate', $reseller->commission_rate ?? 0)); ?>" min="0" max="100" step="0.01" required>
    <small class="text-gray-500">درصد کمیسیون از فروش مشتریان این نماینده.</small>
</div>
<div class="mb-3">
    <label class="form-label">تخفیف عمده (درصد)</label>
    <input type="number" name="wholesale_discount_percent" class="form-control" value="<?php echo e(old('wholesale_discount_percent', $reseller->wholesale_discount_percent ?? 0)); ?>" min="0" max="100" step="0.01" placeholder="0">
    <small class="text-gray-500">وقتی این نماینده از فروشگاه خرید می‌کند، این درصد تخفیف اعمال می‌شود.</small>
</div>
<div class="mb-3">
    <label class="form-label">اطلاعات بانکی (اختیاری)</label>
    <textarea name="bank_info" class="form-control" rows="2" placeholder="شماره شبا، نام بانک، ..."><?php echo e(old('bank_info', $reseller->bank_info ?? '')); ?></textarea>
</div>
<div class="mb-3">
    <label class="form-label">White-label</label>
    <input type="checkbox" name="is_white_label" value="1" <?php echo e(old('is_white_label', $reseller->is_white_label ?? false) ? 'checked' : ''); ?>>
    <span class="text-gray-500 text-sm">فعال‌سازی برند سفارشی برای این نماینده</span>
</div>
<div class="mb-3 border-t pt-3 mt-3">
    <span class="text-gray-600 font-medium block mb-2">تنظیمات White-label (اختیاری)</span>
    <div class="mb-2">
        <label class="form-label">نام برند</label>
        <input type="text" name="brand_name" class="form-control" value="<?php echo e(old('brand_name', $reseller->brand_name ?? '')); ?>" placeholder="نام نمایشی در نوار بالا">
    </div>
    <div class="mb-2">
        <label class="form-label">مسیر لوگو (مثلاً reseller/logo.png در storage)</label>
        <input type="text" name="logo_path" class="form-control" value="<?php echo e(old('logo_path', $reseller->logo_path ?? '')); ?>" placeholder="reseller/logo.png">
    </div>
    <div class="mb-2">
        <label class="form-label">دامنهٔ اختصاصی</label>
        <input type="text" name="custom_domain" class="form-control" value="<?php echo e(old('custom_domain', $reseller->custom_domain ?? '')); ?>" placeholder="shop.reseller.ir">
        <small class="text-gray-500">وقتی کاربر از این دامنه وارد شود، برند شما نمایش داده می‌شود.</small>
    </div>
    <div class="mb-2">
        <label class="form-label">زیردامنه (مثلاً reseller1 برای reseller1.platform.com)</label>
        <input type="text" name="subdomain" class="form-control" value="<?php echo e(old('subdomain', $reseller->subdomain ?? '')); ?>" placeholder="reseller1">
    </div>
</div>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/resellers/_form.blade.php ENDPATH**/ ?>