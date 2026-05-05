@extends('layouts.admin')

@section('header', 'ویرایش نماینده')

@section('content')
<form method="POST" action="{{ route('admin.resellers.update', $reseller) }}" class="card p-4 max-w-2xl">
    @csrf
    @method('PUT')
    @include('admin.resellers._form')
    <button type="submit" class="btn btn-primary">بروزرسانی</button>
</form>
@endsection
