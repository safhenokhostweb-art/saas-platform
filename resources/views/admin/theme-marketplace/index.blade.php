@extends('layouts.admin')

@section('title', 'مارکت‌پلیس قالب‌ها')

@section('content')
<div class="space-y-6" dir="rtl">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">مارکت‌پلیس قالب‌ها</h1>
      <p class="text-sm text-gray-500 mt-1">قالب‌های قابل فروش را مدیریت کنید</p>
    </div>
    <a href="{{ route('admin.theme-marketplace.licenses') }}"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      لایسنس‌ها و خریدها
    </a>
  </div>

  {{-- Stats --}}
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <div>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalLicenses) }}</p>
        <p class="text-sm text-gray-500">لایسنس فعال</p>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <div>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalRevenue) }}</p>
        <p class="text-sm text-gray-500">درآمد کل (ریال)</p>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <div>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($pendingOrders) }}</p>
        <p class="text-sm text-gray-500">سفارش در انتظار</p>
      </div>
    </div>
  </div>


  {{-- Themes Table --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-gray-700">قالب‌های پلتفرم ({{ $themes->count() }})</h2>
      <a href="{{ route('admin.theme-builder.index') }}"
         class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">+ قالب جدید</a>
    </div>
    <div class="divide-y divide-gray-50">
      @forelse($themes as $theme)
        @php $meta = $theme->marketplaceMeta; @endphp
        <div x-data="{ open: false }" class="px-6 py-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4 min-w-0">
              {{-- Thumbnail --}}
              <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                @if($theme->thumbnail)
                  <img src="{{ $theme->thumbnail }}" class="w-full h-full object-cover" alt="">
                @else
                  <div class="w-full h-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </div>
                @endif
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium text-gray-900 text-sm">{{ $theme->name }}</span>
                  @if($theme->is_marketplace)
                    <span class="inline-flex items-center gap-1 bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-medium">
                      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                      مارکت
                    </span>
                    @if($meta?->is_featured)
                      <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full font-medium">ویژه</span>
                    @endif
                  @endif
                  <span class="text-xs text-gray-400">{{ $theme->status === 'published' ? 'منتشر' : 'پیش‌نویس' }}</span>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">{{ $theme->slug }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0 mr-4">
              @if($theme->is_marketplace)
                <span class="text-sm font-semibold text-gray-800">{{ number_format($theme->marketplace_price) }} ریال</span>
                <span class="text-xs text-gray-400">{{ $theme->licenses()->count() }} لایسنس</span>
                <form method="POST" action="{{ route('admin.theme-marketplace.featured', $theme) }}">
                  @csrf
                  <button type="submit" title="تغییر وضعیت ویژه"
                    class="text-amber-500 hover:text-amber-700 transition">
                    <svg class="w-5 h-5 {{ $meta?->is_featured ? 'fill-amber-400' : '' }}" fill="{{ $meta?->is_featured ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                  </button>
                </form>
              @endif
              <button @click="open = !open"
                class="inline-flex items-center gap-1.5 bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-gray-600 text-xs font-medium px-3 py-1.5 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                تنظیم
              </button>
            </div>
          </div>

          {{-- Inline Edit Form --}}
          <div x-show="open" x-cloak class="mt-4 bg-gray-50 rounded-xl p-5 border border-gray-200">
            <form method="POST" action="{{ route('admin.theme-marketplace.update-listing', $theme) }}">
              @csrf
              @method('PUT')
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1.5">وضعیت مارکت‌پلیس</label>
                  <select name="is_marketplace" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="1" {{ $theme->is_marketplace ? 'selected' : '' }}>فعال — در فروشگاه نمایش داده شود</option>
                    <option value="0" {{ !$theme->is_marketplace ? 'selected' : '' }}>غیرفعال</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1.5">قیمت (ریال)</label>
                  <input type="number" name="marketplace_price" min="0"
                    value="{{ old('marketplace_price', $theme->marketplace_price) }}"
                    class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1.5">یادداشت نسخه</label>
                  <input type="text" name="version_notes" maxlength="500"
                    value="{{ old('version_notes', $meta?->version_notes) }}"
                    placeholder="مثلاً: نسخه ۱.۲ — افزوده‌شده‌ها..."
                    class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div class="flex items-center gap-3">
                  <label class="block text-xs font-medium text-gray-600">ویژه (featured)</label>
                  <input type="hidden" name="is_featured" value="0">
                  <input type="checkbox" name="is_featured" value="1" {{ $meta?->is_featured ? 'checked' : '' }}
                    class="rounded text-indigo-600">
                </div>
              </div>
              <div class="mb-4">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">توضیح مارکت‌پلیس</label>
                <textarea name="marketplace_description" rows="3"
                  class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                  placeholder="توضیح کامل قالب برای صفحه فروشگاه...">{{ old('marketplace_description', $meta?->marketplace_description) }}</textarea>
              </div>
              <div class="mb-4">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">آدرس تصاویر (هر خط یک URL)</label>
                <textarea name="screenshots[]" rows="3"
                  class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                  placeholder="https://...">{{ implode("\n", $meta?->screenshots ?? []) }}</textarea>
              </div>
              <div class="mb-5">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">ماژول‌های مورد نیاز (slug، هر خط یک مورد)</label>
                <textarea name="requirements[]" rows="2"
                  class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                  placeholder="courses&#10;blog">{{ implode("\n", $meta?->requirements ?? []) }}</textarea>
              </div>
              <div class="flex items-center gap-3">
                <button type="submit"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  ذخیره
                </button>
                <button type="button" @click="open = false"
                  class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">انصراف</button>
              </div>
            </form>
          </div>
        </div>
      @empty
        <div class="px-6 py-12 text-center">
          <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
          <p class="text-gray-400 text-sm">هنوز قالب پلتفرمی وجود ندارد.</p>
          <a href="{{ route('admin.theme-builder.create') }}" class="text-indigo-600 text-sm mt-2 inline-block">+ ساخت قالب جدید</a>
        </div>
      @endforelse
    </div>
  </div>

</div>
@endsection
