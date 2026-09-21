@extends('layouts.admin')

@section('title', 'لایسنس‌های قالب')

@section('content')
<div class="space-y-6" dir="rtl">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">لایسنس‌های قالب</h1>
      <p class="text-sm text-gray-500 mt-1">تمام لایسنس‌های اعطا‌شده و سفارش‌ها</p>
    </div>
    <div class="flex items-center gap-3">
      {{-- Grant manual license --}}
      <button onclick="document.getElementById('modal-grant').classList.remove('hidden')"
        class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        اعطای دستی
      </button>
      <a href="{{ route('admin.theme-marketplace.index') }}"
         class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">← برگشت به مارکت‌پلیس</a>
    </div>
  </div>


  {{-- Licenses --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-700">لایسنس‌های فعال ({{ $licenses->total() }})</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">قالب</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">سایت</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">کلید لایسنس</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">Release / Assignment</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">PlatformLicense</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">تاریخ خرید</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">عملیات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @forelse($licenses as $lic)
            <tr class="hover:bg-gray-50/50 transition">
              <td class="px-6 py-3 font-medium text-gray-800">{{ $lic->theme?->name ?? '—' }}</td>
              <td class="px-6 py-3 text-gray-600">{{ $lic->site?->title ?? '—' }} <span class="text-xs text-gray-400">#{{ $lic->site_id }}</span></td>
              <td class="px-6 py-3 font-mono text-xs text-gray-500 select-all">{{ $lic->license_key }}</td>
              <td class="px-6 py-3 text-xs text-gray-600">
                <div>{{ $lic->licensedRelease?->version ?? '—' }}</div>
                <div class="text-gray-400">{{ $lic->assignment?->status ?? 'بدون Assignment' }}</div>
              </td>
              <td class="px-6 py-3 text-xs">
                @if($lic->assignment?->platformLicense)
                  <span class="text-green-700">فعال #{{ $lic->assignment->platform_license_id }}</span>
                @else
                  <span class="text-red-600">ناموجود</span>
                @endif
              </td>
              <td class="px-6 py-3 text-gray-500 text-xs">{{ \App\Helpers\Jalali::formatFa($lic->purchased_at, 'Y/m/d') }}</td>
              <td class="px-6 py-3">
                <form method="POST" action="{{ route('admin.theme-marketplace.revoke-license', $lic) }}"
                  onsubmit="return confirm('لغو لایسنس؟')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium transition">لغو</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-400">لایسنسی ثبت نشده</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($licenses->hasPages())
      <div class="px-6 py-4 border-t border-gray-100">
        {{ $licenses->links() }}
      </div>
    @endif
  </div>

  {{-- Recent Orders --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-700">سفارش‌های اخیر</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">#</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">قالب</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">سایت</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">مبلغ</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">درگاه</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">وضعیت</th>
            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500">تاریخ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @forelse($orders as $order)
            @php
              $statusMap = ['pending'=>['text'=>'در انتظار','cls'=>'bg-amber-100 text-amber-700'],'paid'=>['text'=>'پرداخت‌شده','cls'=>'bg-green-100 text-green-700'],'failed'=>['text'=>'ناموفق','cls'=>'bg-red-100 text-red-700'],'cancelled'=>['text'=>'لغو‌شده','cls'=>'bg-gray-100 text-gray-500']];
              $st = $statusMap[$order->status] ?? ['text'=>$order->status,'cls'=>'bg-gray-100 text-gray-500'];
            @endphp
            <tr class="hover:bg-gray-50/50 transition">
              <td class="px-6 py-3 text-gray-400 text-xs">#{{ $order->id }}</td>
              <td class="px-6 py-3 font-medium text-gray-800">{{ $order->theme?->name ?? '—' }}</td>
              <td class="px-6 py-3 text-gray-600">{{ $order->site?->title ?? '—' }}</td>
              <td class="px-6 py-3 font-mono text-gray-700">@numFa($order->amount_rial)</td>
              <td class="px-6 py-3 text-gray-500">{{ $order->gateway ?? '—' }}</td>
              <td class="px-6 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $st['cls'] }}">{{ $st['text'] }}</span></td>
              <td class="px-6 py-3 text-gray-400 text-xs">{{ \App\Helpers\Jalali::formatFa($order->created_at, 'Y/m/d') }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-400">سفارشی ثبت نشده</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- Grant License Modal --}}
<div id="modal-grant" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" dir="rtl">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-5">اعطای دستی لایسنس</h3>
    <form method="POST" action="{{ route('admin.theme-marketplace.grant-license') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">شناسه سایت</label>
        <input type="number" name="site_id" required min="1"
          class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">شناسه قالب</label>
        <input type="number" name="theme_id" required min="1"
          class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div class="flex items-center gap-3 pt-2">
        <button type="submit"
          class="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">اعطا</button>
        <button type="button" onclick="document.getElementById('modal-grant').classList.add('hidden')"
          class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition">انصراف</button>
      </div>
    </form>
  </div>
</div>
@endsection
