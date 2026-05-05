@extends('layouts.admin')

@section('header', 'ویرایش پلن پشتیبانی')

@section('content')
<form action="{{ route('admin.support-plans.update', $supportPlan) }}" method="post">
    @csrf
    @method('PUT')
    <div class="card p-4 max-w-md">
        <div class="form-group mb-3"><label>عنوان</label><input type="text" name="title" class="form-control" value="{{ old('title', $supportPlan->title) }}" required></div>
        <div class="form-group mb-3"><label>قیمت (ریال)</label><input type="number" name="price" class="form-control" value="{{ old('price', $supportPlan->price) }}" min="0" required></div>
        <div class="form-group mb-3"><label>مدت (روز)</label><input type="number" name="duration_days" class="form-control" value="{{ old('duration_days', $supportPlan->duration_days) }}" min="1" required></div>
        <div class="form-group mb-3"><label><input type="checkbox" name="has_ticket" value="1" {{ old('has_ticket', $supportPlan->has_ticket) ? 'checked' : '' }}> تیکت پشتیبانی</label></div>
        <div class="form-group mb-3"><label><input type="checkbox" name="has_sms" value="1" {{ old('has_sms', $supportPlan->has_sms) ? 'checked' : '' }}> پیامک</label></div>
        <button type="submit" class="btn btn-primary">ذخیره</button>
        <a href="{{ route('admin.support-plans.index') }}" class="btn btn-secondary">انصراف</a>
    </div>
</form>
@endsection
