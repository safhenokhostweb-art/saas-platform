<?php $__env->startSection('content'); ?>
<div class="container py-5">

    <h4 class="font-bold mb-4 text-xl"><?php echo e($form->title); ?></h4>

    <?php if(session('success')): ?>
        <div class="alert alert-success"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('site.forms.submit_for_site', [$site, $form->slug])); ?>">
        <?php echo csrf_field(); ?>

        <?php $__currentLoopData = $form->fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="mb-3">
                <label class="form-label">
                    <?php echo e($field->label); ?>

                    <?php if($field->is_required): ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>

                <?php if(in_array($field->type, ['text','email','number'])): ?>
                    <input type="<?php echo e($field->type); ?>" name="field_<?php echo e($field->id); ?>" class="form-control" <?php if($field->placeholder): ?> placeholder="<?php echo e($field->placeholder); ?>" <?php endif; ?>>
                <?php elseif($field->type === 'textarea'): ?>
                    <textarea name="field_<?php echo e($field->id); ?>" class="form-control" <?php if($field->placeholder): ?> placeholder="<?php echo e($field->placeholder); ?>" <?php endif; ?>></textarea>
                <?php elseif($field->type === 'date'): ?>
                    <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'field_'.e($field->id).'','value' => ''.e(old('field_'.$field->id)).'','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control persian-date-input']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'field_'.e($field->id).'','value' => ''.e(old('field_'.$field->id)).'','placeholder' => '۱۴۰۳/۰۷/۱۵','class' => 'form-control persian-date-input']); ?>
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
                    <?php $__currentLoopData = $field->options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="form-check">
                            <input class="form-check-input" type="<?php echo e($field->type === 'select' ? 'radio' : $field->type); ?>"
                                   name="field_<?php echo e($field->id); ?>" value="<?php echo e($opt); ?>">
                            <label class="form-check-label"><?php echo e($opt); ?></label>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>

                <?php $__errorArgs = ['field_'.$field->id];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <?php if(!empty($captcha['enabled'] ?? false)): ?>
            <div class="mb-3">
                <label class="form-label">کپچا</label>
                <?php if($captcha['type'] === 'persian_number' && !empty($captcha['persian_code'])): ?>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-2xl font-bold tracking-widest text-gray-700 bg-gray-100 px-3 py-2 rounded select-none" aria-hidden="true"><?php echo e($captcha['persian_code']); ?></span>
                        <input type="text" name="captcha_answer" class="form-control w-32" placeholder="عدد بالا را وارد کنید" maxlength="6" required autocomplete="off">
                    </div>
                <?php elseif($captcha['type'] === 'normal' && !empty($captcha['normal_text'])): ?>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xl font-bold text-gray-700 bg-gray-100 px-3 py-2 rounded select-none" aria-hidden="true"><?php echo e($captcha['normal_text']); ?></span>
                        <input type="text" name="captcha_answer" class="form-control w-24" placeholder="جواب" maxlength="4" required autocomplete="off">
                    </div>
                <?php elseif($captcha['type'] === 'hcaptcha' && !empty($captcha['hcaptcha_site_key'])): ?>
                    <div class="h-captcha" data-sitekey="<?php echo e($captcha['hcaptcha_site_key']); ?>" data-hl="fa"></div>
                <?php endif; ?>
                <?php $__errorArgs = ['captcha'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        <?php endif; ?>

        <button class="btn btn-success w-full rounded-xl">ارسال فرم</button>
    </form>
</div>

<?php if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key'])): ?>
<?php $__env->startPush('scripts'); ?>
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
<?php $__env->stopPush(); ?>
<?php endif; ?>

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

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/site/forms/show.blade.php ENDPATH**/ ?>