<?php $__env->startSection('header', ''); ?>

<?php $__env->startPush('head'); ?>
<style>
  #forms-builder { height: calc(100vh - 64px); overflow: hidden; }
  .builder-palette { width: 272px; min-width: 272px; }
  .builder-inspector { width: 340px; min-width: 340px; }
  .builder-canvas { flex: 1; min-width: 0; }
  .field-card.sortable-ghost { opacity: .35; background: #eef2ff; }
  .field-card.sortable-drag { box-shadow: 0 20px 60px -10px rgba(79,70,229,.35); }
  .palette-item { cursor: grab; user-select: none; }
  .palette-item:active { cursor: grabbing; }
  .drop-hint { border: 2px dashed #c7d2fe; border-radius: .75rem; transition: all .15s; }
  .drop-hint.drag-over { border-color: #6366f1; background: #eef2ff; }
  .inspector-tab.active { border-bottom: 2px solid #6366f1; color: #4f46e5; font-weight: 600; }
  [x-cloak] { display: none !important; }
  .field-card { transition: box-shadow .15s, background .15s; }
  .field-card:hover { box-shadow: 0 2px 12px rgba(79,70,229,.12); }
  .field-card.selected { box-shadow: 0 0 0 2px #6366f1, 0 4px 16px rgba(79,70,229,.15); }
  .toast-enter { animation: toastIn .25s ease; }
  @keyframes toastIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform:translateY(0); } }
  .autosave-pulse { animation: pulse 1.5s ease-in-out infinite; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
  .canvas-grid { background-image: radial-gradient(circle,#e5e7eb 1px, transparent 1px); background-size: 24px 24px; }
  .width-btn.active { background: #eef2ff; color: #4f46e5; border-color: #a5b4fc; }
  .star-rating input[type=radio] { display: none; }
  .star-rating label { cursor: pointer; font-size: 1.4rem; color: #d1d5db; }
  .star-rating:hover label, .star-rating input:checked ~ label { color: #f59e0b; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div id="forms-builder" class="flex flex-col bg-slate-50" x-data="formBuilder(<?php echo json_encode($formData, 15, 512) ?>)" x-cloak>

  
  <div class="flex items-center gap-3 px-4 h-14 bg-white border-b border-slate-200 shadow-sm shrink-0 z-10">

    
    <a href="<?php echo e(route('admin.forms.index')); ?>" class="flex items-center gap-1.5 text-slate-500 hover:text-slate-800 text-sm transition mr-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
      فرم‌ها
    </a>

    <div class="w-px h-5 bg-slate-200 mx-1"></div>

    
    <div class="flex items-center gap-2 min-w-0">
      <input type="text" x-model="form.title"
             @change="autosave"
             class="font-semibold text-slate-800 text-sm bg-transparent border-0 border-b-2 border-transparent hover:border-slate-200 focus:border-indigo-400 outline-none px-0.5 py-0.5 min-w-[160px] max-w-[320px] transition"
             placeholder="نام فرم...">
    </div>

    
    <button @click="toggleFormStatus"
            :class="form.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
            class="px-3 py-1 rounded-full text-xs font-medium transition flex items-center gap-1.5">
      <span class="w-1.5 h-1.5 rounded-full" :class="form.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
      <span x-text="form.is_active ? 'فعال' : 'غیرفعال'"></span>
    </button>

    
    <div class="flex items-center gap-1.5 text-xs transition" :class="{
      'text-slate-400': saveStatus === 'saved',
      'text-indigo-500': saveStatus === 'saving',
      'text-amber-500': saveStatus === 'unsaved'
    }">
      <template x-if="saveStatus === 'saving'">
        <svg class="w-3 h-3 autosave-pulse" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
      </template>
      <template x-if="saveStatus === 'saved'">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
      </template>
      <template x-if="saveStatus === 'unsaved'">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
      </template>
      <span x-text="{saved: 'ذخیره شد', saving: 'در حال ذخیره...', unsaved: 'تغییرات ذخیره نشده'}[saveStatus]"></span>
    </div>

    <div class="flex-1"></div>

    
    <span class="text-xs text-slate-400" x-text="`${fields.length} فیلد`"></span>

    
    <div class="flex items-center gap-0.5">
      <button @click="undo" :disabled="historyIndex <= 0"
              class="p-1.5 rounded hover:bg-slate-100 disabled:opacity-30 transition" title="برگشت (Ctrl+Z)">
        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
      </button>
      <button @click="redo" :disabled="historyIndex >= history.length - 1"
              class="p-1.5 rounded hover:bg-slate-100 disabled:opacity-30 transition" title="انجام دوباره (Ctrl+Y)">
        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3"/></svg>
      </button>
    </div>

    <div class="w-px h-5 bg-slate-200"></div>

    
    <a href="<?php echo e(route('admin.forms.preview', $form)); ?>" target="_blank"
       class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg>
      پیش‌نمایش
    </a>

    
    <a href="<?php echo e(route('admin.forms.analytics', $form)); ?>"
       class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/></svg>
      آمار
    </a>

    
    <a href="<?php echo e(route('admin.forms.submissions.index', ['form_id' => $form->id])); ?>"
       class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75z"/></svg>
      ارسال‌ها
    </a>

    
    <button @click="saveAll"
            class="flex items-center gap-1.5 px-4 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 active:scale-95 transition font-medium shadow-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
      ذخیره
    </button>
  </div>

  
  <div class="flex flex-1 overflow-hidden">

    
    <div class="builder-palette bg-slate-800 flex flex-col border-r border-slate-700 overflow-hidden">

      
      <div class="p-3 border-b border-slate-700 shrink-0">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2 px-1">فیلدها</p>
        <div class="relative">
          <svg class="w-4 h-4 text-slate-500 absolute right-2.5 top-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z"/></svg>
          <input type="text" x-model="paletteSearch" placeholder="جستجوی فیلد..."
                 class="w-full bg-slate-700 border border-slate-600 rounded-lg pl-3 pr-8 py-1.5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
      </div>

      
      <div class="flex-1 overflow-y-auto p-3 space-y-4" id="palette-list">
        <?php $__currentLoopData = config('forms.field_type_categories', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div x-show="categoryVisible(<?php echo \Illuminate\Support\Js::from($cat['types'])->toHtml() ?>, paletteSearch)">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2 px-1"><?php echo e($cat['label']); ?></p>
            <div class="space-y-1">
              <?php $__currentLoopData = $cat['types']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="palette-item flex items-center gap-2.5 px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 border border-slate-600 hover:border-slate-500 transition group"
                     draggable="true"
                     data-field-type="<?php echo e($type); ?>"
                     @dragstart="onPaletteDragStart($event, '<?php echo e($type); ?>')"
                     @click="quickAddField('<?php echo e($type); ?>')"
                     x-show="fieldTypeVisible('<?php echo e($type); ?>', paletteSearch)">
                  <span class="text-base leading-none"><?php echo $__env->make('forms::admin.forms._partials.field-icon', ['type' => $type], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?></span>
                  <span class="text-sm text-slate-200 group-hover:text-white transition"><?php echo e(config("forms.field_types.{$type}", $type)); ?></span>
                  <svg class="w-3.5 h-3.5 text-slate-500 mr-auto opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>

      
      <div class="p-3 border-t border-slate-700 shrink-0">
        <button @click="showTemplateModal = true"
                class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25z"/></svg>
          از قالب شروع کنید
        </button>
      </div>
    </div>

    
    <div class="builder-canvas bg-slate-100 overflow-y-auto canvas-grid"
         @dragover.prevent="onCanvasDragOver($event)"
         @dragleave="onCanvasDragLeave"
         @drop.prevent="onCanvasDrop($event)">

      <div class="max-w-2xl mx-auto py-8 px-6">

        
        <div class="mb-6 text-center">
          <p class="text-xs text-slate-400 mb-1">عنوان فرم (در سایت نمایش داده می‌شود)</p>
          <h2 class="text-xl font-bold text-slate-700" x-text="form.title || 'نام فرم...'"></h2>
        </div>

        
        <div id="fields-canvas" class="space-y-3">
          <template x-for="field in fields" :key="field.id">
            <div class="field-card bg-white rounded-xl border border-slate-200 p-4 cursor-pointer"
                 :class="{'selected': selectedFieldId === field.id, 'opacity-50': !field.is_active}"
                 :data-field-id="field.id"
                 @click="selectField(field.id)">

              
              <div class="flex items-start gap-3">
                <span class="cursor-grab mt-0.5 text-slate-300 hover:text-slate-400 transition drag-handle" aria-hidden="true">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8.5 6a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm0 5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm0 5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm7-10a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm0 5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm0 5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>
                </span>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-medium text-slate-800 text-sm" x-text="field.label || '(بدون عنوان)'"></span>
                    <span x-show="field.is_required" class="text-red-500 text-xs font-bold">*الزامی</span>
                    <span x-show="field.conditions && field.conditions.rules && field.conditions.rules.length > 0"
                          class="text-xs px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded-full border border-purple-200">شرطی</span>
                  </div>

                  
                  <div class="mt-1">
                    <template x-if="field.type === 'section_header'">
                      <div class="flex items-center gap-3">
                        <div class="flex-1 h-px bg-slate-200"></div>
                        <span class="text-xs text-slate-400 font-medium" x-text="field.label"></span>
                        <div class="flex-1 h-px bg-slate-200"></div>
                      </div>
                    </template>
                    <template x-if="field.type !== 'section_header'">
                      <div class="bg-slate-50 rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-400 pointer-events-none"
                           x-text="field.placeholder || getFieldTypeLabel(field.type)"></div>
                    </template>
                  </div>

                  <p x-show="field.help_text" class="text-xs text-slate-400 mt-1" x-text="field.help_text"></p>
                </div>

                
                <div class="flex items-center gap-1.5 shrink-0">
                  <span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full border border-indigo-100"
                        x-text="getFieldTypeLabel(field.type)"></span>

                  <button @click.stop="duplicateField(field.id)"
                          class="p-1 rounded hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition" title="کپی">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
                  </button>

                  <button @click.stop="deleteField(field.id)"
                          class="p-1 rounded hover:bg-red-50 text-slate-400 hover:text-red-500 transition" title="حذف">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
                  </button>
                </div>
              </div>
            </div>
          </template>

          
          <div id="drop-zone"
               class="drop-hint p-6 text-center text-sm text-slate-400"
               :class="{'drag-over': isDraggingOver}"
               @dragover.prevent="isDraggingOver = true"
               @dragleave="isDraggingOver = false"
               @drop.prevent="onDropToCanvas($event)">
            <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <template x-if="fields.length === 0">
              <div>
                <p class="font-medium text-slate-500 mb-1">فیلدی وجود ندارد</p>
                <p>یک فیلد از پنل چپ بکشید یا کلیک کنید</p>
              </div>
            </template>
            <template x-if="fields.length > 0">
              <p>فیلد جدید اینجا رها کنید</p>
            </template>
          </div>
        </div>

        
        <div class="mt-6 text-center">
          <div class="inline-flex items-center gap-2 bg-indigo-600 text-white px-6 py-2.5 rounded-xl text-sm font-medium cursor-default opacity-80">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12zm0 0h7.5"/></svg>
            <span x-text="form.settings && form.settings.submit_button_text ? form.settings.submit_button_text : 'ارسال فرم'"></span>
          </div>
          <p class="text-xs text-slate-400 mt-2">پیش‌نمایش دکمه ارسال</p>
        </div>
      </div>
    </div>

    
    <div class="builder-inspector bg-white border-r border-slate-200 flex flex-col overflow-hidden" dir="rtl">

      <template x-if="selectedField">
        <div class="flex flex-col h-full">
          
          <div class="px-4 py-3 border-b border-slate-100 shrink-0">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm font-semibold text-slate-700" x-text="selectedField.label || 'فیلد انتخابی'"></span>
              <button @click="selectedFieldId = null" class="p-1 rounded hover:bg-slate-100 text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <span class="text-xs text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full" x-text="getFieldTypeLabel(selectedField.type)"></span>
          </div>

          
          <div class="flex border-b border-slate-100 text-xs shrink-0 overflow-x-auto">
            <?php $__currentLoopData = [['general','عمومی'],['validation','اعتبارسنجی'],['appearance','ظاهر'],['logic','منطق'],['advanced','پیشرفته']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$tab, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button @click="activeTab = '<?php echo e($tab); ?>'"
                    :class="activeTab === '<?php echo e($tab); ?>' ? 'inspector-tab active' : 'text-slate-500 hover:text-slate-700'"
                    class="px-3 py-2.5 whitespace-nowrap transition border-b-2 border-transparent"><?php echo e($label); ?></button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>

          
          <div class="flex-1 overflow-y-auto p-4 space-y-4">

            
            <div x-show="activeTab === 'general'" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">عنوان فیلد <span class="text-red-500">*</span></label>
                <input type="text" x-model="selectedField.label"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                  نام فنی (name)
                  <span class="text-slate-400 font-normal">— برای ارتباط با سیستم‌ها</span>
                </label>
                <input type="text" x-model="selectedField.name"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       dir="ltr" placeholder="field_name">
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">متن راهنما (Placeholder)</label>
                <input type="text" x-model="selectedField.placeholder"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">متن کمکی (Help Text)</label>
                <textarea x-model="selectedField.help_text"
                          @input="debouncedFieldSave" rows="2"
                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none resize-none"></textarea>
              </div>

              
              <div x-show="['select','radio','checkbox'].includes(selectedField.type)">
                <label class="block text-xs font-medium text-slate-600 mb-1">گزینه‌ها <span class="text-slate-400">(هر خط یک گزینه)</span></label>
                <textarea :value="(selectedField.options || []).join('\n')"
                          @change="updateOptions($event)"
                          rows="5"
                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none resize-none"
                          placeholder="گزینه ۱&#10;گزینه ۲&#10;گزینه ۳"></textarea>
              </div>

              <label class="flex items-center gap-2 cursor-pointer">
                <div class="relative">
                  <input type="checkbox" x-model="selectedField.is_required" @change="debouncedFieldSave" class="sr-only">
                  <div class="w-9 h-5 rounded-full transition" :class="selectedField.is_required ? 'bg-indigo-600' : 'bg-slate-200'"></div>
                  <div class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow transition" :class="selectedField.is_required ? 'translate-x-[-16px]' : ''"></div>
                </div>
                <span class="text-sm text-slate-700">فیلد الزامی است</span>
              </label>

              <label class="flex items-center gap-2 cursor-pointer">
                <div class="relative">
                  <input type="checkbox" x-model="selectedField.is_active" @change="debouncedFieldSave" class="sr-only">
                  <div class="w-9 h-5 rounded-full transition" :class="selectedField.is_active ? 'bg-emerald-500' : 'bg-slate-200'"></div>
                  <div class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow transition" :class="selectedField.is_active ? 'translate-x-[-16px]' : ''"></div>
                </div>
                <span class="text-sm text-slate-700">فیلد فعال باشد</span>
              </label>
            </div>

            
            <div x-show="activeTab === 'validation'" class="space-y-4">
              <div x-show="['text','textarea','email','phone'].includes(selectedField.type)">
                <label class="block text-xs font-medium text-slate-600 mb-1">حداقل طول</label>
                <input type="number" min="0"
                       :value="selectedField.validation_rules && selectedField.validation_rules.min_length"
                       @input="updateValidationRule('min_length', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
              </div>
              <div x-show="['text','textarea','email','phone'].includes(selectedField.type)">
                <label class="block text-xs font-medium text-slate-600 mb-1">حداکثر طول</label>
                <input type="number" min="0"
                       :value="selectedField.validation_rules && selectedField.validation_rules.max_length"
                       @input="updateValidationRule('max_length', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
              </div>
              <div x-show="selectedField.type === 'number'">
                <label class="block text-xs font-medium text-slate-600 mb-1">حداقل مقدار</label>
                <input type="number"
                       :value="selectedField.validation_rules && selectedField.validation_rules.min"
                       @input="updateValidationRule('min', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
              </div>
              <div x-show="selectedField.type === 'number'">
                <label class="block text-xs font-medium text-slate-600 mb-1">حداکثر مقدار</label>
                <input type="number"
                       :value="selectedField.validation_rules && selectedField.validation_rules.max"
                       @input="updateValidationRule('max', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">پیام خطای سفارشی</label>
                <input type="text"
                       :value="selectedField.validation_rules && selectedField.validation_rules.custom_message"
                       @input="updateValidationRule('custom_message', $event.target.value)"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       placeholder="این فیلد اشتباه است...">
              </div>
            </div>

            
            <div x-show="activeTab === 'appearance'" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-2">عرض فیلد</label>
                <div class="flex gap-2">
                  <button @click="selectedField.column_span = 4; debouncedFieldSave()"
                          :class="selectedField.column_span === 4 ? 'width-btn active' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="width-btn flex-1 px-2 py-2 rounded-lg border text-xs font-medium transition">
                    <div class="flex gap-0.5 justify-center mb-1">
                      <div class="w-2 h-3 bg-current rounded-sm"></div>
                    </div>
                    یک سوم
                  </button>
                  <button @click="selectedField.column_span = 6; debouncedFieldSave()"
                          :class="selectedField.column_span === 6 ? 'width-btn active' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="width-btn flex-1 px-2 py-2 rounded-lg border text-xs font-medium transition">
                    <div class="flex gap-0.5 justify-center mb-1">
                      <div class="w-3 h-3 bg-current rounded-sm"></div>
                    </div>
                    نیمه
                  </button>
                  <button @click="selectedField.column_span = 12; debouncedFieldSave()"
                          :class="selectedField.column_span === 12 ? 'width-btn active' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="width-btn flex-1 px-2 py-2 rounded-lg border text-xs font-medium transition">
                    <div class="flex gap-0.5 justify-center mb-1">
                      <div class="w-5 h-3 bg-current rounded-sm"></div>
                    </div>
                    کامل
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">کلاس CSS سفارشی</label>
                <input type="text" x-model="selectedField.css_class"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       dir="ltr" placeholder="my-custom-class">
              </div>
            </div>

            
            <div x-show="activeTab === 'logic'" class="space-y-4">
              <div class="bg-purple-50 border border-purple-200 rounded-xl p-3 text-xs text-purple-700">
                <strong>منطق شرطی:</strong> این فیلد را بر اساس مقدار فیلدهای دیگر نمایش یا مخفی کنید.
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-2">نوع منطق</label>
                <div class="flex gap-2">
                  <button @click="ensureConditions(); selectedField.conditions.logic = 'AND'; debouncedFieldSave()"
                          :class="(!selectedField.conditions || selectedField.conditions.logic !== 'OR') ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-medium transition">AND (همه شرط‌ها)</button>
                  <button @click="ensureConditions(); selectedField.conditions.logic = 'OR'; debouncedFieldSave()"
                          :class="selectedField.conditions && selectedField.conditions.logic === 'OR' ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : 'bg-slate-50 text-slate-600 border-slate-200'"
                          class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-medium transition">OR (هر شرطی)</button>
                </div>
              </div>

              
              <div x-show="selectedField.conditions && selectedField.conditions.rules && selectedField.conditions.rules.length > 0"
                   class="space-y-2">
                <template x-for="(rule, index) in (selectedField.conditions && selectedField.conditions.rules || [])" :key="index">
                  <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-2">
                    <select x-model="rule.field_id" @change="debouncedFieldSave"
                            class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500">
                      <option value="">انتخاب فیلد...</option>
                      <template x-for="f in fields.filter(f => f.id !== selectedField.id)" :key="f.id">
                        <option :value="f.id" x-text="f.label"></option>
                      </template>
                    </select>
                    <select x-model="rule.operator" @change="debouncedFieldSave"
                            class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500">
                      <option value="equals">برابر است با</option>
                      <option value="not_equals">برابر نیست با</option>
                      <option value="contains">شامل می‌شود</option>
                      <option value="is_empty">خالی است</option>
                      <option value="is_not_empty">خالی نیست</option>
                    </select>
                    <div class="flex gap-2">
                      <input x-show="!['is_empty','is_not_empty'].includes(rule.operator)"
                             type="text" x-model="rule.value" @input="debouncedFieldSave"
                             class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500"
                             placeholder="مقدار...">
                      <button @click="removeConditionRule(index)"
                              class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-500 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                      </button>
                    </div>
                  </div>
                </template>
              </div>

              <button @click="addConditionRule"
                      class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-dashed border-slate-300 text-slate-500 text-xs hover:border-indigo-300 hover:text-indigo-600 hover:bg-indigo-50 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                افزودن شرط
              </button>
            </div>

            
            <div x-show="activeTab === 'advanced'" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">مقدار پیش‌فرض</label>
                <input type="text" x-model="selectedField.default_value"
                       @input="debouncedFieldSave"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
              </div>
              <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">
                <strong>نام فیلد:</strong> <span class="font-mono" x-text="'field_' + selectedField.id"></span><br>
                <strong>ID فیلد:</strong> <span class="font-mono" x-text="selectedField.id"></span>
              </div>
            </div>

          </div>

          
          <div class="p-4 border-t border-slate-100 shrink-0">
            <button @click="deleteField(selectedFieldId)"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-red-200 text-red-500 text-sm hover:bg-red-50 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
              حذف این فیلد
            </button>
          </div>
        </div>
      </template>

      
      <template x-if="!selectedField">
        <div class="flex flex-col h-full">
          <div class="px-4 py-3 border-b border-slate-100 shrink-0">
            <p class="text-sm font-semibold text-slate-700">تنظیمات فرم</p>
            <p class="text-xs text-slate-400 mt-0.5">یک فیلد را از canvas انتخاب کنید تا ویژگی‌های آن نمایش داده شود.</p>
          </div>
          <div class="flex-1 overflow-y-auto p-4 space-y-4">

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">عنوان فرم</label>
              <input type="text" x-model="form.title" @change="autosave"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">نامک (Slug)</label>
              <input type="text" x-model="form.slug" @change="autosave"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr">
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">پیام موفقیت</label>
              <textarea x-model="successMessage" @change="autosave" rows="2"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none resize-none"
                        placeholder="فرم با موفقیت ارسال شد."></textarea>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">متن دکمه ارسال</label>
              <input type="text" x-model="submitButtonText" @change="autosave"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                     placeholder="ارسال فرم">
            </div>

            
            <div class="pt-2 border-t border-slate-100 space-y-2">
              <p class="text-xs font-medium text-slate-500 mb-2">لینک‌های سریع</p>
              <a href="<?php echo e(route('admin.forms.automation.index', $form)); ?>"
                 class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-indigo-600 transition text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5 10.5 7l3.75 3.75L21 4.5M3.75 19.5h16.5"/></svg>
                خودکارسازی و اعلان‌ها
              </a>
              <?php if (\Illuminate\Support\Facades\Blade::check('platform')): ?>
              <a href="<?php echo e(route('admin.forms.sites.edit', $form)); ?>"
                 class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-indigo-600 transition text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.86-2.571a4.5 4.5 0 0 0-1.242-7.244l-4.5-4.5a4.5 4.5 0 0 0-6.364 6.364L4.34 8.688"/></svg>
                اتصال به سایت‌ها
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>

  
  <div x-show="showTemplateModal"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @click.self="showTemplateModal = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-800">انتخاب قالب</h3>
        <button @click="showTemplateModal = false" class="p-1 rounded hover:bg-slate-100 text-slate-400">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tpl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <button @click="applyBuiltInTemplate(<?php echo \Illuminate\Support\Js::from($tpl)->toHtml() ?>)"
                class="text-right flex flex-col gap-2 p-4 rounded-xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 transition group">
          <span class="font-semibold text-slate-800 group-hover:text-indigo-700"><?php echo e($tpl['title']); ?></span>
          <span class="text-xs text-slate-500"><?php echo e($tpl['description']); ?></span>
          <span class="text-xs text-indigo-500"><?php echo e(count($tpl['fields'])); ?> فیلد</span>
        </button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>

  
  <div class="fixed bottom-6 left-6 z-50 space-y-2" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
      <div class="toast-enter flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-lg text-sm max-w-xs"
           :class="{
             'bg-emerald-600 text-white': toast.type === 'success',
             'bg-red-600 text-white': toast.type === 'error',
             'bg-slate-800 text-white': toast.type === 'info',
           }">
        <template x-if="toast.type === 'success'">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
        </template>
        <template x-if="toast.type === 'error'">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
        </template>
        <span x-text="toast.message"></span>
      </div>
    </template>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('formBuilder', (initialData) => ({
    form:             { ...initialData, settings: initialData.settings || {} },
    fields:           initialData.fields || [],
    selectedFieldId:  null,
    activeTab:        'general',
    saveStatus:       'saved',
    history:          [],
    historyIndex:     -1,
    paletteSearch:    '',
    isDraggingOver:   false,
    draggingType:     null,
    showTemplateModal: false,
    toasts:           [],
    _saveTimer:       null,
    _fieldSaveTimer:  null,
    sortableInstance: null,

    get selectedField() {
      if (! this.selectedFieldId) return null;
      return this.fields.find(f => f.id === this.selectedFieldId) || null;
    },
    get successMessage() {
      return this.form.settings && this.form.settings.success_message ? this.form.settings.success_message : '';
    },
    set successMessage(v) {
      if (! this.form.settings) this.form.settings = {};
      this.form.settings.success_message = v;
    },
    get submitButtonText() {
      return this.form.settings && this.form.settings.submit_button_text ? this.form.settings.submit_button_text : '';
    },
    set submitButtonText(v) {
      if (! this.form.settings) this.form.settings = {};
      this.form.settings.submit_button_text = v;
    },

    init() {
      this.$nextTick(() => this.initSortable());
      this.pushHistory();
      document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); this.undo(); }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); this.redo(); }
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); this.saveAll(); }
      });
    },

    initSortable() {
      const canvas = document.getElementById('fields-canvas');
      if (! canvas || typeof Sortable === 'undefined') return;
      this.sortableInstance = new Sortable(canvas, {
        animation: 180,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        filter: '#drop-zone',
        onEnd: (evt) => {
          if (evt.oldIndex === undefined || evt.newIndex === undefined) return;
          const item = this.fields.splice(evt.oldIndex, 1)[0];
          this.fields.splice(evt.newIndex, 0, item);
          this.persistSort();
          this.pushHistory();
        },
      });
    },

    getFieldTypeLabel(type) {
      const labels = <?php echo json_encode(config('forms.field_types'), 15, 512) ?>;
      return labels[type] || type;
    },

    fieldTypeVisible(type, query) {
      if (! query) return true;
      const label = this.getFieldTypeLabel(type).toLowerCase();
      return label.includes(query.toLowerCase()) || type.includes(query.toLowerCase());
    },

    categoryVisible(types, query) {
      if (! query) return true;
      return types.some(t => this.fieldTypeVisible(t, query));
    },

    selectField(id) {
      this.selectedFieldId = id;
      this.activeTab = 'general';
    },

    async quickAddField(type) {
      await this.addField({ type });
    },

    onPaletteDragStart(event, type) {
      this.draggingType = type;
      event.dataTransfer.setData('text/plain', type);
      event.dataTransfer.effectAllowed = 'copy';
    },

    onCanvasDragOver(event) {
      if (this.draggingType) { this.isDraggingOver = true; }
    },

    onCanvasDragLeave() {
      this.isDraggingOver = false;
    },

    async onDropToCanvas(event) {
      this.isDraggingOver = false;
      const type = event.dataTransfer.getData('text/plain') || this.draggingType;
      if (type) { await this.addField({ type }); }
      this.draggingType = null;
    },

    onCanvasDrop(event) { this.onDropToCanvas(event); },

    async addField(data) {
      this.saveStatus = 'saving';
      try {
        const resp = await fetch(`<?php echo e(route('admin.forms.builder.fields.store', $form)); ?>`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ ...data, position: this.fields.length }),
        });
        const json = await resp.json();
        if (! resp.ok) throw new Error(json.message || 'Error');
        this.fields.push(json.field);
        this.selectField(json.field.id);
        this.saveStatus = 'saved';
        this.pushHistory();
        this.$nextTick(() => this.initSortable());
        this.showToast('فیلد اضافه شد', 'success');
      } catch (e) {
        this.saveStatus = 'unsaved';
        this.showToast('خطا در افزودن فیلد', 'error');
      }
    },

    debouncedFieldSave() {
      this.saveStatus = 'unsaved';
      clearTimeout(this._fieldSaveTimer);
      this._fieldSaveTimer = setTimeout(() => this.saveCurrentField(), 800);
    },

    async saveCurrentField() {
      const field = this.selectedField;
      if (! field) return;
      this.saveStatus = 'saving';
      try {
        const resp = await fetch(`/admin/forms/<?php echo e($form->id); ?>/builder/fields/${field.id}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify(field),
        });
        const json = await resp.json();
        if (! resp.ok) throw new Error(json.message || 'Error');
        const idx = this.fields.findIndex(f => f.id === field.id);
        if (idx >= 0) this.fields[idx] = json.field;
        this.saveStatus = 'saved';
        this.pushHistory();
      } catch (e) {
        this.saveStatus = 'unsaved';
      }
    },

    autosave() {
      this.saveStatus = 'unsaved';
      clearTimeout(this._saveTimer);
      this._saveTimer = setTimeout(() => this.saveFormSettings(), 1000);
    },

    async saveFormSettings() {
      this.saveStatus = 'saving';
      try {
        const resp = await fetch(`<?php echo e(route('admin.forms.builder.settings', $form)); ?>`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            title: this.form.title,
            slug: this.form.slug,
            is_active: this.form.is_active,
            success_message: this.successMessage,
            submit_button_text: this.submitButtonText,
          }),
        });
        if (! resp.ok) throw new Error();
        this.saveStatus = 'saved';
      } catch (e) {
        this.saveStatus = 'unsaved';
      }
    },

    async saveAll() {
      if (this.selectedField) await this.saveCurrentField();
      await this.saveFormSettings();
      this.showToast('همه تغییرات ذخیره شد', 'success');
    },

    async deleteField(id) {
      if (! confirm('این فیلد حذف شود؟')) return;
      const idx = this.fields.findIndex(f => f.id === id);
      if (idx < 0) return;
      this.fields.splice(idx, 1);
      if (this.selectedFieldId === id) this.selectedFieldId = null;
      try {
        await fetch(`/admin/forms/<?php echo e($form->id); ?>/builder/fields/${id}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        });
        this.pushHistory();
        this.showToast('فیلد حذف شد', 'info');
      } catch (e) {
        this.showToast('خطا در حذف فیلد', 'error');
      }
    },

    async duplicateField(id) {
      try {
        const resp = await fetch(`/admin/forms/<?php echo e($form->id); ?>/builder/fields/${id}/duplicate`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        });
        const json = await resp.json();
        if (! resp.ok) throw new Error();
        this.fields.push(json.field);
        this.selectField(json.field.id);
        this.pushHistory();
        this.$nextTick(() => this.initSortable());
        this.showToast('فیلد کپی شد', 'success');
      } catch (e) {
        this.showToast('خطا در کپی فیلد', 'error');
      }
    },

    async persistSort() {
      const order = this.fields.map(f => f.id);
      await fetch(`<?php echo e(route('admin.forms.builder.sort', $form)); ?>`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ order }),
      });
    },

    toggleFormStatus() {
      this.form.is_active = ! this.form.is_active;
      this.autosave();
    },

    updateOptions(event) {
      const lines = event.target.value.split('\n').map(l => l.trim()).filter(l => l.length > 0);
      if (this.selectedField) {
        this.selectedField.options = lines;
        this.debouncedFieldSave();
      }
    },

    updateValidationRule(key, value) {
      if (! this.selectedField) return;
      if (! this.selectedField.validation_rules) this.selectedField.validation_rules = {};
      this.selectedField.validation_rules[key] = value;
      this.debouncedFieldSave();
    },

    ensureConditions() {
      if (! this.selectedField) return;
      if (! this.selectedField.conditions) this.selectedField.conditions = { logic: 'AND', rules: [] };
      if (! this.selectedField.conditions.rules) this.selectedField.conditions.rules = [];
    },

    addConditionRule() {
      this.ensureConditions();
      this.selectedField.conditions.rules.push({ field_id: '', operator: 'equals', value: '' });
    },

    removeConditionRule(index) {
      if (! this.selectedField || ! this.selectedField.conditions) return;
      this.selectedField.conditions.rules.splice(index, 1);
      this.debouncedFieldSave();
    },

    applyBuiltInTemplate(template) {
      if (! confirm('این قالب فیلدهای فعلی را جایگزین می‌کند. ادامه می‌دهید؟')) return;
      this.showTemplateModal = false;
      fetch(`<?php echo e(route('admin.forms.templates.apply', $form)); ?>`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ template_id: 'builtin', template_data: template }),
      }).then(() => {
        this.showToast('قالب اعمال شد — صفحه بارگذاری مجدد می‌شود...', 'info');
        setTimeout(() => window.location.reload(), 1500);
      });
    },

    pushHistory() {
      if (this.historyIndex < this.history.length - 1) {
        this.history = this.history.slice(0, this.historyIndex + 1);
      }
      this.history.push(JSON.parse(JSON.stringify(this.fields)));
      if (this.history.length > 50) this.history.shift();
      this.historyIndex = this.history.length - 1;
    },

    undo() {
      if (this.historyIndex <= 0) return;
      this.historyIndex--;
      this.fields = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
      this.showToast('بازگشت انجام شد', 'info');
    },

    redo() {
      if (this.historyIndex >= this.history.length - 1) return;
      this.historyIndex++;
      this.fields = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
      this.showToast('انجام دوباره', 'info');
    },

    showToast(message, type = 'info') {
      const id = Date.now();
      this.toasts.push({ id, message, type });
      setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3000);
    },
  }));
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(config('forms.layouts.admin'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\forms\src\Providers/../../resources/views/admin/forms/builder.blade.php ENDPATH**/ ?>