@extends('layouts.admin')

@section('header','فیلدهای پروفایل')

@section('content')
@include('admin.profile-fields.guide')
<a href="{{ route('admin.profile-fields.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded">
    افزودن فیلد
</a>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
        <tr>
            <th class="p-3 text-right">عنوان</th>
            <th class="p-3 text-right">نوع</th>
            <th class="p-3 text-right">عملیات</th>
        </tr>
        </thead>
        <tbody>
        @foreach($fields as $field)
            <tr class="border-t">
                <td class="p-3">{{ $field->label }}</td>
                <td class="p-3">{{ $field->type }}</td>
                <td class="p-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.profile-fields.edit', $field) }}" class="text-indigo-600 hover:text-indigo-800">ویرایش</a>
                        <form action="{{ route('admin.profile-fields.destroy', $field) }}" method="POST" class="inline" onsubmit="return confirm('آیا از حذف این فیلد اطمینان دارید؟');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
