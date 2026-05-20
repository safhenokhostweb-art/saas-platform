@extends('layouts.admin')

@section('header', 'ثبت تیکت پشتیبانی')

@push('head')
<style>
.form-glass-card{background:rgba(255,255,255,0.82);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.3);border-radius:1.25rem;box-shadow:0 8px 32px rgba(15,23,42,0.06)}
.form-glass-card:hover{box-shadow:0 12px 40px rgba(15,23,42,0.1)}
.form-glass-header{background:linear-gradient(135deg,rgba(99,102,241,0.06),rgba(168,85,247,0.04));border-bottom:1px solid rgba(255,255,255,0.2);border-radius:1.25rem 1.25rem 0 0;padding:1.25rem 1.75rem}
.form-input{width:100%;padding:0.75rem 1rem;border:1px solid #e2e8f0;border-radius:0.75rem;font-size:0.875rem;transition:all 0.2s ease;background:rgba(255,255,255,0.6)}
.form-input:focus{outline:none;border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,0.12)}
.form-input:hover{border-color:#c7d2fe}
.form-label{display:block;font-size:0.8125rem;font-weight:600;color:#374151;margin-bottom:0.5rem}
.form-label .required{color:#ef4444;margin-right:2px}
.btn-gradient{display:inline-flex;align-items:center;gap:0.5rem;padding:0.7rem 1.75rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.25s ease;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.25)}
.btn-gradient:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,0.35)}
.btn-ghost{display:inline-flex;align-items:center;gap:0.5rem;padding:0.7rem 1.75rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.2s ease;background:transparent;color:#64748b;border:1.5px solid #e2e8f0;cursor:pointer}
.btn-ghost:hover{background:#f8fafc;border-color:#cbd5e1;color:#475569}
.error-glass{background:rgba(254,242,242,0.85);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(239,68,68,0.2);border-radius:1rem;padding:1rem 1.25rem}
.error-glass li{font-size:0.8125rem;color:#dc2626;padding:0.2rem 0}
</style>
@endpush

@section('content')
<div class="max-w-3xl mx-auto">

<div class="flex items-center gap-4 mb-8">
    <a href="{{ route('admin.support-tickets.index') }}" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/70 hover:bg-white border border-gray-200 transition-all hover:shadow-sm">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        بازگشت به لیست
    </a>
    <div>
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">ثبت تیکت جدید</h2>
        <p class="text-sm text-gray-500 mt-1">تیکت پشتیبانی جدید برای مشتری ایجاد کنید</p>
    </div>
</div>

@if($errors->any())
<div class="error-glass mb-6" x-data="{ show: true }" x-show="show" x-transition>
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="flex-1">
            <p class="text-sm font-semibold text-red-800 mb-1">{{ count($errors->all()) }} خطا در ارسال فرم</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        <button @click="show = false" class="text-red-400 hover:text-red-600 transition-colors shrink-0">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>
@endif

<form method="POST" action="{{ route('admin.support-tickets.store') }}" enctype="multipart/form-data" class="form-glass-card overflow-hidden">
    @csrf
    <div class="form-glass-header">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20 text-lg">🎫</span>
            <div>
                <h3 class="font-bold text-gray-900">اطلاعات تیکت</h3>
                <p class="text-xs text-gray-500 mt-0.5">تمام فیلدهای الزامی را پر کنید</p>
            </div>
        </div>
    </div>

    </div>

    <div class="p-6 sm:p-8 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="form-label">سایت <span class="required">*</span></label>
                <select name="site_id" required class="form-input">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($sites as $s)
                    <option value="{{ $s->id }}" {{ old('site_id') == $s->id ? 'selected' : '' }}>{{ $s->title }} ({{ $s->domain ?: '—' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">اولویت <span class="required">*</span></label>
                <select name="priority" class="form-input">
                    <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>🔽 کم</option>
                    <option value="normal" {{ old('priority', 'normal') === 'normal' ? 'selected' : '' }}>➡️ عادی</option>
                    <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>🔴 بالا</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="form-label">ایمیل مشتری <span class="required">*</span></label>
                <input type="email" name="customer_email" value="{{ old('customer_email') }}" required class="form-input" placeholder="email@example.com" dir="ltr">
            </div>
            <div>
                <label class="form-label">نام مشتری <span class="text-gray-400 text-xs">(اختیاری)</span></label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" class="form-input" placeholder="مثال: علی رضایی">
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="form-label">دسته‌بندی <span class="text-gray-400 text-xs">(اختیاری)</span></label>
                <select name="category" class="form-input">
                    <option value="">— انتخاب کنید —</option>
                    <option value="technical" {{ old('category') === 'technical' ? 'selected' : '' }}>🔧 فنی</option>
                    <option value="billing" {{ old('category') === 'billing' ? 'selected' : '' }}>💰 مالی و صورتحساب</option>
                    <option value="feature" {{ old('category') === 'feature' ? 'selected' : '' }}>✨ درخواست ویژگی</option>
                    <option value="bug" {{ old('category') === 'bug' ? 'selected' : '' }}>🐛 گزارش مشکل</option>
                    <option value="other" {{ old('category') === 'other' ? 'selected' : '' }}>📋 سایر</option>
                </select>
            </div>
            <div>
                <label class="form-label">برچسب‌ها <span class="text-gray-400 text-xs">(اختیاری)</span></label>
                <input type="text" name="tags" value="{{ old('tags') }}" class="form-input" placeholder="مهم, فوری, خطا">
                <p class="text-[10px] text-gray-400 mt-1">با کاما جدا کنید</p>
            </div>
        </div>
        <div>
            <label class="form-label">موضوع <span class="required">*</span></label>
            <input type="text" name="subject" value="{{ old('subject') }}" required class="form-input" placeholder="موضوع تیکت را وارد کنید">
        </div>
        <div>
            <label class="form-label">پیام <span class="required">*</span></label>
            <textarea name="message" rows="5" required class="form-input resize-y min-h-[120px]" placeholder="متن پیام خود را بنویسید...">{{ old('message') }}</textarea>
            <p class="text-[11px] text-gray-400 mt-1 flex items-center gap-1.5">
                <span class="text-sm">💡</span>
                <span>حداکثر ۵۰۰۰ کاراکتر — توضیحات کامل مشکل را بنویسید</span>
            </p>
        </div>
        <div>
            <label class="form-label">پیوست فایل <span class="text-gray-400 text-xs">(اختیاری)</span></label>
            <input type="file" name="attachments[]" multiple class="form-input file:!rounded-lg file:!border-0 file:!bg-indigo-50 file:!text-indigo-700 file:!px-4 file:!py-2 file:!text-sm file:!font-medium hover:file:!bg-indigo-100" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
            <p class="text-[10px] text-gray-400 mt-1">حداکثر ۱۰ مگابایت — فرمت‌های مجاز: jpg, png, pdf, doc, zip</p>
        </div>
    </div>

    <div class="flex items-center justify-between gap-4 px-6 sm:px-8 py-4 border-t border-gray-100 bg-gray-50/50">
        <div class="flex items-center gap-2 text-xs text-gray-400">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
            اطلاعات با امنیت کامل ذخیره می‌شود
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.support-tickets.index') }}" class="btn-ghost">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                انصراف
            </a>
            <button type="submit" class="btn-gradient">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                ثبت تیکت
            </button>
        </div>
    </div>
</form>
</div>
@endsection
