@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', $note ? 'ویرایش یادداشت انتشار' : 'یادداشت جدید')

@section('content')
<div class="mx-auto max-w-2xl space-y-6" dir="rtl">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.education.release-notes.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900">{{ $note ? 'ویرایش یادداشت انتشار' : 'یادداشت جدید' }}</h1>
    </div>

    @if($errors->any())
    <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
        <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST"
          action="{{ $note ? route('admin.education.release-notes.update', $note) : route('admin.education.release-notes.store') }}"
          class="space-y-5">
        @csrf
        @if($note) @method('PUT') @endif

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان</label>
                <input type="text" name="title" value="{{ old('title', $note?->title) }}" required
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-orange-400 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نسخه</label>
                    <input type="text" name="version_number" value="{{ old('version_number', $note?->version_number) }}"
                           placeholder="1.2.3" required
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-mono focus:border-orange-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع</label>
                    <select name="release_type" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-orange-400 focus:outline-none">
                        @foreach(['feature'=>'ویژگی جدید','fix'=>'رفع باگ','improvement'=>'بهبود','breaking'=>'تغییر شکست‌دهنده','security'=>'امنیتی'] as $v => $l)
                        <option value="{{ $v }}" {{ old('release_type', $note?->release_type ?? 'feature') === $v ? 'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">slug ماژول</label>
                    <input type="text" name="module_slug" value="{{ old('module_slug', $note?->module_slug) }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-orange-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">شناسه ویرایش مرتبط</label>
                    <input type="number" name="revision_id" value="{{ old('revision_id', $note?->revision_id) }}"
                           placeholder="ID از edu_article_revisions"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-orange-400 focus:outline-none">
                    <p class="mt-1 text-xs text-gray-400">این فیلد یادداشت را به ویرایش خاص مقاله مرتبط می‌کند.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">متن یادداشت</label>
                <textarea name="body" rows="8" required
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-mono resize-y focus:border-orange-400 focus:outline-none">{{ old('body', $note?->body) }}</textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="hidden" name="is_published" value="0">
                <input type="checkbox" name="is_published" value="1"
                       {{ old('is_published', $note?->is_published ?? false) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                منتشر شود
            </label>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.education.release-notes.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">انصراف</a>
            <button type="submit" class="rounded-lg bg-orange-600 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-700">ذخیره یادداشت</button>
        </div>
    </form>
</div>
@endsection
