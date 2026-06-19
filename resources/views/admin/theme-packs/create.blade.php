@extends('layouts.admin')

@section('title', 'پک تم جدید')

@section('content')
<div class="p-6 max-w-2xl mx-auto" dir="rtl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.theme-packs.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900">پک تم جدید</h1>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm mb-4">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.theme-packs.store') }}" class="space-y-5">
        @csrf

        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
            <h2 class="font-semibold text-gray-800 text-sm border-b pb-2">اطلاعات پک</h2>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام پک *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                    <textarea name="description" rows="3" class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-300 resize-none">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نسخه</label>
                    <input type="text" name="version" value="{{ old('version', '1.0.0') }}"
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نویسنده</label>
                    <input type="text" name="author" value="{{ old('author') }}"
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع مجوز</label>
                    <select name="license_type" class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-300">
                        <option value="free">رایگان</option>
                        <option value="premium">پریمیوم</option>
                        <option value="commercial">تجاری</option>
                        <option value="mit">MIT</option>
                    </select>
                </div>
            </div>
        </div>

        @if($themes->isNotEmpty())
        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
            <h2 class="font-semibold text-gray-800 text-sm border-b pb-2">ساخت از تم موجود (اختیاری)</h2>
            <p class="text-xs text-gray-500">در صورت انتخاب، پک به‌صورت خودکار از صفحات و استایل تم انتخابی ساخته می‌شود.</p>
            <select name="theme_id" class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-300">
                <option value="">-- بدون تم پایه --</option>
                @foreach($themes as $theme)
                    <option value="{{ $theme->id }}" @selected(old('theme_id') == $theme->id)>{{ $theme->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="is_public" value="1" @checked(old('is_public'))
                    class="rounded text-purple-600">
                <span class="text-sm text-gray-700">عمومی (قابل مشاهده در مارکتپلیس آینده)</span>
            </label>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.theme-packs.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">انصراف</a>
            <button type="submit" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                ایجاد پک
            </button>
        </div>
    </form>
</div>
@endsection
