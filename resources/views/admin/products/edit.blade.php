@extends('layouts.admin')

@section('header', 'ویرایش محصول')

@section('content')
<form method="POST" action="{{ route('admin.products.update', $product) }}" class="card p-4 max-w-2xl">
    @csrf
    @method('PUT')
    @include('admin.products._form')
    <button type="submit" class="btn btn-primary">بروزرسانی</button>
</form>
@endsection
