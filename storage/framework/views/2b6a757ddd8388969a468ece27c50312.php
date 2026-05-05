

<?php $__env->startSection('header', 'قالب اعلان خوش‌آمدگویی جدید'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="<?php echo e(route('admin.notification-templates.index')); ?>" class="text-indigo-600 hover:underline">قالب‌های خوش‌آمدگویی</a>
        → جدید
    </p>

    <div class="card p-4 shadow-sm rounded-xl">
        <form method="POST" action="<?php echo e(route('admin.notification-templates.store')); ?>">
            <?php echo csrf_field(); ?>
            <?php echo $__env->make('admin.notification-templates._form', ['template' => null], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">ذخیره قالب</button>
                <a href="<?php echo e(route('admin.notification-templates.index')); ?>" class="btn btn-outline-secondary me-2">انصراف</a>
            </div>
        </form>
    </div>
</div>
<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('template-image-file');
    var pathInput = document.getElementById('template-image-path');
    var uploadBtn = document.getElementById('template-image-upload-btn');
    if (fileInput && pathInput && uploadBtn) {
        uploadBtn.addEventListener('click', function() { fileInput.click(); });
        fileInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            var fd = new FormData();
            fd.append('image', this.files[0]);
            fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'در حال آپلود...';
            fetch('<?php echo e(route("admin.notification-templates.upload-image")); ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) {
                    if (!r.ok) return r.json().then(function(d) { throw new Error(d.message || 'خطا در آپلود'); });
                    return r.json();
                })
                .then(function(d) {
                    if (d.path) {
                        pathInput.value = d.path;
                        var preview = document.getElementById('template-image-preview');
                        if (preview) { preview.textContent = '✓ ذخیره شد: ' + d.path; preview.style.color = '#15803d'; preview.style.fontWeight = '600'; }
                        alert('تصویر با موفقیت ذخیره شد.');
                    }
                })
                .catch(function(e) { alert(e.message || 'خطا در آپلود'); })
                .finally(function() { uploadBtn.disabled = false; uploadBtn.textContent = 'انتخاب تصویر'; fileInput.value = ''; });
        });
    }
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/notification-templates/create.blade.php ENDPATH**/ ?>