<?php $__env->startSection('header', 'کتابخانه قالب‌ها'); ?>

<?php $__env->startSection('content'); ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <p class="text-slate-500 text-sm">قالب‌های آماده برای سرعت در ساخت فرم‌های جدید</p>
</div>


<div class="mb-8">
  <h2 class="font-bold text-slate-700 mb-3 flex items-center gap-2">
    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09z"/></svg>
    قالب‌های پیش‌ساخته
  </h2>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php $__currentLoopData = $builtIn; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tpl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition group">
      <div class="flex items-start justify-between mb-3">
        <div>
          <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 mb-2">
            <?php echo e($categories[$tpl['category']] ?? $tpl['category']); ?>

          </span>
          <h3 class="font-semibold text-slate-800"><?php echo e($tpl['title']); ?></h3>
          <p class="text-xs text-slate-500 mt-0.5"><?php echo e($tpl['description']); ?></p>
        </div>
      </div>
      <div class="flex items-center justify-between mt-4">
        <span class="text-xs text-slate-400"><?php echo e(count($tpl['fields'])); ?> فیلد</span>
        <a href="<?php echo e(route('admin.forms.create', ['template' => $loop->index, 'builtin' => 1])); ?>"
           class="text-xs text-indigo-600 font-medium hover:text-indigo-800 transition flex items-center gap-1">
          استفاده از این قالب
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>


<?php if($templates->isNotEmpty()): ?>
<div>
  <h2 class="font-bold text-slate-700 mb-3 flex items-center gap-2">
    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0z"/></svg>
    قالب‌های ذخیره‌شده من
  </h2>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition group">
      <div class="flex items-start justify-between mb-3">
        <div class="flex-1 min-w-0">
          <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 mb-2">
            <?php echo e($categories[$template->category] ?? $template->category); ?>

          </span>
          <h3 class="font-semibold text-slate-800 truncate"><?php echo e($template->title); ?></h3>
          <?php if($template->description): ?>
          <p class="text-xs text-slate-500 mt-0.5 line-clamp-2"><?php echo e($template->description); ?></p>
          <?php endif; ?>
        </div>
        <?php if (\Illuminate\Support\Facades\Blade::check('platform')): ?>
        <?php if($template->is_public): ?>
        <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full shrink-0">عمومی</span>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="flex items-center justify-between mt-4">
        <span class="text-xs text-slate-400"><?php echo e($template->getFieldCount()); ?> فیلد</span>
        <div class="flex items-center gap-2">
          <form method="POST" action="<?php echo e(route('admin.forms.templates.destroy', $template)); ?>"
                onsubmit="return confirm('این قالب حذف شود؟')">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button class="text-xs text-red-400 hover:text-red-600 transition">حذف</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center">
  <svg class="w-10 h-10 mx-auto text-slate-200 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0z"/></svg>
  <p class="text-slate-400 text-sm">هنوز قالب ذخیره‌شده‌ای ندارید.</p>
  <p class="text-slate-400 text-xs mt-1">در سازنده فرم، گزینه «ذخیره به عنوان قالب» را انتخاب کنید.</p>
</div>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make(config('forms.layouts.admin'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\forms\src\Providers/../../resources/views/admin/forms/templates/index.blade.php ENDPATH**/ ?>