@extends('layouts.admin')

@section('header', 'مشاهده مقاله')

@section('content')

<div class="max-w-4xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.kb.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-800">{{ $article->title }}</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.kb.edit', $article) }}"
               class="inline-flex items-center gap-1 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                ویرایش
            </a>
        </div>
    </div>

    {{-- Meta --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
        <div class="flex flex-wrap gap-4 text-sm text-gray-600">
            <span><strong>دسته‌بندی:</strong> {{ $article->category?->name }}</span>
            <span><strong>نویسنده:</strong> {{ $article->author?->name ?? '—' }}</span>
            <span><strong>وضعیت:</strong>
                @if($article->status === 'published')
                    <span class="text-green-600 font-medium">منتشر شده</span>
                @elseif($article->status === 'draft')
                    <span class="text-yellow-600 font-medium">پیش‌نویس</span>
                @else
                    <span class="text-gray-500 font-medium">بایگانی</span>
                @endif
            </span>
            <span><strong>بازدید:</strong> @numFa($article->view_count)</span>
            @if($article->helpfulness_percent !== null)
            <span><strong>مفید بود:</strong> {{ $article->helpfulness_percent }}%</span>
            @endif
        </div>

        @if($article->tags)
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach($article->tags as $tag)
                <span class="px-2 py-0.5 bg-purple-50 text-purple-700 rounded text-xs">{{ $tag }}</span>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Body --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="prose prose-sm max-w-none text-gray-700">
            {!! $article->body !!}
        </div>
    </div>

</div>

@endsection
