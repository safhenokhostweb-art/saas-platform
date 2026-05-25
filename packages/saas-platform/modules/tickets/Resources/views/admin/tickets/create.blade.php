@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'ایجاد تیکت جدید')

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('tickets.admin.index') }}" class="text-gray-400 hover:text-gray-600">
            &larr; بازگشت
        </a>
        <h1 class="text-xl font-bold text-gray-800">تیکت جدید</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <form method="POST" action="{{ route('tickets.admin.store') }}"
              enctype="{{ $attachmentsEnabled ? 'multipart/form-data' : 'application/x-www-form-urlencoded' }}">
            @csrf

            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">موضوع <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" value="{{ old('subject') }}" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 @error('subject') border-red-400 @enderror">
                    @error('subject')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات <span class="text-red-500">*</span></label>
                    <textarea name="description" rows="5" required
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 @error('description') border-red-400 @enderror">{{ old('description') }}</textarea>
                    @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اولویت</label>
                        <select name="priority" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="normal" @selected(old('priority') === 'normal')>معمولی</option>
                            <option value="high" @selected(old('priority') === 'high')>فوری</option>
                            <option value="low" @selected(old('priority') === 'low')>کم</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">سایت</label>
                        <select name="site_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="">بدون سایت</option>
                            @foreach($sites as $site)
                            <option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام مشتری</label>
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ایمیل مشتری</label>
                        <input type="email" name="customer_email" value="{{ old('customer_email') }}"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی</label>
                    <input type="text" name="category" value="{{ old('category') }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>

                @if($attachmentsEnabled)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">پیوست‌ها</label>
                    <input type="file" name="attachments[]" multiple
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none">
                </div>
                @endif

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-lg text-sm transition">
                        ایجاد تیکت
                    </button>
                    <a href="{{ route('tickets.admin.index') }}"
                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-6 py-2 rounded-lg text-sm transition">
                        انصراف
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
