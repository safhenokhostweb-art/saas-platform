@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'ویرایش مقاله')

@section('content')
<div class="mx-auto max-w-3xl space-y-6" dir="rtl">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.education.articles.show', $article) }}" class="text-gray-400 hover:text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900">ویرایش مقاله</h1>
    </div>

    @if($errors->any())
    <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
        <ul class="list-inside list-disc space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.education.articles.update', $article) }}" class="space-y-5">
        @csrf @method('PUT')

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm space-y-5">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $article->title) }}" required
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی</label>
                    <select name="edu_category_id" required
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none">
                        @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('edu_category_id', $article->edu_category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع محتوا</label>
                    <select name="content_type" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none">
                        @foreach($contentTypes as $ct)
                        <option value="{{ $ct }}" {{ old('content_type', $article->content_type) === $ct ? 'selected' : '' }}>{{ $ct }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">خلاصه</label>
                <textarea name="excerpt" rows="2"
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none resize-none">{{ old('excerpt', $article->excerpt) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">متن مقاله <span class="text-red-500">*</span></label>
                <textarea name="body" rows="14" required
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-mono focus:border-cyan-400 focus:outline-none resize-y">{{ old('body', $article->body) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">slug ماژول</label>
                    <input type="text" name="module_slug" value="{{ old('module_slug', $article->module_slug) }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الگوی مسیر</label>
                    <input type="text" name="route_pattern" value="{{ old('route_pattern', $article->route_pattern) }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">برچسب‌ها</label>
                <input type="text" name="tags_raw" value="{{ old('tags_raw', implode(', ', $article->tags ?? [])) }}"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت</label>
                <select name="status" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none">
                    <option value="draft"     {{ old('status', $article->status) === 'draft'     ? 'selected' : '' }}>پیش‌نویس</option>
                    <option value="published" {{ old('status', $article->status) === 'published' ? 'selected' : '' }}>منتشرشده</option>
                    <option value="archived"  {{ old('status', $article->status) === 'archived'  ? 'selected' : '' }}>بایگانی</option>
                </select>
            </div>

        </div>

        <div class="flex items-center gap-3 justify-between">
            <form method="POST" action="{{ route('admin.education.articles.destroy', $article) }}" onsubmit="return confirm('حذف این مقاله؟')">
                @csrf @method('DELETE')
                <button type="submit" class="text-sm text-red-500 hover:underline">حذف مقاله</button>
            </form>
            <div class="flex gap-3">
                <a href="{{ route('admin.education.articles.show', $article) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">انصراف</a>
                <button type="submit" class="rounded-lg bg-cyan-600 px-5 py-2 text-sm font-semibold text-white hover:bg-cyan-700">ذخیره تغییرات</button>
            </div>
        </div>
    </form>

</div>
@endsection
