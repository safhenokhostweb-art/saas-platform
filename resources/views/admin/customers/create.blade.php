@extends('layouts.admin')

@section('header','افزودن مشتری')

@section('content')

<form method="POST" action="{{ route('admin.customers.store') }}" class="bg-white p-6 rounded shadow max-w-lg space-y-4">
    @csrf
    <input name="name" placeholder="نام مشتری" class="w-full border rounded px-3 py-2">
    <input name="mobile" placeholder="شماره موبایل" class="w-full border rounded px-3 py-2">
    <button class="bg-indigo-600 text-white px-4 py-2 rounded">ذخیره</button>
</form>
@endsection
