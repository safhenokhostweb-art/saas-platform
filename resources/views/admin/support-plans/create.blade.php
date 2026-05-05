@extends('layouts.admin')

@section('header', 'افزودن پلن پشتیبانی')

@section('content')
<form action="{{ route('admin.support-plans.store') }}" method="post">
    @csrf
    <div class="card p-4 max-w-md">
        <div class="form-group mb-3"><label>عنوان</label><input type="text" name="title" class="form-control" value="{{ old('title') }}" required></div>
        <div class="form-group mb-3"><label>قیمت (ریال)</label><input type="number" name="price" class="form-control" value="{{ old('price') }}" min="0" required></div>
        <div class="form-group mb-3"><label>مدت (روز)</label><input type="number" name="duration_days" class="form-control" value="{{ old('duration_days', 30) }}" min="1" required></div>
        <div class="form-group mb-3"><label><input type="checkbox" name="has_ticket" value="1" {{ old('has_ticket', true) ? 'checked' : '' }}> تیکت پشتیبانی</label></div>
        <div class="form-group mb-3"><label><input type="checkbox" name="has_sms" value="1" {{ old('has_sms') ? 'checked' : '' }}> پیامک</label></div>
        <button type="submit" class="btn btn-primary">ذخیره</button>
        <a href="{{ route('admin.support-plans.index') }}" class="btn btn-secondary">انصراف</a>
    </div>
</form>
@endsection
