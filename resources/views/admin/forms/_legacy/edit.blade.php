@extends('layouts.admin')

@section('header', 'ویرایش فرم: ' . $form->title)

@section('content')

<p class="mb-4">
    <a href="{{ route('admin.forms.preview', $form) }}" class="btn btn-primary inline-flex items-center gap-1" target="_blank">مشاهده فرم نهایی (پیش‌نمایش برای تست)</a>
    <a href="{{ route('admin.forms.sites.edit', $form) }}" class="btn btn-secondary inline-flex items-center gap-1 mr-2">اتصال به سایت‌ها</a>
</p>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- افزودن فیلد -->
    <div class="bg-white p-6 rounded-xl shadow">
                    @if ($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                <ul class="list-disc pr-5">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        <h2 class="font-bold mb-4">افزودن فیلد جدید</h2>

        <form method="POST" action="{{ route('admin.forms.fields.store', $form) }}" class="space-y-3">
            @csrf

            <input name="label" class="w-full border rounded px-3 py-2" placeholder="عنوان فیلد">
            <input name="name" class="w-full border rounded px-3 py-2" placeholder="name">
            <select name="type" class="w-full border rounded px-3 py-2">
                <option value="text">متن</option>
                <option value="textarea">متن بلند</option>
                <option value="email">ایمیل</option>
                <option value="number">عدد</option>
                <option value="date">تاریخ (شمسی)</option>
                <option value="radio">رادیو</option>
                <option value="checkbox">چک‌باکس</option>
                <option value="select">لیست انتخابی</option>
            </select>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_required" value="1">
                الزامی
            </label>

            <button class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                افزودن فیلد
            </button>
        </form>
    </div>

    <!-- لیست فیلدها -->
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="font-bold mb-4">فیلدهای فرم</h2>

        <ul class="space-y-2">
            @foreach($form->fields()->orderBy('position')->get() as $field)
            <li class="flex justify-between items-center border rounded p-2">
                <span>{{ $field->label }} ({{ $field->type }})</span>

                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.forms.fields.toggle', $field) }}">
                        @csrf
                        @method('PATCH')

                        <button class="px-2 py-1 text-xs rounded bg-gray-200">
                            {{ $field->is_active ? 'غیرفعال' : 'فعال' }}
                        </button>
                    </form>


                    <form method="POST" action="{{ route('admin.forms.fields.destroy', $field) }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-sm px-2 py-1 bg-red-500 text-white rounded">حذف</button>
                    </form>
                </div>
            </li>
            @endforeach
        </ul>
    </div>

</div>

@endsection
