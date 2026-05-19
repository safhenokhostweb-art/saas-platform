@extends('layouts.admin')

@section('header', 'ثبت تیکت پشتیبانی جدید')

@section('content')
@if($errors->any())
    <div class="alert alert-danger mb-4">
        <ul class="list-disc list-inside mb-0">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($sites->isEmpty())
    <div class="bg-white rounded-2xl shadow p-6 text-center text-gray-500">
        <p>شما به هیچ سایتی دسترسی ندارید. برای ثبت تیکت ابتدا باید به یک سایت متصل باشید.</p>
        <a href="{{ route('customer.support-tickets.index') }}" class="btn btn-secondary mt-4">بازگشت</a>
    </div>
@else
    <form method="POST" action="{{ route('customer.support-tickets.store') }}" class="card p-4 max-w-2xl">
        @csrf
        <div class="mb-3">
            <label class="form-label">سایت</label>
            <select name="site_id" class="form-select" required>
                <option value="">— انتخاب کنید —</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}" {{ old('site_id') == $s->id ? 'selected' : '' }}>{{ $s->title }} ({{ $s->domain ?: '—' }})</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">موضوع</label>
            <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required maxlength="255" placeholder="خلاصه‌ای از مشکل خود">
        </div>
        <div class="mb-3">
            <label class="form-label">شرح مشکل</label>
            <textarea name="message" class="form-control" rows="6" required maxlength="5000" placeholder="مشکل خود را به طور کامل شرح دهید...">{{ old('message') }}</textarea>
            <small class="text-gray-500">حداکثر ۵۰۰۰ کاراکتر</small>
        </div>
        <div class="mb-3">
            <label class="form-label">اولویت</label>
            <select name="priority" class="form-select w-auto">
                <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>کم</option>
                <option value="normal" {{ old('priority', 'normal') === 'normal' ? 'selected' : '' }}>عادی</option>
                <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>بالا</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary">ثبت تیکت</button>
            <a href="{{ route('customer.support-tickets.index') }}" class="btn btn-secondary">انصراف</a>
        </div>
    </form>
@endif
@endsection
