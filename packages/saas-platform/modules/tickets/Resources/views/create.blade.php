@push('head')
@asset_module('tickets', 'css/tickets.css')
@endpush

@extends('admin.layout')
@section('title', 'ثبت تیکت جدید')
@section('header', 'ثبت تیکت جدید')

@section('content')
<div class="max-w-2xl">
    <form action="{{ route('admin.tickets.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-6">
            {{-- Subject --}}
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">موضوع</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all @error('subject') border-red-300 @enderror"
                    placeholder="موضوع تیکت را وارد کنید">
                @error('subject')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" id="description" rows="6"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all @error('description') border-red-300 @enderror"
                    placeholder="توضیحات تیکت را وارد کنید">{{ old('description') }}</textarea>
                @error('description')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Priority --}}
            <div>
                <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">اولویت</label>
                <select name="priority" id="priority"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
                    <option value="normal" {{ old('priority') === 'normal' ? 'selected' : '' }}>عادی</option>
                    <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>کم</option>
                    <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>بالا</option>
                </select>
            </div>

            {{-- Site --}}
            <div>
                <label for="site_id" class="block text-sm font-medium text-gray-700 mb-1">سایت</label>
                <select name="site_id" id="site_id"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
                    <option value="">انتخاب سایت</option>
                    @foreach($sites as $site)
                    <option value="{{ $site->id }}" {{ old('site_id') == $site->id ? 'selected' : '' }}>{{ $site->title }} ({{ $site->domain }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Customer Info --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">نام مشتری</label>
                    <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all"
                        placeholder="نام مشتری">
                </div>
                <div>
                    <label for="customer_mobile" class="block text-sm font-medium text-gray-700 mb-1">موبایل مشتری</label>
                    <input type="text" name="customer_mobile" id="customer_mobile" value="{{ old('customer_mobile') }}"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all"
                        placeholder="09xxxxxxxxx">
                </div>
            </div>

            {{-- Category --}}
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی</label>
                <input type="text" name="category" id="category" value="{{ old('category') }}"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all"
                    placeholder="دسته‌بندی تیکت">
            </div>

            {{-- Attachments --}}
            <div>
                <label for="attachments" class="block text-sm font-medium text-gray-700 mb-1">فایل‌های ضمیمه</label>
                <input type="file" name="attachments[]" id="attachments" multiple
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 shadow-sm transition-all">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                ثبت تیکت
            </button>
            <a href="{{ route('admin.tickets.index') }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 transition-all">
                انصراف
            </a>
        </div>
    </form>
</div>
@endsection
