

<?php $__env->startSection('header', 'ارسال اعلان به چند سایت'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="<?php echo e(route('admin.sites.index')); ?>" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="<?php echo e(route('admin.sites.notifications.create-bulk')); ?>" class="text-indigo-600 hover:underline">اعلان به چند سایت</a>
    </p>

    <div class="card p-4 shadow-sm rounded-xl">
        <form method="POST" action="<?php echo e(route('admin.sites.notifications.store-bulk')); ?>" id="notif-bulk-form">
            <?php echo csrf_field(); ?>

            <div class="mb-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                <h5 class="font-bold mb-3">سایت‌های هدف *</h5>
                <p class="text-sm text-gray-600 mb-3">یک یا چند سایت را انتخاب کنید. اعلان با همان محتوا برای همهٔ سایت‌های انتخاب‌شده ارسال می‌شود.</p>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="select-all-sites" class="rounded border-gray-300">
                        <span class="font-medium">همه</span>
                    </label>
                    <span class="text-gray-400">|</span>
                    <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="site_ids[]" value="<?php echo e($s->id); ?>" class="site-checkbox rounded border-gray-300" <?php echo e(in_array($s->id, old('site_ids', [])) ? 'checked' : ''); ?>>
                            <span><?php echo e($s->title); ?></span>
                        </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php $__errorArgs = ['site_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-600 text-sm mt-2"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <?php echo $__env->make('admin.sites.notifications._form', ['notification' => null], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">ارسال اعلان به سایت‌های انتخاب‌شده</button>
                <a href="<?php echo e(route('admin.sites.index')); ?>" class="btn btn-outline-secondary me-2">انصراف</a>
            </div>
        </form>
    </div>
</div>
<?php $__env->startPush('head'); ?>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
<?php $__env->stopPush(); ?>
<?php $__env->startPush('scripts'); ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({ format: 'YYYY/MM/DD', persianDigit: true, initialValueType: 'persian' });
    }
    var selectAll = document.getElementById('select-all-sites');
    var checkboxes = document.querySelectorAll('.site-checkbox');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
        });
    }
    document.getElementById('notif-bulk-form')?.addEventListener('submit', function(e) {
        var checked = document.querySelectorAll('.site-checkbox:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert('حداقل یک سایت را انتخاب کنید.');
        }
    });
    var fileInput = document.getElementById('notif-image-file');
    var pathInput = document.getElementById('notif-image-path');
    var uploadBtn = document.getElementById('notif-image-upload-btn');
    if (fileInput && pathInput && uploadBtn) {
        uploadBtn.addEventListener('click', function() { fileInput.click(); });
        fileInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            var fd = new FormData();
            fd.append('image', this.files[0]);
            fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'در حال آپلود...';
            fetch('<?php echo e(route("admin.notifications.upload-image")); ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) {
                    if (!r.ok) return r.json().then(function(d) { throw new Error(d.message || 'خطا در آپلود'); });
                    return r.json();
                })
                .then(function(d) {
                    if (d.path) {
                        pathInput.value = d.path;
                        var preview = document.getElementById('notif-image-preview');
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

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/notifications/create-bulk.blade.php ENDPATH**/ ?>