<?php $__env->startSection('header', 'ویرایش فرم: ' . $form->title); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-4 flex flex-wrap gap-3">
    <a href="<?php echo e(route('admin.forms.preview', $form)); ?>" target="_blank"
       class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-xl inline-flex items-center gap-2 text-sm hover:opacity-90 transition">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        پیش‌نمایش فرم نهایی
    </a>
    <?php if (\Illuminate\Support\Facades\Blade::check('platform')): ?>
    <a href="<?php echo e(route('admin.forms.sites.edit', $form)); ?>"
       class="border border-indigo-600 text-indigo-600 px-4 py-2 rounded-xl inline-flex items-center gap-2 text-sm hover:bg-indigo-50 transition">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.571a4.5 4.5 0 00-1.242-7.244l-4.5-4.5a4.5 4.5 0 00-6.364 6.364L4.34 8.688"/>
        </svg>
        اتصال به سایت‌ها
    </a>
    <?php endif; ?>
</div>

<?php if(session('success')): ?>
    <div class="bg-emerald-100 text-emerald-700 p-4 rounded-xl mb-6"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <h2 class="font-bold text-lg mb-4">تنظیمات فرم</h2>
        <form method="POST" action="<?php echo e(route('admin.forms.update', $form)); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div>
                <label class="block mb-1 font-medium text-gray-700">عنوان فرم</label>
                <input type="text" name="title" value="<?php echo e(old('title', $form->title)); ?>"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div>
                <label class="block mb-1 font-medium text-gray-700">نامک (اسلاگ)</label>
                <input type="text" name="slug" value="<?php echo e(old('slug', $form->slug)); ?>"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <?php $__errorArgs = ['slug'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <?php if(config('forms.seo_enabled', true)): ?>
            <div>
                <label class="block mb-1 font-medium text-gray-700">عنوان متا</label>
                <input type="text" name="meta_title" value="<?php echo e(old('meta_title', $form->meta_title)); ?>"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block mb-1 font-medium text-gray-700">توضیحات متا</label>
                <textarea name="meta_description" rows="2"
                          class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"><?php echo e(old('meta_description', $form->meta_description)); ?></textarea>
            </div>
            <div>
                <label class="block mb-1 font-medium text-gray-700">آدرس کنونیکال</label>
                <input type="text" name="canonical_url" value="<?php echo e(old('canonical_url', $form->canonical_url)); ?>"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block mb-1 font-medium text-gray-700">ربات متا</label>
                <input type="text" name="meta_robots" value="<?php echo e(old('meta_robots', $form->meta_robots)); ?>"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block mb-1 font-medium text-gray-700">متن مقدمه</label>
                <textarea name="intro_text" rows="4"
                          class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"><?php echo e(old('intro_text', $form->intro_text)); ?></textarea>
            </div>
            <?php endif; ?>

            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" value="1" <?php echo e($form->is_active ? 'checked' : ''); ?> id="is_active"
                       class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_active" class="font-medium text-gray-700">فرم فعال باشد</label>
            </div>

            <button type="submit"
                    class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl hover:opacity-90 transition">
                ذخیره تنظیمات
            </button>
        </form>
    </div>

    
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <?php if($errors->any()): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-xl mb-4">
                <ul class="list-disc pr-5">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <h2 class="font-bold text-lg mb-4">افزودن فیلد جدید</h2>

        <form method="POST" action="<?php echo e(route('admin.forms.fields.store', $form)); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">عنوان فیلد</label>
                <input name="label" class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" placeholder="مثلاً: نام و نام خانوادگی">
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">نام (name)</label>
                <input name="name" class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" placeholder="full_name">
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">نوع فیلد</label>
                <select name="type" class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" id="field-type-select">
                    <?php $__currentLoopData = config('forms.field_types', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            <div id="options-raw-wrapper" class="hidden">
                <label class="block mb-1 text-sm font-medium text-gray-700">گزینه‌ها (هر خط یک گزینه)</label>
                <textarea name="options_raw" rows="4"
                          class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                          placeholder="گزینه ۱&#10;گزینه ۲&#10;گزینه ۳"></textarea>
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">متن راهنما (Placeholder)</label>
                <input name="placeholder" class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">متن کمکی (Help Text)</label>
                <input name="help_text" class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="is_required" value="1"
                       class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="font-medium text-gray-700">الزامی</span>
            </label>

            <button type="submit"
                    class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-6 py-3 rounded-xl hover:opacity-90 transition inline-flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                افزودن فیلد
            </button>
        </form>
    </div>
</div>


<div class="bg-white rounded-2xl shadow-sm p-6 mt-6">
    <h2 class="font-bold text-lg mb-4">فیلدهای فرم</h2>

    <?php if($form->fields->count()): ?>
        <ul class="space-y-3" id="field-list">
            <?php $__currentLoopData = $form->fields()->orderBy('position')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="flex items-center justify-between border border-gray-200 rounded-xl p-4 hover:bg-gray-50 transition" data-field-id="<?php echo e($field->id); ?>">
                <div class="flex items-center gap-3">
                    <span class="cursor-grab text-gray-400" aria-hidden="true">⋮⋮</span>
                    <div>
                        <span class="font-medium"><?php echo e($field->label); ?></span>
                        <span class="text-sm text-gray-500 mr-2">(<?php echo e($field->type); ?>)</span>
                        <?php if($field->is_required): ?>
                            <span class="text-red-500 text-xs">*الزامی</span>
                        <?php endif; ?>
                        <?php if(!$field->is_active): ?>
                            <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded-full mr-2">غیرفعال</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <form method="POST" action="<?php echo e(route('admin.forms.fields.toggle', $field)); ?>">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <button class="px-3 py-1.5 text-xs rounded-xl <?php echo e($field->is_active ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'); ?> transition">
                            <?php echo e($field->is_active ? 'غیرفعال' : 'فعال'); ?>

                        </button>
                    </form>

                    <form method="POST" action="<?php echo e(route('admin.forms.fields.destroy', $field)); ?>"
                          onsubmit="return confirm('حذف فیلد؟');">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('DELETE'); ?>
                        <button class="px-3 py-1.5 text-xs rounded-xl bg-red-100 text-red-700 hover:bg-red-200 transition">
                            حذف
                        </button>
                    </form>
                </div>
            </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    <?php else: ?>
        <p class="text-gray-500 text-center py-8">هنوز فیلدی به این فرم اضافه نشده است.</p>
    <?php endif; ?>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var typeSelect = document.getElementById('field-type-select');
    var optionsWrapper = document.getElementById('options-raw-wrapper');

    function toggleOptionsField() {
        if (typeSelect && optionsWrapper) {
            var val = typeSelect.value;
            optionsWrapper.classList.toggle('hidden', !['select', 'radio', 'checkbox'].includes(val));
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', toggleOptionsField);
        toggleOptionsField();
    }
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(config('forms.layouts.admin'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\forms\src\Providers/../../resources/views/admin/forms/edit.blade.php ENDPATH**/ ?>