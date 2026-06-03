@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', $article->title)

@section('content')
<div class="mx-auto max-w-3xl space-y-6" dir="rtl">

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.education.articles.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900">{{ $article->title }}</h1>
        </div>
        <a href="{{ route('admin.education.articles.edit', $article) }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
            ویرایش
        </a>
    </div>

    {{-- Meta --}}
    <div class="flex flex-wrap gap-3 text-xs text-gray-500">
        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1">{{ $article->category?->name }}</span>
        <span class="inline-flex items-center gap-1 rounded-full bg-cyan-50 text-cyan-700 px-2.5 py-1">{{ $article->content_type }}</span>
        @if($article->module_slug)
        <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 text-violet-700 px-2.5 py-1">{{ $article->module_slug }}</span>
        @endif
        <span>بازدید: {{ number_format($article->view_count) }}</span>
        <span>نویسنده: {{ $article->author?->name ?? '—' }}</span>
    </div>

    {{-- Body --}}
    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm prose prose-sm max-w-none text-gray-800">
        {!! nl2br(e($article->body)) !!}
    </div>

    {{-- Revisions --}}
    @if($article->revisions->isNotEmpty())
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-700">تاریخچه ویرایش‌ها</h2>
        <div class="space-y-2">
            @foreach($article->revisions->take(5) as $rev)
            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600">
                <span>نسخه {{ $rev->revision_number }} — {{ $rev->change_summary ?? 'بدون توضیح' }}</span>
                <span class="text-gray-400">{{ $rev->created_at?->diffForHumans() }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
