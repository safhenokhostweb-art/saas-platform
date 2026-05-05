@extends('layouts.admin')

@section('header', \App\Helpers\ModuleLabels::getTitle('module_labels'))

@section('content')
@include('admin.module-labels.guide')
<div class="max-w-4xl">
    <div class="bg-white rounded-2xl shadow p-6">
        <p class="text-gray-500 text-sm mb-6">
            در این صفحه می‌توانید عنوان و توضیح هر آیتم منو (ماژول) را به‌صورت سفارشی تغییر دهید. مثلاً «تنظیمات کپچا» را به «کد امنیتی» تغییر دهید. اگر فیلد را خالی بگذارید، عنوان پیش‌فرض نمایش داده می‌شود.
        </p>

        <form method="POST" action="{{ route('admin.module-labels.update') }}" class="space-y-6">
            @csrf
            @if($siteId !== null)
                <input type="hidden" name="site_id" value="{{ $siteId }}">
            @endif

            <div class="space-y-4">
                @foreach($items as $index => $item)
                    <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/50">
                        <input type="hidden" name="labels[{{ $index }}][key]" value="{{ $item['key'] }}">
                        <div class="mb-3">
                            <span class="text-xs font-mono text-gray-400">{{ $item['key'] }}</span>
                            <span class="text-gray-500 text-sm mr-2">— پیش‌فرض:</span>
                            <span class="font-medium">{{ $item['default_title'] }}</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label class="form-label">عنوان نمایشی</label>
                                <input type="text"
                                       name="labels[{{ $index }}][custom_title]"
                                       class="form-control"
                                       value="{{ old('labels.'.$index.'.custom_title', $item['custom_title']) }}"
                                       placeholder="{{ $item['default_title'] }}">
                            </div>
                            <div>
                                <label class="form-label">توضیح (اختیاری)</label>
                                <textarea name="labels[{{ $index }}][custom_description]"
                                          class="form-control"
                                          rows="2"
                                          placeholder="{{ $item['default_description'] ?: 'توضیح کوتاه' }}">{{ old('labels.'.$index.'.custom_description', $item['custom_description']) }}</textarea>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-4">
                <button type="submit" class="btn btn-primary">ذخیره عنوان و جزئیات ماژول‌ها</button>
                <a href="{{ route('admin.access.index') }}" class="btn btn-secondary mr-2">بازگشت به دسترسی و منو</a>
            </div>
        </form>
    </div>
</div>
@endsection
