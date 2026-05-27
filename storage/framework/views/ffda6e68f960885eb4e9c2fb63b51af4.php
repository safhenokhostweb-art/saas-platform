<?php $__env->startSection('header', 'پیش‌نمایش فرم: ' . $form->title); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-2xl mx-auto">
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6 flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <span>این پیش‌نمایش فرم نهایی است. ارسال غیرفعال است و فقط برای تست ظاهر فرم استفاده شود.</span>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6">
        <h4 class="font-bold mb-4 text-xl"><?php echo e($form->title); ?></h4>

        <?php if(!empty($form->intro_text)): ?>
            <div class="mb-6 text-gray-700 leading-relaxed">
                <?php echo strip_tags($form->intro_text, '<p><br><strong><em><u><a><ul><ol><li><h2><h3><h4><blockquote>'); ?>

            </div>
        <?php endif; ?>

        <form action="#" method="POST" class="preview-form-no-submit" onsubmit="event.preventDefault(); alert('این پیش‌نمایش است. در سایت مشتری ارسال فعال است.');">
            <?php echo csrf_field(); ?>

            <?php if(!empty($captcha['enabled'])): ?>
                <div class="mb-4">
                    <label class="block mb-1 font-medium">کپچا</label>
                    <?php if(($captcha['type'] ?? '') === 'persian_number' && !empty($captcha['persian_code'])): ?>
                        <div class="flex items-center gap-2 flex-wrap">
                            <?php if (isset($component)) { $__componentOriginal7a0e54dcbc9a72f04f85c64167dd3aa6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a0e54dcbc9a72f04f85c64167dd3aa6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-captcha-challenge','data' => ['code' => $captcha['persian_code'],'id' => 'preview-captcha-persian']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-captcha-challenge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['code' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($captcha['persian_code']),'id' => 'preview-captcha-persian']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a0e54dcbc9a72f04f85c64167dd3aa6)): ?>
<?php $attributes = $__attributesOriginal7a0e54dcbc9a72f04f85c64167dd3aa6; ?>
<?php unset($__attributesOriginal7a0e54dcbc9a72f04f85c64167dd3aa6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a0e54dcbc9a72f04f85c64167dd3aa6)): ?>
<?php $component = $__componentOriginal7a0e54dcbc9a72f04f85c64167dd3aa6; ?>
<?php unset($__componentOriginal7a0e54dcbc9a72f04f85c64167dd3aa6); ?>
<?php endif; ?>
                            <input type="text" class="border rounded-xl px-4 py-3 w-32" placeholder="عدد بالا را وارد کنید" disabled>
                        </div>
                    <?php elseif(($captcha['type'] ?? '') === 'normal' && !empty($captcha['normal_text'])): ?>
                        <div class="flex items-center gap-2 flex-wrap">
                            <?php if (isset($component)) { $__componentOriginaleb91f24f7ff8764e958b07bfc3588d53 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaleb91f24f7ff8764e958b07bfc3588d53 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-captcha-math','data' => ['text' => $captcha['normal_text']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-captcha-math'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($captcha['normal_text'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaleb91f24f7ff8764e958b07bfc3588d53)): ?>
<?php $attributes = $__attributesOriginaleb91f24f7ff8764e958b07bfc3588d53; ?>
<?php unset($__attributesOriginaleb91f24f7ff8764e958b07bfc3588d53); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaleb91f24f7ff8764e958b07bfc3588d53)): ?>
<?php $component = $__componentOriginaleb91f24f7ff8764e958b07bfc3588d53; ?>
<?php unset($__componentOriginaleb91f24f7ff8764e958b07bfc3588d53); ?>
<?php endif; ?>
                            <input type="text" class="border rounded-xl px-4 py-3 w-24" placeholder="جواب" disabled>
                        </div>
                    <?php elseif(($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key'])): ?>
                        <div class="h-captcha" data-sitekey="<?php echo e($captcha['hcaptcha_site_key']); ?>" data-hl="fa"></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php $__empty_1 = true; $__currentLoopData = $form->fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="mb-4">
                    <label class="block mb-1 font-medium">
                        <?php echo e($field->label); ?>

                        <?php if($field->is_required): ?>
                            <span class="text-red-500">*</span>
                        <?php endif; ?>
                    </label>

                    <?php if(in_array($field->type, ['text','email','number'])): ?>
                        <input type="<?php echo e($field->type); ?>" name="field_<?php echo e($field->id); ?>"
                               class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                               <?php if($field->placeholder): ?> placeholder="<?php echo e($field->placeholder); ?>" <?php endif; ?> disabled>
                    <?php elseif($field->type === 'textarea'): ?>
                        <textarea name="field_<?php echo e($field->id); ?>"
                                  class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                                  <?php if($field->placeholder): ?> placeholder="<?php echo e($field->placeholder); ?>" <?php endif; ?> disabled></textarea>
                    <?php elseif($field->type === 'date'): ?>
                        <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'field_'.e($field->id).'','value' => '','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'w-full border rounded-xl px-4 py-3 persian-date-input']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'field_'.e($field->id).'','value' => '','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'w-full border rounded-xl px-4 py-3 persian-date-input']); ?>
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
                    <?php elseif(in_array($field->type, ['select','radio','checkbox'])): ?>
                        <?php $__currentLoopData = $field->options ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="flex items-center gap-2 mb-1">
                                <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded"
                                       type="<?php echo e($field->type === 'select' ? 'radio' : $field->type); ?>"
                                       name="field_<?php echo e($field->id); ?>" value="<?php echo e($opt); ?>" disabled>
                                <label class="text-gray-700"><?php echo e($opt); ?></label>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>

                    <?php if($field->help_text): ?>
                        <p class="text-gray-500 text-sm mt-1"><?php echo e($field->help_text); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="text-gray-500">هنوز فیلدی به این فرم اضافه نشده است.</p>
            <?php endif; ?>

            <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white w-full py-3 rounded-xl mt-4 opacity-60 cursor-not-allowed">
                ارسال فرم (غیرفعال در پیش‌نمایش)
            </button>
        </form>
    </div>

    <p class="mt-4 text-center">
        <a href="<?php echo e(route('admin.forms.edit', $form)); ?>" class="text-indigo-600 hover:underline">بازگشت به ویرایش فرم</a>
        <?php if (\Illuminate\Support\Facades\Blade::check('platform')): ?>
        <span class="mx-2">|</span>
        <a href="<?php echo e(route('admin.forms.sites.edit', $form)); ?>" class="text-indigo-600 hover:underline">اتصال به سایت‌ها</a>
        <?php if(isset($firstSite)): ?>
            <span class="mx-2">|</span>
            <a href="<?php echo e(route('site.forms.show_for_site', [$firstSite, $form->slug])); ?>" class="text-indigo-600 hover:underline" target="_blank">مشاهده در سایت (با کپچا)</a>
        <?php endif; ?>
        <?php endif; ?>
    </p>
</div>

<?php if($form->fields->contains('type', 'date')): ?>
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
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(config('forms.layouts.admin'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\forms\src\Providers/../../resources/views/admin/forms/preview.blade.php ENDPATH**/ ?>