<div class="mb-3">
    <label class="form-label">SKU</label>
    <input type="text" name="sku" class="form-control" value="<?php echo e(old('sku', $product->sku ?? '')); ?>" required>
</div>
<div class="mb-3">
    <label class="form-label">نام (فارسی)</label>
    <input type="text" name="name_fa" class="form-control" value="<?php echo e(old('name_fa', $product->name_fa ?? '')); ?>" required>
</div>
<div class="mb-3">
    <label class="form-label">نام (انگلیسی)</label>
    <input type="text" name="name_en" class="form-control" value="<?php echo e(old('name_en', $product->name_en ?? '')); ?>">
</div>
<div class="mb-3">
    <label class="form-label">نوع</label>
    <select name="type" class="form-select" required>
        <option value="license_template" <?php echo e(old('type', isset($product) ? $product->type : ($preselectedModuleId ? 'module_addon' : '')) === 'license_template' ? 'selected' : ''); ?>>لایسنس قالب سایت</option>
        <option value="module_addon" <?php echo e(old('type', isset($product) ? $product->type : ($preselectedModuleId ? 'module_addon' : '')) === 'module_addon' ? 'selected' : ''); ?>>ماژول افزودنی</option>
        <option value="support_plan" <?php echo e(old('type', isset($product) ? $product->type : ($preselectedModuleId ? 'module_addon' : '')) === 'support_plan' ? 'selected' : ''); ?>>پلن پشتیبانی</option>
    </select>
</div>
<div class="mb-3">
    <label class="form-label">قیمت (ریال)</label>
    <input type="number" name="price_irr" class="form-control" value="<?php echo e(old('price_irr', $product->price_irr ?? 0)); ?>" min="0" required>
</div>
<div class="mb-3">
    <label class="form-label">ماژول (برای نوع ماژول افزودنی)</label>
    <select name="module_id" class="form-select">
        <option value="">—</option>
        <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($m->id); ?>" <?php echo e(old('module_id', isset($product) ? $product->module_id : ($preselectedModuleId ?? '')) == $m->id ? 'selected' : ''); ?>><?php echo e($m->title); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
</div>
<div class="mb-3">
    <label class="form-label">پلن پشتیبانی (برای نوع پشتیبانی)</label>
    <select name="support_plan_id" class="form-select">
        <option value="">—</option>
        <?php $__currentLoopData = $supportPlans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($sp->id); ?>" <?php echo e(old('support_plan_id', $product->support_plan_id ?? '') == $sp->id ? 'selected' : ''); ?>><?php echo e($sp->title); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
</div>
<div class="mb-3">
    <label class="form-label">یک‌بار خرید</label>
    <input type="checkbox" name="is_one_time" value="1" <?php echo e(old('is_one_time', $product->is_one_time ?? true) ? 'checked' : ''); ?>>
</div>
<div class="mb-3">
    <label class="form-label">فعال</label>
    <input type="checkbox" name="is_active" value="1" <?php echo e(old('is_active', isset($product) ? $product->is_active : true) ? 'checked' : ''); ?>>
</div>
<div class="mb-3">
    <label class="form-label">توضیح</label>
    <textarea name="description" class="form-control" rows="2"><?php echo e(old('description', $product->description ?? '')); ?></textarea>
</div>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/products/_form.blade.php ENDPATH**/ ?>