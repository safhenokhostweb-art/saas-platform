@extends('layouts.admin')

@section('title', 'تأیید قالب هوش مصنوعی')

@section('content')
<div dir="rtl" class="max-w-2xl mx-auto space-y-6">
  <div>
    <h1 class="text-2xl font-bold text-gray-900">تأیید و ذخیره قالب</h1>
    <p class="text-sm text-gray-500 mt-1">قالب زیر از تحلیل هوش مصنوعی تولید شده. نام آن را بررسی کنید و ذخیره کنید.</p>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7">
    <div class="grid grid-cols-2 gap-4 mb-6">
      <div class="bg-indigo-50 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-indigo-600">{{ count($themeJson['header_blocks'] ?? []) }}</p>
        <p class="text-xs text-gray-500 mt-1">بلوک هدر</p>
      </div>
      <div class="bg-purple-50 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-purple-600">{{ count($themeJson['pages']['home']['blocks'] ?? []) }}</p>
        <p class="text-xs text-gray-500 mt-1">بلوک صفحه اصلی</p>
      </div>
    </div>

    <form method="POST" action="{{ route('admin.theme-builder.ai-generate') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">نام قالب</label>
        <input type="text" name="name"
          value="{{ old('name', $themeJson['suggested_name'] ?? 'قالب هوش مصنوعی') }}"
          required maxlength="255"
          class="w-full border border-gray-300 rounded-xl text-sm px-4 py-3 focus:ring-2 focus:ring-indigo-500">
      </div>
      <button type="submit"
        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition">
        ذخیره و باز کردن ویرایشگر بصری
      </button>
    </form>
  </div>
</div>
@endsection
