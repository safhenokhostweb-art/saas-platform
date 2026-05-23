@extends('layouts.admin')

@section('header','فرم‌ها')

@section('content')
<a href="{{ route('admin.forms.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded">
    ساخت فرم
</a>

<x-module-features module="forms" />

<div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
    @foreach($forms as $form)
        <div class="bg-white p-4 rounded shadow">
            <div class="font-bold">{{ $form->title }}</div>
            <a href="{{ route('admin.forms.edit',$form) }}" class="text-indigo-600 text-sm">مدیریت فیلدها</a>
            <span class="mx-1">|</span>
            <a href="{{ route('admin.forms.preview', $form) }}" class="text-indigo-600 text-sm" target="_blank">مشاهده فرم نهایی</a>
            <span class="mx-1">|</span>
            <a href="{{ route('admin.forms.sites.edit', $form) }}" class="text-indigo-600 text-sm">اتصال به سایت‌ها</a>
            <form method="POST" action="{{ route('admin.forms.destroy', $form) }}"
      onsubmit="return confirm('حذف کامل فرم؟');">
    @csrf
    @method('DELETE')

    <button class="bg-red-600 text-white px-3 py-1 rounded text-sm">
        حذف فرم
    </button>
</form>
        </div>
    @endforeach
</div>

@include('admin.forms.guide')

@endsection
