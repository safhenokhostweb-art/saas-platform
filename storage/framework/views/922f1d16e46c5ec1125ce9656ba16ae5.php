<?php $__env->startSection('header', 'مدیریت ارسال‌ها'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="submissionsManager()" x-cloak>

  
  <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
      <p class="text-slate-500 text-sm">مشاهده و مدیریت تمام ارسال‌های فرم‌ها</p>
    </div>
    <div class="flex items-center gap-2">
      
      <form method="GET" action="<?php echo e(route('admin.forms.submissions.export')); ?>" id="export-form">
        <input type="hidden" name="form_id"  value="<?php echo e(request('form_id')); ?>">
        <input type="hidden" name="site_id"  value="<?php echo e(request('site_id')); ?>">
        <input type="hidden" name="from"     value="<?php echo e(request('from')); ?>">
        <input type="hidden" name="to"       value="<?php echo e(request('to')); ?>">
        <input type="hidden" name="q"        value="<?php echo e(request('q')); ?>">
        <button type="submit"
                class="flex items-center gap-2 px-4 py-2 rounded-xl border border-indigo-200 text-indigo-600 text-sm hover:bg-indigo-50 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
          </svg>
          خروجی CSV
        </button>
      </form>
    </div>
  </div>

  
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
    <form method="GET" action="<?php echo e(route('admin.forms.submissions.index')); ?>"
          class="flex flex-wrap items-end gap-3">

      <div class="min-w-0">
        <label class="block text-xs font-medium text-slate-500 mb-1">جستجو</label>
        <div class="relative">
          <svg class="w-4 h-4 text-slate-400 absolute right-3 top-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z"/></svg>
          <input type="text" name="q" value="<?php echo e(request('q')); ?>"
                 placeholder="IP، نام، ایمیل..."
                 class="border border-slate-200 rounded-xl pr-9 pl-4 py-2 text-sm w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">فرم</label>
        <select name="form_id"
                class="border border-slate-200 rounded-xl px-3 py-2 text-sm w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
          <option value="">همه فرم‌ها</option>
          <?php $__currentLoopData = $forms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($f->id); ?>" <?php echo e(request('form_id') == $f->id ? 'selected' : ''); ?>><?php echo e($f->title); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>

      <?php if (\Illuminate\Support\Facades\Blade::check('platform')): ?>
      <?php if(isset($sites) && $sites->count()): ?>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">سایت</label>
        <select name="site_id"
                class="border border-slate-200 rounded-xl px-3 py-2 text-sm w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
          <option value="">همه سایت‌ها</option>
          <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($s->id); ?>" <?php echo e(request('site_id') == $s->id ? 'selected' : ''); ?>><?php echo e($s->title ?: $s->domain); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">از تاریخ</label>
        <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'from','placeholder' => '۱۴۰۳/۰۱/۰۱','value' => request('from'),'class' => 'border border-slate-200 rounded-xl px-3 py-2 text-sm w-40 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'from','placeholder' => '۱۴۰۳/۰۱/۰۱','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request('from')),'class' => 'border border-slate-200 rounded-xl px-3 py-2 text-sm w-40 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none']); ?>
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
        <label class="block text-xs font-medium text-slate-500 mb-1">تا تاریخ</label>
        <?php if (isset($component)) { $__componentOriginale306a0c31e413f34dfe42b38d8fcaf16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale306a0c31e413f34dfe42b38d8fcaf16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.persian-date-input','data' => ['name' => 'to','placeholder' => '۱۴۰۳/۱۲/۲۹','value' => request('to'),'class' => 'border border-slate-200 rounded-xl px-3 py-2 text-sm w-40 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('persian-date-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'to','placeholder' => '۱۴۰۳/۱۲/۲۹','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request('to')),'class' => 'border border-slate-200 rounded-xl px-3 py-2 text-sm w-40 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none']); ?>
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

      <div class="flex items-center gap-2">
        <button type="submit"
                class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-xl hover:bg-indigo-700 active:scale-95 transition">
          فیلتر
        </button>
        <a href="<?php echo e(route('admin.forms.submissions.index')); ?>"
           class="px-4 py-2 border border-slate-200 text-slate-600 text-sm rounded-xl hover:bg-slate-50 transition">
          پاک کردن
        </a>
      </div>
    </form>
  </div>

  
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
      <p class="text-2xl font-bold text-indigo-600"><?php echo e($submissions->total()); ?></p>
      <p class="text-xs text-slate-500 mt-0.5">ارسال فیلترشده</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
      <p class="text-2xl font-bold text-slate-700"><?php echo e($forms->count()); ?></p>
      <p class="text-xs text-slate-500 mt-0.5">تعداد فرم‌ها</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
      <p class="text-2xl font-bold text-emerald-600"><?php echo e(number_format($submissions->perPage())); ?></p>
      <p class="text-xs text-slate-500 mt-0.5">در هر صفحه</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
      <p class="text-2xl font-bold text-slate-700"><?php echo e($submissions->lastPage()); ?></p>
      <p class="text-xs text-slate-500 mt-0.5">صفحه</p>
    </div>
  </div>

  
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

    
    <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs text-slate-500">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
      برای تغییر ترتیب ستون‌ها، هدر جدول را بکشید.
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm text-right border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200" id="submission-header-row">
            <th class="p-3 font-semibold text-slate-500 w-8">
              <input type="checkbox" class="rounded border-slate-300" @change="toggleSelectAll">
            </th>
            <?php $__currentLoopData = $columnOrder; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php if($col === 'id' || $col === 'form' || $col === 'site' || $col === 'date' || $col === 'ip' || in_array($col, $dataKeys, true)): ?>
              <th class="p-3 font-semibold text-slate-600 cursor-grab active:cursor-grabbing border-l border-slate-100 select-none hover:bg-slate-100 transition whitespace-nowrap"
                  data-column-id="<?php echo e($col); ?>">
                <div class="flex items-center gap-1.5">
                  <span class="text-slate-300" aria-hidden="true">⠿</span>
                  <?php if($col === 'id'): ?>    #
                  <?php elseif($col === 'form'): ?>  فرم
                  <?php elseif($col === 'site'): ?>  سایت
                  <?php elseif($col === 'date'): ?>  تاریخ
                  <?php elseif($col === 'ip'): ?>    IP
                  <?php else: ?> <?php echo e($col); ?>

                  <?php endif; ?>
                </div>
              </th>
              <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <th class="p-3 font-semibold text-slate-500 text-center w-16">عملیات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php $__empty_1 = true; $__currentLoopData = $submissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr class="hover:bg-slate-50 transition group" :class="{'bg-indigo-50': selectedIds.includes(<?php echo e($item->id); ?>)}" >
            <td class="p-3">
              <input type="checkbox" class="rounded border-slate-300" value="<?php echo e($item->id); ?>"
                     @change="toggleSelect(<?php echo e($item->id); ?>)"
                     :checked="selectedIds.includes(<?php echo e($item->id); ?>)">
            </td>
            <?php $__currentLoopData = $columnOrder; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php if($col === 'id'): ?>
              <td class="p-3 text-slate-500 font-mono text-xs"><?php echo e($item->id); ?></td>
              <?php elseif($col === 'form'): ?>
              <td class="p-3">
                <span class="font-medium text-slate-800"><?php echo e($item->form?->title ?? '—'); ?></span>
              </td>
              <?php elseif($col === 'site'): ?>
              <td class="p-3 text-slate-500 text-xs"><?php echo e($item->site?->title ?? $item->site?->domain ?? '—'); ?></td>
              <?php elseif($col === 'date'): ?>
              <td class="p-3 text-slate-500 text-xs whitespace-nowrap">
                <?php
                    $formatter = config('forms.date_formatter');
                    echo $formatter
                        ? call_user_func($formatter, $item->created_at, 'Y/m/d H:i')
                        : \Illuminate\Support\Carbon::parse($item->created_at)->format('Y/m/d H:i');
                ?>
              </td>
              <?php elseif($col === 'ip'): ?>
              <td class="p-3 text-slate-400 font-mono text-xs"><?php echo e($item->ip ?? '—'); ?></td>
              <?php elseif(in_array($col, $dataKeys, true)): ?>
              <td class="p-3 text-slate-700 max-w-50">
                <span class="truncate block"><?php echo e(is_array($item->data[$col] ?? null) ? implode(', ', $item->data[$col]) : ($item->data[$col] ?? '—')); ?></span>
              </td>
              <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <td class="p-3 text-center">
              <button @click="openDetail(<?php echo e($item->id); ?>)"
                      class="p-1.5 rounded-lg hover:bg-indigo-100 text-slate-400 hover:text-indigo-600 transition opacity-0 group-hover:opacity-100"
                      title="مشاهده جزئیات">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                </svg>
              </button>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="<?php echo e(count($columnOrder) + 2); ?>" class="p-12 text-center">
              <svg class="w-12 h-12 mx-auto text-slate-200 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75z"/></svg>
              <p class="text-slate-400 text-sm">هیچ ارسالی یافت نشد.</p>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
      <p class="text-xs text-slate-400">
        نمایش <?php echo e($submissions->firstItem() ?? 0); ?>–<?php echo e($submissions->lastItem() ?? 0); ?> از <?php echo e($submissions->total()); ?> ارسال
      </p>
      <?php echo e($submissions->links()); ?>

    </div>
  </div>

  
  <div x-show="detailOpen"
       x-transition:enter="transition ease-out duration-250"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-end"
       @click.self="closeDetail()">
    <div x-show="detailOpen"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         class="bg-white w-full max-w-md h-full shadow-2xl flex flex-col overflow-hidden"
         dir="rtl">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-800">جزئیات ارسال</h3>
        <button @click="closeDetail()" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-5">
        <template x-if="detailData">
          <div class="space-y-3">
            <template x-for="[key, value] in Object.entries(detailData.data || {})" :key="key">
              <div class="bg-slate-50 rounded-xl p-3">
                <p class="text-xs font-medium text-slate-500 mb-1" x-text="key"></p>
                <p class="text-sm text-slate-800 wrap-break-word"
                   x-text="Array.isArray(value) ? value.join(', ') : (value || '—')"></p>
              </div>
            </template>
            <div class="border-t border-slate-100 pt-3 mt-3 space-y-2 text-xs text-slate-400">
              <p>فرم: <span class="text-slate-600" x-text="detailData.form_title"></span></p>
              <p>تاریخ: <span class="text-slate-600" x-text="detailData.created_at"></span></p>
              <p>IP: <span class="font-mono text-slate-600" x-text="detailData.ip || '—'"></span></p>
            </div>
          </div>
        </template>
        <template x-if="!detailData">
          <div class="flex items-center justify-center h-32">
            <svg class="w-6 h-6 text-slate-300 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          </div>
        </template>
      </div>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('head'); ?>
<link rel="stylesheet" href="<?php echo e(asset('vendor/persian-datepicker/persian-datepicker.min.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="<?php echo e(asset('vendor/jquery/jquery.min.js')); ?>"></script>
<script src="<?php echo e(asset('vendor/persian-date/persian-date.min.js')); ?>"></script>
<script src="<?php echo e(asset('vendor/persian-datepicker/persian-datepicker.min.js')); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({ format: 'YYYY/MM/DD', persianDigit: true, initialValueType: 'persian', autoClose: true });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const row = document.getElementById('submission-header-row');
    if (row && typeof Sortable !== 'undefined') {
        new Sortable(row, {
            animation: 150,
            handle: 'th[data-column-id]',
            filter: 'th:not([data-column-id])',
            onEnd(ev) {
                const order = [];
                row.querySelectorAll('th[data-column-id]').forEach(th => order.push(th.dataset.columnId));
                fetch('<?php echo e(route("admin.forms.submissions.column-order")); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ order }),
                }).then(() => window.location.reload());
            },
        });
    }
});
</script>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('submissionsManager', () => ({
    selectedIds: [],
    detailOpen:  false,
    detailData:  null,

    toggleSelectAll(e) {
      if (e.target.checked) {
        this.selectedIds = <?php echo json_encode($submissions->pluck('id'), 15, 512) ?>;
      } else {
        this.selectedIds = [];
      }
    },

    toggleSelect(id) {
      if (this.selectedIds.includes(id)) {
        this.selectedIds = this.selectedIds.filter(i => i !== id);
      } else {
        this.selectedIds.push(id);
      }
    },

    async openDetail(id) {
      this.detailOpen = true;
      this.detailData = null;
      const submission = <?php echo json_encode($submissions->items(), 15, 512) ?>.find(s => s.id === id);
      if (submission) {
        this.detailData = {
          data:        submission.data || {},
          form_title:  submission.form ? submission.form.title : '—',
          created_at:  submission.created_at,
          ip:          submission.ip,
        };
      }
    },

    closeDetail() {
      this.detailOpen = false;
      setTimeout(() => { this.detailData = null; }, 300);
    },
  }));
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(config('forms.layouts.admin'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\forms\src\Providers/../../resources/views/admin/forms/submissions/index.blade.php ENDPATH**/ ?>