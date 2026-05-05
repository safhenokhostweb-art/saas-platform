<?php $__env->startSection('header', 'عنوان صفحه'); ?>


<?php $__env->startSection('content'); ?>

<div class="container py-5">

    <h4 class="font-bold mb-4 text-xl">ساخت سایت جدید برای مشتری</h4>
    <?php if($errors->any()): ?>
    <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
        <ul class="list-disc pr-5">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li><?php echo e($error); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
    <?php endif; ?>
    <form method="POST" action="<?php echo e(route('admin.sites.store')); ?>" class="card p-4 shadow-sm rounded-xl border-0">
        <?php echo csrf_field(); ?>

        <div class="mb-3">
            <label class="form-label">مشتری</label>
            <select name="customer_id" class="form-select" required>
                <option value="">انتخاب مشتری</option>
                <?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($customer->id); ?>">
                    <?php echo e($customer->company_name ?? $customer->name ?? $customer->email ?? 'بدون نام'); ?>

                </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">عنوان سایت</label>
            <input type="text" name="title" class="form-control" placeholder="نام یا عنوان سایت" required>
        </div>

        <div class="mb-3">
            <label class="form-label">دامنه</label>
            <input type="text" name="domain" class="form-control" placeholder="مثال: example.com">
        </div>

        <?php if(isset($paymentGateways) && $paymentGateways->isNotEmpty()): ?>
        <div class="mb-3">
            <label class="form-label">درگاه پرداخت (اختیاری)</label>
            <select name="payment_gateway_id" class="form-select">
                <option value="">استفاده از درگاه پیش‌فرض پلتفرم</option>
                <?php $__currentLoopData = $paymentGateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($gw->id); ?>"><?php echo e($gw->name); ?> (<?php echo e(\App\Models\PaymentGateway::drivers()[$gw->driver] ?? $gw->driver); ?>)</option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <?php endif; ?>

        <hr>

        <h6 class="font-bold mt-3">ماژول‌ها</h6>
        <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="modules[]" value="<?php echo e($module->id); ?>">
            <label class="form-check-label">
                <?php echo e($module->title); ?>

            </label>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <hr>

        <h6 class="font-bold mt-3">اطلاعات هاست و دیتابیس</h6>

        <input name="hosting_company" class="form-control mb-2" placeholder="نام شرکت هاست">
        <input name="db_host" class="form-control mb-2" placeholder="آدرس سرور دیتابیس">
        <input name="db_name" class="form-control mb-2" placeholder="نام دیتابیس">
        <input name="db_user" class="form-control mb-2" placeholder="کاربر دیتابیس">
        <input name="db_password" class="form-control mb-2" placeholder="رمز عبور دیتابیس">

        <div class="row mt-3">
            <div class="col-md-4 mb-3">
                <label class="form-label">تاریخ انقضای هاست (شمسی)</label>
                <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'host_expires_at','value' => '','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'host_expires_at','value' => '','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control']); ?>
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
            <div class="col-md-4 mb-3">
                <label class="form-label">تاریخ انقضای دامنه (شمسی)</label>
                <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'domain_expires_at','value' => '','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'domain_expires_at','value' => '','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control']); ?>
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

        <hr>

        <div class="mb-3">
            <label class="form-label">تاریخ انقضای پشتیبانی (شمسی)</label>
            <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'support_expires_at','value' => '','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'support_expires_at','value' => '','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control']); ?>
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

        <div class="mb-3">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>سایت فعال است</span>
            </label>
        </div>

        <button class="btn btn-primary w-full rounded-xl">
            ساخت سایت
        </button>
    </form>
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
    if (document.querySelector('.persian-date-input') && typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({
            format: 'YYYY/MM/DD',
            persianDigit: true,
            initialValueType: 'persian'
        });
    }
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/create.blade.php ENDPATH**/ ?>