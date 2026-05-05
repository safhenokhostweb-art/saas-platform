@extends('layouts.admin')

@section('header', 'ثبت تیکت پشتیبانی')

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

<form method="POST" action="{{ route('admin.support-tickets.store') }}" class="card p-4 max-w-2xl">
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
        <label class="form-label">ایمیل مشتری</label>
        <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">نام مشتری (اختیاری)</label>
        <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}">
    </div>
    <div class="mb-3">
        <label class="form-label">موضوع</label>
        <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">پیام</label>
        <textarea name="message" class="form-control" rows="5" required>{{ old('message') }}</textarea>
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
        <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-secondary">انصراف</a>
    </div>
</form>
@endsection
