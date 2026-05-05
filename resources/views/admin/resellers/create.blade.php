@extends('layouts.admin')

@section('header', 'افزودن نماینده')

@section('content')
@if($users->isEmpty())
    <div class="alert alert-warning">هیچ کاربر فعالی برای تخصیص به نماینده وجود ندارد. همهٔ کاربران فعال قبلاً نماینده شده‌اند یا کاربری ثبت نشده است.</div>
    <a href="{{ route('admin.resellers.index') }}" class="btn btn-secondary">بازگشت</a>
@else
<form method="POST" action="{{ route('admin.resellers.store') }}" class="card p-4 max-w-2xl">
    @csrf
    @include('admin.resellers._form')
    <button type="submit" class="btn btn-primary">ذخیره</button>
</form>
@endif
@endsection
