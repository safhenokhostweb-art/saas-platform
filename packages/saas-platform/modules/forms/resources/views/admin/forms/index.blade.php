@extends('layouts.admin')

@section('header', 'فرم‌ها')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="text-gray-600 text-sm">مدیریت فرم‌های سایت</p>
    </div>
    <a href="{{ route('admin.forms.create') }}"
       class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-xl inline-flex items-center gap-2 hover:opacity-90 transition">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        ساخت فرم جدید
    </a>
</div>

<x-module-features module="forms" />

@if($forms->count())
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($forms as $form)
            <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="font-bold text-lg">{{ $form->title }}</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $form->fields_count ?? 0 }} فیلد |
                            {{ $form->submissions_count ?? 0 }} ارسال
                        </p>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full {{ $form->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $form->is_active ? 'فعال' : 'غیرفعال' }}
                    </span>
                </div>

                <div class="flex flex-wrap gap-2 mt-4">
                    <a href="{{ route('admin.forms.edit', $form) }}"
                       class="text-indigo-600 text-sm hover:underline inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                        </svg>
                        مدیریت
                    </a>
                    <a href="{{ route('admin.forms.preview', $form) }}" target="_blank"
                       class="text-indigo-600 text-sm hover:underline inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        پیش‌نمایش
                    </a>
                    <a href="{{ route('admin.forms.sites.edit', $form) }}"
                       class="text-indigo-600 text-sm hover:underline inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.571a4.5 4.5 0 00-1.242-7.244l-4.5-4.5a4.5 4.5 0 00-6.364 6.364L4.34 8.688"/>
                        </svg>
                        سایت‌ها
                    </a>
                </div>

                <form method="POST" action="{{ route('admin.forms.destroy', $form) }}" class="mt-3"
                      onsubmit="return confirm('آیا از حذف کامل فرم مطمئن هستید؟');">
                    @csrf
                    @method('DELETE')
                    <button class="text-red-600 text-sm hover:underline inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                        حذف فرم
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $forms->links() }}
    </div>
@else
    <div class="bg-white rounded-2xl shadow-sm p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
        </svg>
        <p class="text-gray-500 text-lg">هنوز فرمی ساخته نشده است.</p>
        <a href="{{ route('admin.forms.create') }}" class="text-indigo-600 hover:underline mt-2 inline-block">ساخت اولین فرم</a>
    </div>
@endif

@include('forms::admin.forms.guide')
@endsection
