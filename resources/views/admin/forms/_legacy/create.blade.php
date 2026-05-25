@extends('layouts.admin')

@section('header', 'ایجاد فرم جدید')

@section('content')
<div class="bg-white rounded-xl shadow p-6 max-w-xl">

    <form method="POST" action="{{ route('admin.forms.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block mb-1 font-medium">عنوان فرم</label>
            <input type="text" name="title"
                   class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-blue-200">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" checked>
            <label>فعال باشد</label>
        </div>

        <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            ذخیره فرم
        </button>
    </form>
@if ($errors->any())
    <div class="bg-red-100 text-red-700 p-3 rounded mb-3">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

</div>
@endsection
