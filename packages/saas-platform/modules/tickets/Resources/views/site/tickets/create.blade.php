@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', 'ارسال تیکت جدید')

@push('head')
<style>
.form-glass-card{background:rgba(255,255,255,0.82);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.3);border-radius:1.25rem;box-shadow:0 8px 32px rgba(15,23,42,0.06)}
.form-glass-header{background:linear-gradient(135deg,rgba(99,102,241,0.06),rgba(168,85,247,0.04));border-bottom:1px solid rgba(255,255,255,0.2);border-radius:1.25rem 1.25rem 0 0;padding:1.25rem 1.75rem}
.form-input{width:100%!important;padding:0.75rem 1rem!important;border:1px solid #e2e8f0!important;border-radius:0.75rem!important;font-size:0.875rem!important;transition:all 0.2s ease;background:rgba(255,255,255,0.6)}
.form-input:focus{outline:none!important;border-color:#a5b4fc!important;box-shadow:0 0 0 3px rgba(99,102,241,0.12)!important}
.form-label{display:block!important;font-size:0.8125rem!important;font-weight:600!important;color:#374151!important;margin-bottom:0.5rem!important}
.form-label .required{color:#ef4444;margin-right:2px}
.btn-gradient{display:inline-flex;align-items:center;gap:0.5rem;padding:0.7rem 1.75rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.25s ease;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.25)}
.btn-gradient:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,0.35)}
.btn-ghost{display:inline-flex;align-items:center;gap:0.5rem;padding:0.7rem 1.75rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.2s ease;background:transparent;color:#64748b;border:1.5px solid #e2e8f0;cursor:pointer;text-decoration:none}
.btn-ghost:hover{background:#f8fafc;border-color:#cbd5e1;color:#475569}
</style>
@endpush

@section('content')
<div class="max-w-xl mx-auto p-6">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('tickets.site.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/70 hover:bg-white border border-gray-200 transition-all hover:shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت
        </a>
        <div>
            <h2 class="text-xl font-bold text-gray-900">ارسال تیکت پشتیبانی</h2>
            <p class="text-sm text-gray-500 mt-0.5">مشکل یا سوال خود را با ما در میان بگذارید</p>
        </div>
    </div>

    @if($errors->any())
    <div class="mb-6 bg-red-50/85 border border-red-200 rounded-2xl p-4"
         x-data="{ show: true }" x-show="show" x-transition>
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <ul class="flex-1 text-sm text-red-700 list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
            <button @click="show = false" class="text-red-400 hover:text-red-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('tickets.site.store') }}"
          enctype="{{ $attachmentsEnabled ? 'multipart/form-data' : 'application/x-www-form-urlencoded' }}"
          class="form-glass-card overflow-hidden">
        @csrf

        <div class="form-glass-header">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg text-lg">🎫</span>
                <div>
                    <h3 class="font-bold text-gray-900">اطلاعات تیکت</h3>
                    <p class="text-xs text-gray-500 mt-0.5">تمام فیلدهای الزامی را پر کنید</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-5">

            {{-- موضوع --}}
            <div>
                <label class="form-label">موضوع <span class="required">*</span></label>
                <input type="text" name="subject" value="{{ old('subject') }}" required
                       class="form-input @error('subject') !border-red-400 @enderror"
                       placeholder="موضوع تیکت را وارد کنید">
                @error('subject')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- اولویت --}}
            <div>
                <label class="form-label">اولویت</label>
                <select name="priority" class="form-input">
                    <option value="normal" @selected(old('priority', 'normal') === 'normal')>➡️ عادی</option>
                    <option value="high"   @selected(old('priority') === 'high')>🔴 بالا</option>
                    <option value="low"    @selected(old('priority') === 'low')>🔽 کم</option>
                </select>
            </div>

            {{-- پیام --}}
            <div>
                <label class="form-label">توضیحات <span class="required">*</span></label>
                <textarea name="description" rows="5" required
                          class="form-input resize-y min-h-[120px] @error('description') !border-red-400 @enderror"
                          placeholder="متن پیام خود را بنویسید...">{{ old('description') }}</textarea>
                @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-[11px] text-gray-400 mt-1">
                    💡 توضیحات کامل مشکل را بنویسید — حداکثر ۵۰۰۰ کاراکتر
                </p>
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

        <div class="flex items-center justify-between gap-4 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                اطلاعات با امنیت ذخیره می‌شود
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('tickets.site.index') }}" class="btn-ghost">انصراف</a>
                <button type="submit" class="btn-gradient">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    ارسال تیکت
                </button>
            </div>
        </div>
    </form>

</div>
@endsection
