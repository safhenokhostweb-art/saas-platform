<?php $__env->startSection('header', 'گزارش ارسال فرم‌ها'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6 flex flex-wrap items-end gap-3">
    <form method="GET" action="<?php echo e(route('admin.forms.submissions.index')); ?>" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">جستجو</label>
            <input type="text" name="q" class="border rounded-xl px-4 py-2 w-40 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                   placeholder="فرم، سایت، IP" value="<?php echo e(request('q')); ?>">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">فرم</label>
            <select name="form_id" class="border rounded-xl px-4 py-2 w-48 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <option value="">همه</option>
                <?php $__currentLoopData = $forms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($f->id); ?>" <?php echo e(request('form_id') == $f->id ? 'selected' : ''); ?>><?php echo e($f->title); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <?php if (\Illuminate\Support\Facades\Blade::check('platform')): ?>
        <?php if(isset($sites) && $sites->count()): ?>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">سایت</label>
            <select name="site_id" class="border rounded-xl px-4 py-2 w-48 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <option value="">همه</option>
                <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($s->id); ?>" <?php echo e(request('site_id') == $s->id ? 'selected' : ''); ?>><?php echo e($s->title ?: $s->domain); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">از تاریخ (شمسی)</label>
            <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'from','placeholder' => '۱۴۰۳/۰۱/۰۱','value' => request('from'),'class' => 'border rounded-xl px-4 py-2 w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'from','placeholder' => '۱۴۰۳/۰۱/۰۱','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request('from')),'class' => 'border rounded-xl px-4 py-2 w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none']); ?>
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
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">تا تاریخ (شمسی)</label>
            <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'to','placeholder' => '۱۴۰۳/۱۲/۲۹','value' => request('to'),'class' => 'border rounded-xl px-4 py-2 w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'to','placeholder' => '۱۴۰۳/۱۲/۲۹','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request('to')),'class' => 'border rounded-xl px-4 py-2 w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none']); ?>
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
        <button type="submit"
                class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-xl hover:opacity-90 transition">
            فیلتر
        </button>
        <a href="<?php echo e(route('admin.forms.submissions.index')); ?>"
           class="border border-gray-300 text-gray-700 px-4 py-2 rounded-xl hover:bg-gray-50 transition">
            پاک کردن
        </a>
    </form>
    <form method="GET" action="<?php echo e(route('admin.forms.submissions.export')); ?>" class="mr-auto flex items-end gap-2">
        <input type="hidden" name="form_id" value="<?php echo e(request('form_id')); ?>">
        <input type="hidden" name="site_id" value="<?php echo e(request('site_id')); ?>">
        <input type="hidden" name="from" value="<?php echo e(request('from')); ?>">
        <input type="hidden" name="to" value="<?php echo e(request('to')); ?>">
        <input type="hidden" name="q" value="<?php echo e(request('q')); ?>">
        <button type="submit"
                class="border border-indigo-600 text-indigo-600 px-4 py-2 rounded-xl hover:bg-indigo-50 transition inline-flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
            خروجی اکسل (CSV)
        </button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <p class="text-gray-600 text-sm p-3 border-b bg-amber-50 border-amber-100">
        <span class="inline-flex items-center gap-1 ml-1" aria-hidden="true">⋮⋮</span>
        <strong>درگ اند دراپ:</strong> روی هر سرستون زیر کلیک کرده و بکشید تا ترتیب ستون‌ها را عوض کنید. ترتیب جدید در جدول و خروجی CSV اعمال می‌شود.
    </p>
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200" id="submission-header-row">
                    <?php $__currentLoopData = $columnOrder; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if($col === 'id' || $col === 'form' || $col === 'site' || $col === 'date' || $col === 'ip' || in_array($col, $dataKeys, true)): ?>
                            <th class="p-3 font-bold cursor-grab active:cursor-grabbing border-l border-gray-200 select-none hover:bg-gray-100 transition-colors"
                                data-column-id="<?php echo e($col); ?>" title="کشیدن برای تغییر ترتیب">
                                <span class="text-gray-400 align-middle ml-1" aria-hidden="true">⋮⋮</span>
                                <?php if($col === 'id'): ?> #
                                <?php elseif($col === 'form'): ?> فرم
                                <?php elseif($col === 'site'): ?> سایت
                                <?php elseif($col === 'date'): ?> تاریخ
                                <?php elseif($col === 'ip'): ?> IP
                                <?php else: ?> <?php echo e($col); ?>

                                <?php endif; ?>
                            </th>
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $submissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <?php $__currentLoopData = $columnOrder; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if($col === 'id'): ?>
                                <td class="p-3"><?php echo e($item->id); ?></td>
                            <?php elseif($col === 'form'): ?>
                                <td class="p-3"><?php echo e($item->form?->title ?? '—'); ?></td>
                            <?php elseif($col === 'site'): ?>
                                <td class="p-3"><?php echo e($item->site?->title ?? $item->site?->domain ?? '—'); ?></td>
                            <?php elseif($col === 'date'): ?>
                                <td class="p-3 text-sm"><?php echo e(\App\Helpers\Jalali::formatFa($item->created_at, 'Y/m/d H:i')); ?></td>
                            <?php elseif($col === 'ip'): ?>
                                <td class="p-3"><?php echo e($item->ip ?? '—'); ?></td>
                            <?php elseif(in_array($col, $dataKeys, true)): ?>
                                <td class="p-3 text-sm"><?php echo e(is_array($item->data[$col] ?? null) ? json_encode($item->data[$col]) : ($item->data[$col] ?? '—')); ?></td>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="<?php echo e(count($columnOrder)); ?>" class="p-6 text-center text-gray-500">ارسالی یافت نشد.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-gray-200">
        <?php echo e($submissions->links()); ?>

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
        jQuery('.persian-date-input').persianDatepicker({
            format: 'YYYY/MM/DD',
            persianDigit: true,
            initialValueType: 'persian'
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var row = document.getElementById('submission-header-row');
    if (row && typeof Sortable !== 'undefined') {
        new Sortable(row, {
            animation: 150,
            handle: 'th',
            onEnd: function(ev) {
                var order = [];
                row.querySelectorAll('th[data-column-id]').forEach(function(th) {
                    order.push(th.getAttribute('data-column-id'));
                });
                fetch('<?php echo e(route("admin.forms.submissions.column-order")); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ order: order })
                }).then(function() { window.location.reload(); });
            }
        });
    }
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(config('forms.layouts.admin'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\forms\src\Providers/../../resources/views/admin/forms/submissions/index.blade.php ENDPATH**/ ?>