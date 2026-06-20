@extends('admin.layout')

@section('title', 'TB-AI Studio — واردسازی قالب‌ها')

@section('content')
<div class="space-y-6">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">TB-AI Studio — واردسازی قالب‌ها</h1>
      <p class="text-gray-500 text-sm mt-1">قالب‌های تولیدشده توسط ابزار محلی TB-AI Studio</p>
    </div>
    <a href="{{ route('admin.theme-packs.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      همه پک‌ها
    </a>
  </div>

  {{-- API Key Status --}}
  @if(!$apiConfigured)
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
    <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2z"/></svg>
    <div>
      <p class="font-semibold text-amber-800 text-sm">Studio API پیکربندی نشده</p>
      <p class="text-amber-700 text-xs mt-1">برای فعال‌سازی آپلود خودکار از ابزار محلی، متغیر <code class="bg-amber-100 px-1 rounded">STUDIO_API_KEY</code> را در فایل <code class="bg-amber-100 px-1 rounded">.env</code> تنظیم کنید.</p>
    </div>
  </div>
  @else
  <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
    <p class="text-green-800 text-sm font-medium">Studio API فعال — endpoint: <code class="bg-green-100 px-1 rounded text-xs">POST /api/v1/studio/import</code></p>
  </div>
  @endif

  {{-- Pack Table --}}
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h2 class="font-semibold text-gray-900">پک‌های وارد شده از Studio</h2>
      <span class="text-sm text-gray-500">{{ $packs->total() }} پک</span>
    </div>

    @if($packs->isEmpty())
    <div class="text-center py-16">
      <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      </div>
      <p class="text-gray-500 font-medium">هنوز هیچ قالبی از Studio وارد نشده</p>
      <p class="text-gray-400 text-sm mt-2">قالب‌ها را از ابزار محلی TB-AI Studio آپلود کنید.</p>
    </div>
    @else
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="text-right px-6 py-3 text-gray-500 font-medium">نام قالب</th>
            <th class="text-right px-6 py-3 text-gray-500 font-medium">نسخه</th>
            <th class="text-right px-6 py-3 text-gray-500 font-medium">صفحات</th>
            <th class="text-right px-6 py-3 text-gray-500 font-medium">قالب‌ها</th>
            <th class="text-right px-6 py-3 text-gray-500 font-medium">وضعیت</th>
            <th class="text-right px-6 py-3 text-gray-500 font-medium">تاریخ</th>
            <th class="px-6 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @foreach($packs as $pack)
          @php $summary = $pack->contentSummary(); @endphp
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4">
              <div class="font-semibold text-gray-900">{{ $pack->name }}</div>
              <div class="text-xs text-gray-400 mt-0.5">{{ $pack->uuid }}</div>
            </td>
            <td class="px-6 py-4 text-gray-600">{{ $pack->version }}</td>
            <td class="px-6 py-4 text-gray-600">{{ $summary['pages'] }}</td>
            <td class="px-6 py-4 text-gray-600">{{ $summary['templates'] }}</td>
            <td class="px-6 py-4">
              @if($pack->is_public)
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> منتشرشده
                </span>
              @else
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> در انتظار انتشار
                </span>
              @endif
            </td>
            <td class="px-6 py-4 text-gray-500 text-xs">{{ $pack->created_at->diffForHumans() }}</td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-2 justify-end">
                <a href="{{ route('admin.studio-imports.show', $pack) }}"
                   class="px-3 py-1.5 text-xs bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 font-medium">مشاهده</a>
                @unless($pack->is_public)
                <form action="{{ route('admin.studio-imports.publish', $pack) }}" method="POST" class="inline">
                  @csrf
                  <button type="submit" class="px-3 py-1.5 text-xs bg-green-50 text-green-700 rounded-lg hover:bg-green-100 font-medium">انتشار</button>
                </form>
                @endunless
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if($packs->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">{{ $packs->links() }}</div>
    @endif
    @endif
  </div>

  {{-- Setup Guide --}}
  <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl p-6 border border-indigo-100">
    <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
      <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2z"/></svg>
      راهنمای راه‌اندازی TB-AI Studio
    </h3>
    <div class="space-y-3 text-sm text-gray-700">
      <div class="flex gap-3">
        <span class="w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">۱</span>
        <p>ابزار TB-AI Studio را روی ماشین محلی نصب کنید: <code class="bg-white px-2 py-0.5 rounded border text-xs">cd tb-ai-studio && composer install</code></p>
      </div>
      <div class="flex gap-3">
        <span class="w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">۲</span>
        <p>فایل <code class="bg-white px-2 py-0.5 rounded border text-xs">.env</code> را از <code class="bg-white px-2 py-0.5 rounded border text-xs">.env.example</code> کپی و API key ها را تنظیم کنید.</p>
      </div>
      <div class="flex gap-3">
        <span class="w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">۳</span>
        <p>در .env سرور: <code class="bg-white px-2 py-0.5 rounded border text-xs">STUDIO_API_KEY=your-secret-key</code> را تنظیم کنید.</p>
      </div>
      <div class="flex gap-3">
        <span class="w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">۴</span>
        <p>در .env ابزار محلی: <code class="bg-white px-2 py-0.5 rounded border text-xs">SAAS_PLATFORM_URL</code> و <code class="bg-white px-2 py-0.5 rounded border text-xs">SAAS_PLATFORM_API_KEY</code> را با همان مقدار تنظیم کنید.</p>
      </div>
    </div>
  </div>

</div>
@endsection
