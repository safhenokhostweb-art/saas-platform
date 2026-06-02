@extends('layouts.admin')

@section('header', 'دسته‌بندی دانشنامه')

@section('content')

@if(session('success'))
<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- List --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">دسته‌بندی‌ها</h2>
            <a href="{{ route('admin.kb.index') }}" class="text-sm text-indigo-600 hover:underline">بازگشت به مقالات</a>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            @forelse($categories as $cat)
            <div class="p-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    @if($cat->icon)
                        <span class="text-lg">{{ $cat->icon }}</span>
                    @endif
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $cat->name }}</p>
                        @if($cat->children->isNotEmpty())
                        <p class="text-xs text-gray-400">{{ $cat->children->count() }} زیردسته</p>
                        @endif
                    </div>
                    <span class="text-xs {{ $cat->is_active ? 'text-green-600' : 'text-gray-400' }}">
                        {{ $cat->is_active ? '● فعال' : '○ غیرفعال' }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.kb.categories.update', $cat) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="name" value="{{ $cat->name }}">
                        <input type="hidden" name="is_active" value="{{ $cat->is_active ? 0 : 1 }}">
                        <button class="text-xs {{ $cat->is_active ? 'text-orange-500' : 'text-green-600' }}">
                            {{ $cat->is_active ? 'غیرفعال' : 'فعال' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.kb.categories.destroy', $cat) }}" onsubmit="return confirm('حذف شود؟')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-500">حذف</button>
                    </form>
                </div>
            </div>
            @empty
            <div class="p-8 text-center text-gray-400 text-sm">هیچ دسته‌بندی‌ای وجود ندارد.</div>
            @endforelse
        </div>
    </div>

    {{-- Create form --}}
    <div>
        <h2 class="text-lg font-bold text-gray-800 mb-4">دسته‌بندی جدید</h2>
        <form method="POST" action="{{ route('admin.kb.categories.store') }}"
              class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نام *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">آیکون (اموجی)</label>
                <input type="text" name="icon" value="{{ old('icon') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                       placeholder="مثال: 📚 🔧 💡">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">زیردسته از</label>
                <select name="parent_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— دسته اصلی —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('parent_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit"
                    class="w-full py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                اضافه کردن
            </button>
        </form>
    </div>

</div>

@endsection
