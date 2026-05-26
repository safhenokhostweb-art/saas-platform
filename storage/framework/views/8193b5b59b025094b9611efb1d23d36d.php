<?php $__env->startSection('header', 'ویرایش سایت'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto py-5">
    <h4 class="font-bold mb-4 text-xl">ویرایش سایت: <?php echo e($site->title); ?></h4>

    <?php if($errors->any()): ?>
        <div class="alert alert-danger mb-4" role="alert">
            <ul class="list-disc list-inside text-right mb-0">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('admin.sites.update', $site)); ?>" class="card p-4 shadow-sm rounded-xl border-0">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <div class="mb-3">
            <label class="form-label">مشتری</label>
            <select name="customer_id" class="form-select" required>
                <option value="">انتخاب مشتری</option>
                <?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($customer->id); ?>" <?php echo e(old('customer_id', $site->customer_id) == $customer->id ? 'selected' : ''); ?>>
                        <?php echo e($customer->company_name ?? $customer->name ?? $customer->email ?? 'بدون نام'); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">عنوان سایت</label>
            <input type="text" name="title" class="form-control" placeholder="نام یا عنوان سایت" value="<?php echo e(old('title', $site->title)); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">دامنه</label>
            <input type="text" name="domain" class="form-control" placeholder="مثال: example.com" value="<?php echo e(old('domain', $site->domain)); ?>">
        </div>

        <?php if(isset($paymentGateways) && $paymentGateways->isNotEmpty() && $site->hasBankGatewayModule()): ?>
        <div class="mb-3">
            <label class="form-label">درگاه پرداخت (اختیاری)</label>
            <select name="payment_gateway_id" class="form-select">
                <option value="">استفاده از درگاه پیش‌فرض پلتفرم</option>
                <?php $__currentLoopData = $paymentGateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($gw->id); ?>" <?php echo e(old('payment_gateway_id', $site->payment_gateway_id) == $gw->id ? 'selected' : ''); ?>><?php echo e($gw->name); ?> (<?php echo e(\App\Models\PaymentGateway::drivers()[$gw->driver] ?? $gw->driver); ?>)</option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <p class="text-sm text-gray-500 mt-1">فقط برای سایت‌هایی که ماژول «درگاه بانکی» دارند نمایش داده می‌شود.</p>
        </div>
        <?php endif; ?>

        <?php if(\Illuminate\Support\Facades\Schema::hasTable('themes') && \Illuminate\Support\Facades\Schema::hasColumn('sites', 'theme_id') && isset($themes) && $themes->isNotEmpty()): ?>
        <div class="mb-4">
            <label class="form-label">تم (Theme Builder)</label>
            <select name="theme_id" class="form-select">
                <option value="">بدون تم (قالب پیش‌فرض پلتفرم)</option>
                <?php $__currentLoopData = $themes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($t->id); ?>" <?php echo e(old('theme_id', $site->theme_id) == $t->id ? 'selected' : ''); ?>><?php echo e($t->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <p class="text-sm text-gray-500 mt-1">تم انتخاب‌شده هدر، فوتر و استایل سراسری سایت را تعیین می‌کند. <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('accessWizard')): ?><a href="<?php echo e(route('admin.theme-builder.index')); ?>" class="text-indigo-600 hover:underline">ساختار تم</a><?php else: ?>ساختار تم<?php endif; ?> می‌توانید تم جدید بسازید یا ویرایش کنید (فقط Super Admin).</p>
        </div>
        <?php endif; ?>

        <hr>

        <div id="site-access" class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
            <h6 class="font-bold text-blue-900 mb-1">دسترسی و ماژول‌های این سایت (مشتری)</h6>
            <p class="text-sm text-blue-800 mb-3">ماژول‌های فعال برای این سایت را انتخاب کنید. فقط ماژول‌هایی که در منوی «دسترسی و منو» فعال کرده‌اید اینجا نمایش داده می‌شوند.</p>
            <?php $__empty_1 = true; $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php $siteMod = $site->modules->firstWhere('id', $module->id); $isActive = $siteMod && $siteMod->pivot->is_active; ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="modules[]" value="<?php echo e($module->id); ?>"
                        <?php echo e($isActive ? 'checked' : ''); ?>>
                    <label class="form-check-label">
                        <?php echo e($module->title); ?>

                    </label>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="text-gray-600 text-sm">ماژولی تعریف نشده یا در <a href="<?php echo e(route('admin.access.index')); ?>" class="underline">دسترسی و منو</a> هیچ ماژولی فعال نیست.</p>
            <?php endif; ?>
        </div>

        <hr>

        <?php if(\Illuminate\Support\Facades\Schema::hasTable('site_settings')): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
            <input type="hidden" name="site_design_section" value="1">
            <h6 class="font-bold text-amber-900 mb-2">تنظیمات ظاهر فرانت (اختیاری)</h6>
            <p class="text-sm text-amber-800 mb-3">در صورت خالی گذاشتن، از تنظیمات عمومی پلتفرم استفاده می‌شود.</p>
            <?php
                $colorPalette = ['#4f46e5','#2563eb','#059669','#dc2626','#ea580c','#ca8a04','#7c3aed','#db2777','#0d9488','#1e40af','#374151','#ffffff'];
            ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-sm">رنگ اصلی</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" class="h-9 w-12 rounded border cursor-pointer" value="<?php echo e(old('site_design_theme_primary', $siteDesign['theme_primary'] ?? '#4f46e5')); ?>" onchange="this.nextElementSibling.value=this.value">
                        <input type="text" name="site_design_theme_primary" class="form-control flex-1" value="<?php echo e(old('site_design_theme_primary', $siteDesign['theme_primary'] ?? '')); ?>" placeholder="#4f46e5">
                    </div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <?php $__currentLoopData = $colorPalette; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button type="button" class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition" style="background:<?php echo e($c); ?>" title="<?php echo e($c); ?>" onclick="var inp=this.closest('div').previousElementSibling.querySelector('input[type=text]'); var col=this.closest('div').previousElementSibling.querySelector('input[type=color]'); inp.value='<?php echo e($c); ?>'; col.value='<?php echo e($c); ?>';"></button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <div>
                    <label class="form-label text-sm">پس‌زمینه منو</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" class="h-9 w-12 rounded border cursor-pointer" value="<?php echo e(old('site_design_theme_menu_bg', $siteDesign['theme_menu_bg'] ?? '#ffffff')); ?>" onchange="this.nextElementSibling.value=this.value">
                        <input type="text" name="site_design_theme_menu_bg" class="form-control flex-1" value="<?php echo e(old('site_design_theme_menu_bg', $siteDesign['theme_menu_bg'] ?? '')); ?>" placeholder="#ffffff">
                    </div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <?php $__currentLoopData = $colorPalette; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button type="button" class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition" style="background:<?php echo e($c); ?>" title="<?php echo e($c); ?>" onclick="var d=this.closest('div').previousElementSibling; var inp=d.querySelector('input[type=text]'); var col=d.querySelector('input[type=color]'); inp.value='<?php echo e($c); ?>'; col.value='<?php echo e($c); ?>';"></button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <div>
                    <label class="form-label text-sm">رنگ متن منو</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" class="h-9 w-12 rounded border cursor-pointer" value="<?php echo e(old('site_design_theme_menu_text', $siteDesign['theme_menu_text'] ?? '#374151')); ?>" onchange="this.nextElementSibling.value=this.value">
                        <input type="text" name="site_design_theme_menu_text" class="form-control flex-1" value="<?php echo e(old('site_design_theme_menu_text', $siteDesign['theme_menu_text'] ?? '')); ?>" placeholder="#374151">
                    </div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <?php $__currentLoopData = $colorPalette; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button type="button" class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition" style="background:<?php echo e($c); ?>" title="<?php echo e($c); ?>" onclick="var d=this.closest('div').previousElementSibling; var inp=d.querySelector('input[type=text]'); var col=d.querySelector('input[type=color]'); inp.value='<?php echo e($c); ?>'; col.value='<?php echo e($c); ?>';"></button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <div>
                    <label class="form-label text-sm">لوگوی اختصاصی سایت</label>
                    <input type="text" name="site_design_site_logo" class="form-control" value="<?php echo e(old('site_design_site_logo', $siteDesign['site_logo'] ?? '')); ?>" placeholder="مسیر یا URL تصویر (خالی = لوگوی پلتفرم)">
                </div>
            </div>
        </div>
        <hr>
        <?php endif; ?>

        <h6 class="font-bold mt-3">اطلاعات هاست و دیتابیس</h6>

        <input name="hosting_company" class="form-control mb-2" placeholder="نام شرکت هاست" value="<?php echo e(old('hosting_company', $site->hosting_company)); ?>">
        <input name="db_host" class="form-control mb-2" placeholder="آدرس سرور دیتابیس" value="<?php echo e(old('db_host', $site->db_host)); ?>">
        <input name="db_name" class="form-control mb-2" placeholder="نام دیتابیس" value="<?php echo e(old('db_name', $site->db_name)); ?>">
        <input name="db_user" class="form-control mb-2" placeholder="کاربر دیتابیس" value="<?php echo e(old('db_user', $site->db_user)); ?>">
        <input name="db_password" class="form-control mb-2" placeholder="رمز عبور دیتابیس" value="<?php echo e(old('db_password', $site->db_password)); ?>" type="password" autocomplete="new-password">

        <div class="row mt-3">
            <div class="col-md-4 mb-3">
                <label class="form-label">تاریخ انقضای هاست (شمسی)</label>
                <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'host_expires_at','value' => old('host_expires_at', $site->host_expires_at ? \App\Helpers\Jalali::format($site->host_expires_at) : ''),'placeholder' => '۱۴۰۳/۰۷/۱۵']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'host_expires_at','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('host_expires_at', $site->host_expires_at ? \App\Helpers\Jalali::format($site->host_expires_at) : '')),'placeholder' => '۱۴۰۳/۰۷/۱۵']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'domain_expires_at','value' => old('domain_expires_at', $site->domain_expires_at ? \App\Helpers\Jalali::format($site->domain_expires_at) : ''),'placeholder' => '۱۴۰۳/۰۷/۱۵']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'domain_expires_at','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('domain_expires_at', $site->domain_expires_at ? \App\Helpers\Jalali::format($site->domain_expires_at) : '')),'placeholder' => '۱۴۰۳/۰۷/۱۵']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'support_expires_at','value' => old('support_expires_at', \App\Helpers\Jalali::format($site->support_expires_at)),'placeholder' => '۱۴۰۳/۰۷/۱۵']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'support_expires_at','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('support_expires_at', \App\Helpers\Jalali::format($site->support_expires_at))),'placeholder' => '۱۴۰۳/۰۷/۱۵']); ?>
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
            <?php $supportProduct = \App\Models\Product::where('is_active', true)->where('type', \App\Models\Product::TYPE_SUPPORT_PLAN)->first(); ?>
            <?php if($supportProduct): ?>
                <p class="text-sm text-gray-600 mt-1">
                    <a href="<?php echo e(route('store.index')); ?>?site_id=<?php echo e($site->id); ?>" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                        خرید پشتیبانی برای این سایت →
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" <?php echo e(old('is_active', $site->is_active) ? 'checked' : ''); ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>سایت فعال است</span>
            </label>
        </div>

        <div class="flex gap-2 flex-wrap items-center">
            <button type="submit" class="btn btn-primary">
                ذخیره تغییرات
            </button>
            <a href="<?php echo e(route('admin.sites.index')); ?>" class="btn btn-secondary">انصراف</a>
            <a href="<?php echo e(route('admin.captcha.site', $site)); ?>" class="btn btn-outline-primary">تنظیمات کپچا این سایت</a>
            <a href="<?php echo e(route('admin.sites.modules.edit', $site)); ?>" class="btn btn-outline-primary">ماژول‌های این سایت</a>
            <?php if(\App\Helpers\PlatformModules::isEnabled('notifications')): ?>
            <a href="<?php echo e(route('admin.sites.notifications.index', $site)); ?>" class="btn btn-outline-primary">اعلان‌های این سایت</a>
            <?php endif; ?>
            <a href="<?php echo e(route('admin.sites.updates.index', $site)); ?>" class="btn btn-outline-primary">توزیع بروزرسانی</a>
            <a href="<?php echo e(route('admin.access.index')); ?>" class="btn btn-outline-secondary">دسترسی و منو (ماژول‌های پلتفرم)</a>
        </div>
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

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/edit.blade.php ENDPATH**/ ?>