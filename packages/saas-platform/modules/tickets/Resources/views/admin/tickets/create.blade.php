@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'ثبت تیکت جدید')

@push('head')
<style>
.form-glass-card{background:rgba(255,255,255,0.82);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.3);border-radius:1.25rem;box-shadow:0 8px 32px rgba(15,23,42,0.06)}
.form-glass-header{background:linear-gradient(135deg,rgba(99,102,241,0.06),rgba(168,85,247,0.04));border-bottom:1px solid rgba(255,255,255,0.2);border-radius:1.25rem 1.25rem 0 0;padding:1.25rem 1.75rem}
.form-input{width:100%!important;padding:0.75rem 1rem!important;border:1px solid #e2e8f0!important;border-radius:0.75rem!important;font-size:0.875rem!important;transition:all 0.2s ease;background:rgba(255,255,255,0.6)}
.form-input:focus{outline:none!important;border-color:#a5b4fc!important;box-shadow:0 0 0 3px rgba(99,102,241,0.12)!important}
.form-input:hover{border-color:#c7d2fe!important}
.form-label{display:block!important;font-size:0.8125rem!important;font-weight:600!important;color:#374151!important;margin-bottom:0.5rem!important}
.form-label .required{color:#ef4444;margin-right:2px}
.btn-gradient{display:inline-flex;align-items:center;gap:0.5rem;padding:0.7rem 1.75rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.25s ease;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.25)}
.btn-gradient:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,0.35)}
.btn-ghost{display:inline-flex;align-items:center;gap:0.5rem;padding:0.7rem 1.75rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.2s ease;background:transparent;color:#64748b;border:1.5px solid #e2e8f0;cursor:pointer;text-decoration:none}
.btn-ghost:hover{background:#f8fafc;border-color:#cbd5e1;color:#475569}
/* User selector */
.user-selector-wrap{position:relative}
.user-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:50;background:#fff;border:1px solid #e2e8f0;border-radius:0.75rem;box-shadow:0 8px 24px rgba(15,23,42,0.1);max-height:220px;overflow-y:auto}
.user-option{display:flex;align-items:center;gap:0.625rem;padding:0.625rem 0.875rem;cursor:pointer;transition:background 0.15s}
.user-option:hover,.user-option.active{background:#f5f3ff}
.user-option .u-avatar{width:2rem;height:2rem;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0}
.user-chip{display:inline-flex;align-items:center;gap:0.5rem;background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(168,85,247,0.05));border:1px solid rgba(99,102,241,0.2);border-radius:2rem;padding:0.35rem 0.75rem 0.35rem 0.5rem;font-size:0.8125rem;font-weight:500;color:#4f46e5}
.user-chip .u-avatar-sm{width:1.5rem;height:1.5rem;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.625rem;font-weight:700}
.on-behalf-panel{background:linear-gradient(135deg,rgba(99,102,241,0.05),rgba(168,85,247,0.03));border:1px solid rgba(99,102,241,0.15);border-radius:1rem;padding:1rem 1.25rem}
.internal-note-toggle{display:flex;align-items:center;gap:0.75rem;padding:0.875rem 1rem;border-radius:0.875rem;border:1.5px solid #e2e8f0;cursor:pointer;transition:all 0.2s;background:rgba(255,255,255,0.5)}
.internal-note-toggle.active{border-color:#f59e0b;background:rgba(245,158,11,0.05)}
</style>
@endpush

@section('content')
<div class="max-w-3xl mx-auto"
     x-data="ticketCreate({
         people: {{ json_encode($people) }},
         oldKey: '{{ old('for_user_id') ? 'u_'.old('for_user_id') : (old('for_customer_id') ? 'c_'.old('for_customer_id') : '') }}'
     })">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('tickets.admin.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/70 hover:bg-white border border-gray-200 transition-all hover:shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت به لیست
        </a>
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">ثبت تیکت جدید</h2>
            <p class="text-sm text-gray-500 mt-1">تیکت پشتیبانی جدید ثبت کنید</p>
        </div>
    </div>

    @if($errors->any())
    <div class="mb-6 bg-red-50/85 border border-red-200 rounded-2xl p-4"
         x-data="{ show: true }" x-show="show" x-transition>
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="flex-1">
                <p class="text-sm font-semibold text-red-800 mb-1">{{ count($errors->all()) }} خطا در ارسال فرم</p>
                <ul class="list-disc list-inside space-y-0.5 text-sm text-red-700">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
            <button @click="show = false" class="text-red-400 hover:text-red-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('tickets.admin.store') }}"
          enctype="{{ $attachmentsEnabled ? 'multipart/form-data' : 'application/x-www-form-urlencoded' }}"
          class="form-glass-card overflow-hidden">
        @csrf

        {{-- Card Header --}}
        <div class="form-glass-header">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20 text-lg">🎫</span>
                <div>
                    <h3 class="font-bold text-gray-900">اطلاعات تیکت</h3>
                    <p class="text-xs text-gray-500 mt-0.5">تمام فیلدهای الزامی را پر کنید</p>
                </div>
            </div>
        </div>

        <div class="p-6 sm:p-8 space-y-6">

            {{-- ═══ ON BEHALF OF USER ═══ --}}
            <div class="on-behalf-panel">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span class="text-sm font-semibold text-indigo-800">ثبت تیکت برای کاربر مشخص</span>
                    <span class="text-xs text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full">اختیاری</span>
                </div>

                {{-- Selected chip --}}
                <div x-show="selected" x-transition class="flex items-center gap-3 mb-3">
                    <div class="user-chip">
                        <div class="u-avatar-sm" x-text="selected ? (selected.name || '?').charAt(0).toUpperCase() : ''"></div>
                        <span x-text="selected ? (selected.name || selected.mobile) : ''"></span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full mr-1"
                              :class="selected && selected.type === 'customer' ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700'"
                              x-text="selected ? selected.badge : ''"></span>
                        <span class="text-indigo-400 text-xs" x-text="selected ? (selected.email || selected.mobile) : ''"></span>
                    </div>
                    <button type="button" @click="clearSelected()"
                            class="text-xs text-gray-400 hover:text-red-500 transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        حذف انتخاب
                    </button>
                </div>

                {{-- Search --}}
                <div x-show="!selected" class="user-selector-wrap">
                    <input type="text" x-model="search" @focus="showDrop = true" @input="showDrop = true"
                           @keydown.escape="showDrop = false"
                           class="form-input" placeholder="جستجو بر اساس نام، موبایل یا ایمیل..."
                           autocomplete="off">
                    <div class="user-dropdown" x-show="showDrop && filtered.length > 0"
                         @click.outside="showDrop = false" x-transition>
                        <template x-for="p in filtered" :key="p.key">
                            <div class="user-option" @click="pick(p)">
                                <div class="u-avatar" x-text="(p.name || '?').charAt(0).toUpperCase()"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-800 truncate" x-text="p.name || p.mobile"></span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full shrink-0"
                                              :class="p.type === 'customer' ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700'"
                                              x-text="p.badge"></span>
                                    </div>
                                    <div class="text-xs text-gray-400 truncate" x-text="p.email || p.mobile"></div>
                                </div>
                            </div>
                        </template>
                        <div x-show="filtered.length === 0 && search.length > 0"
                             class="px-4 py-3 text-sm text-gray-400 text-center">موردی یافت نشد</div>
                    </div>
                </div>

                <input type="hidden" name="for_user_id"     :value="selected && selected.type === 'user'     ? selected.user_id     : ''">
                <input type="hidden" name="for_customer_id" :value="selected && selected.type === 'customer' ? selected.customer_id : ''">
                <p class="text-xs text-indigo-400 mt-2">با انتخاب مشتری یا کاربر، اطلاعات به‌صورت خودکار تکمیل می‌شود.</p>
            </div>

            {{-- موضوع --}}
            <div>
                <label class="form-label">موضوع <span class="required">*</span></label>
                <input type="text" name="subject" value="{{ old('subject') }}" required
                       class="form-input @error('subject') !border-red-400 @enderror"
                       placeholder="موضوع تیکت را وارد کنید">
                @error('subject')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- اولویت + سایت --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="form-label">اولویت <span class="required">*</span></label>
                    <select name="priority" class="form-input">
                        <option value="normal" @selected(old('priority', 'normal') === 'normal')>➡️ عادی</option>
                        <option value="high"   @selected(old('priority') === 'high')>🔴 بالا</option>
                        <option value="low"    @selected(old('priority') === 'low')>🔽 کم</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">سایت</label>
                    <select name="site_id" class="form-input">
                        <option value="">— بدون سایت —</option>
                        @foreach($sites as $site)
                        <option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- نام مشتری + ایمیل --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="form-label">
                        نام مشتری
                        <span class="text-gray-400 text-xs">(اختیاری)</span>
                        <span x-show="selected" class="text-indigo-400 text-xs mr-1">— از انتخاب شده</span>
                    </label>
                    <input type="text" name="customer_name"
                           :value="selected ? (selected.name || selected.mobile) : '{{ old('customer_name') }}'"
                           :readonly="!!selected"
                           :class="selected ? '!bg-indigo-50/50 !border-indigo-200 !text-indigo-700' : ''"
                           class="form-input" placeholder="مثال: علی رضایی">
                </div>
                <div>
                    <label class="form-label">
                        موبایل مشتری
                        <span class="text-gray-400 text-xs">(اختیاری)</span>
                    </label>
                    <input type="text" name="customer_mobile"
                           :value="selected ? selected.mobile : '{{ old('customer_mobile') }}'"
                           :readonly="!!selected"
                           :class="selected ? '!bg-indigo-50/50 !border-indigo-200 !text-indigo-700' : ''"
                           class="form-input" placeholder="09xxxxxxxxx" dir="ltr">
                </div>
            </div>

            {{-- دسته‌بندی --}}
            <div>
                <label class="form-label">دسته‌بندی <span class="text-gray-400 text-xs">(اختیاری)</span></label>
                <select name="category" class="form-input">
                    <option value="">— انتخاب کنید —</option>
                    <option value="technical" @selected(old('category') === 'technical')>🔧 فنی</option>
                    <option value="billing"   @selected(old('category') === 'billing')>💰 مالی و صورتحساب</option>
                    <option value="feature"   @selected(old('category') === 'feature')>✨ درخواست ویژگی</option>
                    <option value="bug"       @selected(old('category') === 'bug')>🐛 گزارش مشکل</option>
                    <option value="other"     @selected(old('category') === 'other')>📋 سایر</option>
                </select>
            </div>

            {{-- پیام --}}
            <div>
                <label class="form-label">توضیحات <span class="required">*</span></label>
                <textarea name="description" rows="5" required
                          class="form-input resize-y min-h-[120px] @error('description') !border-red-400 @enderror"
                          placeholder="متن پیام را بنویسید...">{{ old('description') }}</textarea>
                @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-[11px] text-gray-400 mt-1">حداکثر ۵۰۰۰ کاراکتر</p>
            </div>

            {{-- یادداشت داخلی اولیه --}}
            <div>
                <div class="internal-note-toggle" :class="{ active: internalNote }"
                     role="button" tabindex="0"
                     @click="internalNote = !internalNote; if(internalNote) $nextTick(() => $refs.noteRef.focus())"
                     @keydown.enter.prevent="internalNote = !internalNote; if(internalNote) $nextTick(() => $refs.noteRef.focus())"
                     @keydown.space.prevent="internalNote = !internalNote; if(internalNote) $nextTick(() => $refs.noteRef.focus())">
                    <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded"
                         :class="internalNote ? 'bg-amber-400' : 'border-2 border-gray-300'">
                        <svg x-show="internalNote" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium" :class="internalNote ? 'text-amber-800' : 'text-gray-600'">یادداشت داخلی اولیه</p>
                        <p class="text-xs text-gray-400">یادداشتی که فقط تیم پشتیبانی می‌بیند</p>
                    </div>
                </div>
                <div x-show="internalNote" x-transition class="mt-3">
                    <textarea x-ref="noteRef" name="initial_note" rows="3"
                              class="form-input resize-y min-h-[80px]"
                              placeholder="یادداشت داخلی (برای تیم پشتیبانی)...">{{ old('initial_note') }}</textarea>
                </div>
            </div>

            {{-- پیوست --}}
            @if($attachmentsEnabled)
            <div>
                <label class="form-label">پیوست فایل <span class="text-gray-400 text-xs">(اختیاری)</span></label>
                <input type="file" name="attachments[]" multiple
                       class="form-input file:!rounded-lg file:!border-0 file:!bg-indigo-50 file:!text-indigo-700 file:!px-4 file:!py-2 file:!text-sm file:!font-medium hover:file:!bg-indigo-100"
                       accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                <p class="text-[10px] text-gray-400 mt-1">حداکثر ۱۰ مگابایت — فرمت‌های مجاز: jpg, png, pdf, doc, zip</p>
            </div>
            @endif

        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-4 px-6 sm:px-8 py-4 border-t border-gray-100 bg-gray-50/50">
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                اطلاعات با امنیت ذخیره می‌شود
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('tickets.admin.index') }}" class="btn-ghost">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    انصراف
                </a>
                <button type="submit" class="btn-gradient">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    ثبت تیکت
                </button>
            </div>
        </div>
    </form>

</div>

@push('scripts')
<script>
function ticketCreate({ people, oldKey }) {
    return {
        people,
        search: '',
        showDrop: false,
        selected: null,
        internalNote: false,

        get filtered() {
            const q = this.search.toLowerCase().trim();
            if (!q) return this.people.slice(0, 14);
            return this.people.filter(p =>
                (p.name   && p.name.toLowerCase().includes(q))  ||
                (p.email  && p.email.toLowerCase().includes(q)) ||
                (p.mobile && p.mobile.includes(q))
            ).slice(0, 14);
        },

        pick(p) {
            this.selected = p;
            this.search   = '';
            this.showDrop = false;
        },

        clearSelected() {
            this.selected = null;
            this.search   = '';
        },

        init() {
            if (oldKey) {
                const found = this.people.find(p => p.key === oldKey);
                if (found) this.pick(found);
            }
        },
    };
}
</script>
@endpush
@endsection
