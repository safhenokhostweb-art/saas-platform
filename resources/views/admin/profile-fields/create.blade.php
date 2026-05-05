@extends('layouts.admin')

@section('header', 'عنوان صفحه')

@section('content')
<div class="container py-4">
    <div class="card shadow-sm border-0 rounded-xl">
        <div class="card-body">
            @if ($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                <ul class="list-disc pr-5">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <h1 class="text-2xl font-bold mb-6">افزودن فیلد جدید</h1>

            <form method="POST" action="{{ route('admin.profile-fields.store') }}">
                @csrf

                <div class="mb-4">
                    <label class="block mb-1 font-semibold">عنوان (برچسب)</label>
                    <input type="text" name="label" value="{{ old('label') }}"
                        class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 form-control"
                        placeholder="مثال: نام و نام خانوادگی"
                        required>
                    @error('label')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="block mb-1 font-semibold">کلید (یکتا، انگلیسی)</label>
                    <input type="text" name="key" value="{{ old('key') }}"
                        class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 form-control"
                        placeholder="مثال: full_name یا postal_code"
                        required>
                    @error('key')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="block mb-1 font-semibold">نوع فیلد</label>
                    <select name="type" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 form-control" required>
                        <option value="text" {{ old('type') === 'text' ? 'selected' : '' }}>متن</option>
                        <option value="textarea" {{ old('type') === 'textarea' ? 'selected' : '' }}>متن چندخطی</option>
                        <option value="number" {{ old('type') === 'number' ? 'selected' : '' }}>عدد</option>
                        <option value="map" {{ old('type') === 'map' ? 'selected' : '' }}>نقشه</option>
                    </select>
                    @error('type')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4 flex items-center gap-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="required" value="1" {{ old('required') ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>اجباری</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="enabled" value="1" {{ old('enabled', true) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>فعال</span>
                    </label>
                </div>

                <button type="submit"
                    class="btn btn-primary">
                    ذخیره
                </button>
                <a href="{{ route('admin.profile-fields.index') }}" class="btn btn-secondary mr-2">انصراف</a>
            </form>

        </div>
    </div>
</div>
@endsection
