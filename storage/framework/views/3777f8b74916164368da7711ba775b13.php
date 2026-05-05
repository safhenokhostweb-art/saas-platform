<?php $n = $notification ?? null; ?>
<div class="mb-3">
    <label class="form-label">عنوان *</label>
    <input type="text" name="title" class="form-control" value="<?php echo e(old('title', $n?->title)); ?>" required placeholder="مثلاً اطلاعیه مهم">
</div>
<div class="mb-3">
    <label class="form-label">متن</label>
    <textarea name="body" class="form-control" rows="4" placeholder="متن کامل اعلان..."><?php echo e(old('body', $n?->body)); ?></textarea>
</div>
<div class="mb-3">
    <label class="form-label">نوع</label>
    <select name="type" class="form-select">
        <option value="info" <?php echo e(old('type', $n?->type ?? 'info') === 'info' ? 'selected' : ''); ?>>اطلاع</option>
        <option value="success" <?php echo e(old('type', $n?->type) === 'success' ? 'selected' : ''); ?>>موفق</option>
        <option value="warning" <?php echo e(old('type', $n?->type) === 'warning' ? 'selected' : ''); ?>>هشدار</option>
        <option value="danger" <?php echo e(old('type', $n?->type) === 'danger' ? 'selected' : ''); ?>>مهم</option>
    </select>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">لینک</label>
        <input type="url" name="link" class="form-control" value="<?php echo e(old('link', $n?->link)); ?>" placeholder="https://">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">متن لینک</label>
        <input type="text" name="link_text" class="form-control" value="<?php echo e(old('link_text', $n?->link_text)); ?>" placeholder="مشاهده بیشتر">
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">مخاطب</label>
        <select name="audience" class="form-select">
            <option value="admin" <?php echo e(old('audience', $n?->audience ?? 'admin') === 'admin' ? 'selected' : ''); ?>>ادمین سایت مشتری</option>
        </select>
        <small class="text-muted">اعلان‌ها فقط در پنل مدیریت سایت مشتری به ادمین نمایش داده می‌شوند.</small>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">اولویت</label>
        <select name="priority" class="form-select">
            <option value="low" <?php echo e(old('priority', $n?->priority ?? 'normal') === 'low' ? 'selected' : ''); ?>>کم</option>
            <option value="normal" <?php echo e(old('priority', $n?->priority ?? 'normal') === 'normal' ? 'selected' : ''); ?>>عادی</option>
            <option value="high" <?php echo e(old('priority', $n?->priority ?? 'normal') === 'high' ? 'selected' : ''); ?>>مهم</option>
            <option value="urgent" <?php echo e(old('priority', $n?->priority ?? 'normal') === 'urgent' ? 'selected' : ''); ?>>فوری</option>
        </select>
        <small class="text-muted">اعلان‌های فوری در صدر لیست نمایش داده می‌شوند.</small>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">موقعیت تولبار</label>
        <select name="position" class="form-select">
            <option value="top" <?php echo e(old('position', $n?->position ?? 'top') === 'top' ? 'selected' : ''); ?>>بالا</option>
            <option value="left" <?php echo e(old('position', $n?->position) === 'left' ? 'selected' : ''); ?>>چپ</option>
            <option value="right" <?php echo e(old('position', $n?->position) === 'right' ? 'selected' : ''); ?>>راست</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">موقعیت تصویر</label>
        <select name="image_position" class="form-select">
            <option value="background" <?php echo e(old('image_position', $n?->image_position ?? 'right') === 'background' ? 'selected' : ''); ?>>پس‌زمینه</option>
            <option value="left" <?php echo e(old('image_position', $n?->image_position) === 'left' ? 'selected' : ''); ?>>چپ</option>
            <option value="right" <?php echo e(old('image_position', $n?->image_position) === 'right' ? 'selected' : ''); ?>>راست</option>
        </select>
    </div>
</div>
<div class="mb-3">
    <label class="form-label">تصویر (پس‌زمینه یا کنار اعلان)</label>
    <div class="d-flex gap-2 align-items-center">
        <input type="hidden" name="image_path" id="notif-image-path" value="<?php echo e(old('image_path', $n?->image_path)); ?>">
        <input type="file" id="notif-image-file" accept="image/*" class="d-none">
        <button type="button" id="notif-image-upload-btn" class="btn btn-outline-secondary">انتخاب تصویر</button>
        <span class="text-sm text-gray-500" id="notif-image-preview"><?php echo e($n && $n->image_path ? '✓ ذخیره شد: ' . $n->image_path : ''); ?></span>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">تاریخ شروع (شمسی)</label>
        <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'starts_at','value' => old('starts_at', $n ? \App\Helpers\Jalali::format($n->starts_at) : ''),'placeholder' => '۱۴۰۴/۱۲/۰۵','class' => 'form-control']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'starts_at','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('starts_at', $n ? \App\Helpers\Jalali::format($n->starts_at) : '')),'placeholder' => '۱۴۰۴/۱۲/۰۵','class' => 'form-control']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16)): ?>
<?php $attributes = $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16; ?>
<?php unset($__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale306a0c31e413f34dfe42b38d8fcaf16)): ?>
<?php $component = $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16; ?>
<?php unset($__componentOriginale306a0c31e413f34dfe42b38d8fcaf16); ?>
<?php endif; ?>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">تاریخ انقضا (شمسی)</label>
        <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'expires_at','value' => old('expires_at', $n ? \App\Helpers\Jalali::format($n->expires_at) : ''),'placeholder' => '۱۴۰۴/۱۲/۳۰','class' => 'form-control']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'expires_at','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('expires_at', $n ? \App\Helpers\Jalali::format($n->expires_at) : '')),'placeholder' => '۱۴۰۴/۱۲/۳۰','class' => 'form-control']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16)): ?>
<?php $attributes = $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16; ?>
<?php unset($__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale306a0c31e413f34dfe42b38d8fcaf16)): ?>
<?php $component = $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16; ?>
<?php unset($__componentOriginale306a0c31e413f34dfe42b38d8fcaf16); ?>
<?php endif; ?>
    </div>
</div>
<small class="text-muted d-block mb-2">خالی = نمایش فوری و بدون انقضا. اگر تاریخ شروع در آینده باشد، اعلان تا آن زمان نمایش داده نمی‌شود.</small>
<div class="mb-3">
    <label class="inline-flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1" <?php echo e(old('is_active', $n?->is_active ?? true) ? 'checked' : ''); ?> class="rounded">
        <span>اعلان فعال است</span>
    </label>
</div>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/notifications/_form.blade.php ENDPATH**/ ?>