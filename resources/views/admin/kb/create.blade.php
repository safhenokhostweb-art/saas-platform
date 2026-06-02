@extends('layouts.admin')

@section('header', 'مقاله جدید')

@section('content')

<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.kb.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-800">مقاله جدید</h1>
    </div>

    @if($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.kb.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">

            {{-- دسته‌بندی --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی *</label>
                <select name="kb_category_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">انتخاب دسته‌بندی...</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('kb_category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- عنوان --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان *</label>
                <input type="text" name="title" value="{{ old('title') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                       placeholder="عنوان مقاله...">
            </div>

            {{-- خلاصه --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">خلاصه (اختیاری)</label>
                <textarea name="excerpt" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                          placeholder="خلاصه کوتاه برای نمایش در لیست...">{{ old('excerpt') }}</textarea>
            </div>

            {{-- متن --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">متن مقاله *</label>
                <textarea name="body" rows="16" required
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500"
                          placeholder="متن کامل مقاله... (HTML پشتیبانی می‌شود)">{{ old('body') }}</textarea>
            </div>

            {{-- وضعیت + برچسب‌ها --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="draft"     @selected(old('status', 'draft') === 'draft')>پیش‌نویس</option>
                        <option value="published" @selected(old('status') === 'published')>منتشر شده</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">برچسب‌ها (با کاما جدا کنید)</label>
                    <input type="text" name="tags_raw" value="{{ old('tags_raw') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="فنی, نصب, پیکربندی">
                </div>
            </div>

        </div>

        <div class="flex gap-3 justify-end">
            <a href="{{ route('admin.kb.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">انصراف</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">ذخیره مقاله</button>
        </div>

    </form>
</div>

@endsection
