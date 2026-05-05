

<?php $__env->startSection('header', 'کلید لایسنس — ' . $site->title); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="<?php echo e(route('admin.sites.index')); ?>" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="text-indigo-600 hover:underline"><?php echo e($site->title); ?></a>
        → کلید لایسنس
    </p>

    <div class="alert alert-warning mb-4" role="alert">
        <strong>توجه:</strong> این کلید فقط یک‌بار نمایش داده می‌شود. آن را کپی کرده و در محل امن ذخیره کنید و به مشتری تحویل دهید.
    </div>

    <div class="card p-4 shadow-sm rounded-xl">
        <label class="form-label font-bold">کلید لایسنس</label>
        <div class="flex items-center gap-2">
            <input type="text" class="form-control font-mono text-lg" value="<?php echo e($key); ?>" readonly id="license-key-input">
            <button type="button" class="btn btn-outline-primary" id="license-copy-btn">کپی</button>
        </div>
    </div>
    <script>
    document.getElementById('license-copy-btn')?.addEventListener('click', function() {
        var input = document.getElementById('license-key-input');
        var btn = this;
        var text = input?.value || '';
        function fallbackCopy() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed'; ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                btn.textContent = 'کپی شد!';
            } catch (e) {
                btn.textContent = 'خطا';
            }
            document.body.removeChild(ta);
            setTimeout(function() { btn.textContent = 'کپی'; }, 2000);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                btn.textContent = 'کپی شد!';
                setTimeout(function() { btn.textContent = 'کپی'; }, 2000);
            }).catch(fallbackCopy);
        } else {
            fallbackCopy();
        }
    });
    </script>

    <p class="mt-4">
        <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="btn btn-primary">بازگشت به ویرایش سایت</a>
    </p>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/license-show.blade.php ENDPATH**/ ?>