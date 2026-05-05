<?php $t = $template ?? null; ?>
<div class="mb-3">
    <label class="form-label">عنوان *</label>
    <input type="text" name="title" class="form-control" value="<?php echo e(old('title', $t?->title)); ?>" required placeholder="مثلاً خوش آمدید به پلتفرم">
</div>
<div class="mb-3">
    <label class="form-label">متن</label>
    <textarea name="body" class="form-control" rows="4" placeholder="متن اعلان خوش‌آمدگویی..."><?php echo e(old('body', $t?->body)); ?></textarea>
</div>
<div class="mb-3">
    <label class="form-label">نوع</label>
    <select name="type" class="form-select">
        <option value="info" <?php echo e(old('type', $t?->type ?? 'info') === 'info' ? 'selected' : ''); ?>>اطلاع</option>
        <option value="success" <?php echo e(old('type', $t?->type) === 'success' ? 'selected' : ''); ?>>موفق</option>
        <option value="warning" <?php echo e(old('type', $t?->type) === 'warning' ? 'selected' : ''); ?>>هشدار</option>
        <option value="danger" <?php echo e(old('type', $t?->type) === 'danger' ? 'selected' : ''); ?>>مهم</option>
    </select>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">لینک</label>
        <input type="url" name="link" class="form-control" value="<?php echo e(old('link', $t?->link)); ?>" placeholder="https://">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">متن لینک</label>
        <input type="text" name="link_text" class="form-control" value="<?php echo e(old('link_text', $t?->link_text)); ?>" placeholder="مشاهده بیشتر">
    </div>
</div>
<div class="mb-3">
    <label class="form-label">مخاطب</label>
    <select name="audience" class="form-select">
        <option value="admin" <?php echo e(old('audience', $t?->audience ?? 'admin') === 'admin' ? 'selected' : ''); ?>>ادمین سایت مشتری</option>
        <option value="public" <?php echo e(old('audience', $t?->audience) === 'public' ? 'selected' : ''); ?>>کاربر نهایی (سایت فروش)</option>
    </select>
    <small class="text-muted">اعلان «ادمین» در پنل مدیریت سایت مشتری نمایش داده می‌شود. «کاربر نهایی» برای مشتریان در سایت فروش.</small>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">موقعیت تولبار</label>
        <select name="position" class="form-select">
            <option value="top" <?php echo e(old('position', $t?->position ?? 'top') === 'top' ? 'selected' : ''); ?>>بالا</option>
            <option value="left" <?php echo e(old('position', $t?->position) === 'left' ? 'selected' : ''); ?>>چپ</option>
            <option value="right" <?php echo e(old('position', $t?->position) === 'right' ? 'selected' : ''); ?>>راست</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">موقعیت تصویر</label>
        <select name="image_position" class="form-select">
            <option value="background" <?php echo e(old('image_position', $t?->image_position ?? 'right') === 'background' ? 'selected' : ''); ?>>پس‌زمینه</option>
            <option value="left" <?php echo e(old('image_position', $t?->image_position) === 'left' ? 'selected' : ''); ?>>چپ</option>
            <option value="right" <?php echo e(old('image_position', $t?->image_position) === 'right' ? 'selected' : ''); ?>>راست</option>
        </select>
    </div>
</div>
<div class="mb-3">
    <label class="form-label">تصویر</label>
    <div class="d-flex gap-2 align-items-center">
        <input type="hidden" name="image_path" id="template-image-path" value="<?php echo e(old('image_path', $t?->image_path)); ?>">
        <input type="file" id="template-image-file" accept="image/*" class="d-none">
        <button type="button" id="template-image-upload-btn" class="btn btn-outline-secondary">انتخاب تصویر</button>
        <span class="text-sm text-gray-500" id="template-image-preview"><?php echo e($t && $t->image_path ? '✓ ذخیره شد: ' . $t->image_path : ''); ?></span>
    </div>
</div>
<div class="mb-3">
    <label class="inline-flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1" <?php echo e(old('is_active', $t?->is_active ?? true) ? 'checked' : ''); ?> class="rounded">
        <span>قالب فعال است (ارسال به سایت‌های جدید)</span>
    </label>
</div>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/notification-templates/_form.blade.php ENDPATH**/ ?>