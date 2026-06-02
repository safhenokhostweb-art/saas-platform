@extends('layouts.admin')

@section('header', 'دانشنامه پشتیبانی')

@section('content')

@if(session('success'))
<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 flex items-center gap-2">
    <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- Header --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">دانشنامه پشتیبانی</h1>
        <p class="text-sm text-gray-500 mt-1">مقالات راهنما برای پشتیبانی سریع‌تر</p>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('admin.kb.categories') }}"
           class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
            دسته‌بندی‌ها
        </a>
        <a href="{{ route('admin.kb.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            مقاله جدید
        </a>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="جستجو در عنوان..."
           class="flex-1 min-w-48 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">

    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">همه وضعیت‌ها</option>
        <option value="draft" @selected(request('status') === 'draft')>پیش‌نویس</option>
        <option value="published" @selected(request('status') === 'published')>منتشر شده</option>
        <option value="archived" @selected(request('status') === 'archived')>بایگانی</option>
    </select>

    <select name="category_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">همه دسته‌ها</option>
        @foreach($categories as $cat)
            <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
        @endforeach
    </select>

    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">فیلتر</button>
    @if(request('search') || request('status') || request('category_id'))
        <a href="{{ route('admin.kb.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600">پاک‌کردن</a>
    @endif
</form>

{{-- Articles table --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr class="text-right text-gray-600">
                <th class="px-4 py-3 font-medium">عنوان</th>
                <th class="px-4 py-3 font-medium">دسته‌بندی</th>
                <th class="px-4 py-3 font-medium">وضعیت</th>
                <th class="px-4 py-3 font-medium">بازدید</th>
                <th class="px-4 py-3 font-medium">تاریخ</th>
                <th class="px-4 py-3 font-medium">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($articles as $article)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.kb.show', $article) }}" class="font-medium text-gray-800 hover:text-indigo-600">
                        {{ $article->title }}
                    </a>
                    @if($article->excerpt)
                        <p class="text-xs text-gray-500 mt-0.5 truncate max-w-xs">{{ $article->excerpt }}</p>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-purple-50 text-purple-700 rounded text-xs">
                        {{ $article->category?->name ?? '—' }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    @if($article->status === 'published')
                        <span class="inline-flex px-2 py-1 bg-green-50 text-green-700 rounded text-xs font-medium">منتشر شده</span>
                    @elseif($article->status === 'draft')
                        <span class="inline-flex px-2 py-1 bg-yellow-50 text-yellow-700 rounded text-xs font-medium">پیش‌نویس</span>
                    @else
                        <span class="inline-flex px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs font-medium">بایگانی</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">{{ number_format($article->view_count) }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ $article->created_at->diffForHumans() }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-2">
                        <a href="{{ route('admin.kb.edit', $article) }}"
                           class="text-indigo-600 hover:text-indigo-800 text-xs">ویرایش</a>
                        <form method="POST" action="{{ route('admin.kb.destroy', $article) }}" onsubmit="return confirm('حذف شود؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                    هیچ مقاله‌ای یافت نشد.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($articles->hasPages())
        <div class="p-4 border-t border-gray-100">
            {{ $articles->withQueryString()->links() }}
        </div>
    @endif
</div>

@endsection
