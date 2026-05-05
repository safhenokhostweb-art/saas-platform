@extends('layouts.admin')

@section('header', 'افزودن محصول')

@section('content')
@php $preselectedModuleId = $preselectedModuleId ?? null; @endphp
<form method="POST" action="{{ route('admin.products.store') }}" class="card p-4 max-w-2xl">
    @csrf
    @include('admin.products._form')
    <button type="submit" class="btn btn-primary">ذخیره</button>
</form>
@endsection
