@extends('admin.layouts.app')

@section('title', 'حاکمیت منابع سایت‌ها')

@section('content')
<div class="space-y-6" dir="rtl">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">حاکمیت منابع سایت‌ها</h1>
      <p class="text-sm text-gray-500 mt-1">مدیریت فضا، دیتابیس، ترافیک و منابع تمام سایت‌ها</p>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('admin.resource-governance.settings') }}"
         class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        تنظیمات
      </a>
    </div>
  </div>

  {{-- KPI Cards --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-indigo-50 rounded-lg">
          <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
        </div>
        <div>
          <p class="text-xs text-gray-500">مجموع فضا</p>
          <p class="text-lg font-bold text-gray-900">{{ $summary['formatted_storage'] }}</p>
        </div>
      </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-blue-50 rounded-lg">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
        </div>
        <div>
          <p class="text-xs text-gray-500">مجموع دیتابیس</p>
          <p class="text-lg font-bold text-gray-900">{{ $summary['formatted_db'] }}</p>
        </div>
      </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-amber-50 rounded-lg">
          <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
        </div>
        <div>
          <p class="text-xs text-gray-500">ترافیک ماهانه</p>
          <p class="text-lg font-bold text-gray-900">{{ $summary['formatted_bandwidth'] }}</p>
        </div>
      </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-{{ $summary['sites_over_95_pct'] > 0 ? 'red' : ($summary['sites_over_80_pct'] > 0 ? 'yellow' : 'green') }}-50 rounded-lg">
          <svg class="w-5 h-5 text-{{ $summary['sites_over_95_pct'] > 0 ? 'red' : ($summary['sites_over_80_pct'] > 0 ? 'yellow' : 'green') }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div>
          <p class="text-xs text-gray-500">بالای ۸۰٪</p>
          <p class="text-lg font-bold text-gray-900">{{ $summary['sites_over_80_pct'] }} سایت</p>
          @if($summary['sites_over_95_pct'] > 0)
            <p class="text-xs text-red-600">{{ $summary['sites_over_95_pct'] }} بحرانی</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Alerts banner for critical sites --}}
  @if($summary['sites_over_95_pct'] > 0)
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 flex items-center gap-3">
      <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <p class="text-sm text-red-800">
        <strong>{{ $summary['sites_over_95_pct'] }} سایت</strong> به ۹۵٪ ظرفیت فضا رسیده‌اند و نیاز به توجه فوری دارند.
      </p>
    </div>
  @endif

  {{-- Filters + Sort --}}
  <div class="bg-white rounded-xl border border-gray-200 p-4">
    <div class="flex flex-wrap gap-3 items-center">
      <span class="text-sm font-medium text-gray-700">فیلتر:</span>
      @foreach(['all' => 'همه', 'over80' => 'بالای ۸۰٪', 'over95' => 'بالای ۹۵٪', 'inactive' => 'غیرفعال'] as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['filter' => $key, 'page' => 1]) }}"
           class="px-3 py-1 rounded-full text-xs font-medium {{ $filter === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
          {{ $label }}
        </a>
      @endforeach
      <div class="mr-auto flex items-center gap-2">
        <span class="text-sm text-gray-600">مرتب‌سازی:</span>
        <select onchange="window.location='{{ request()->fullUrlWithQuery([]) }}&sort='+this.value"
                class="text-sm border border-gray-300 rounded-lg px-2 py-1">
          <option value="storage" {{ $sortBy === 'storage' ? 'selected' : '' }}>فضا</option>
          <option value="db" {{ $sortBy === 'db' ? 'selected' : '' }}>دیتابیس</option>
          <option value="archive" {{ $sortBy === 'archive' ? 'selected' : '' }}>آرشیو</option>
          <option value="bandwidth" {{ $sortBy === 'bandwidth' ? 'selected' : '' }}>ترافیک</option>
          <option value="files" {{ $sortBy === 'files' ? 'selected' : '' }}>تعداد فایل</option>
        </select>
      </div>
    </div>
  </div>

  {{-- Sites Table --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @if($usages->isEmpty())
      <div class="text-center py-16">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        <p class="text-gray-500 text-sm">هنوز داده‌ای محاسبه نشده.</p>
        <p class="text-gray-400 text-xs mt-1">دستور <code>php artisan resource:calculate</code> را اجرا کنید.</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
            <tr>
              <th class="px-4 py-3 text-right">سایت</th>
              <th class="px-4 py-3 text-center">فضا</th>
              <th class="px-4 py-3 text-center">دیتابیس</th>
              <th class="px-4 py-3 text-center">آرشیو</th>
              <th class="px-4 py-3 text-center">ترافیک ماه</th>
              <th class="px-4 py-3 text-center">فایل‌ها</th>
              <th class="px-4 py-3 text-center">محاسبه</th>
              <th class="px-4 py-3 text-center">عملیات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach($usages as $usage)
              @php
                $pct = $usage->storagePercent($limitBytes);
                $barColor = $pct >= 95 ? 'bg-red-500' : ($pct >= 80 ? 'bg-yellow-500' : 'bg-indigo-500');
              @endphp
              <tr class="hover:bg-gray-50 {{ $pct >= 95 ? 'bg-red-50' : ($pct >= 80 ? 'bg-yellow-50' : '') }}">
                <td class="px-4 py-3">
                  <div class="font-medium text-gray-900">{{ $usage->site->title ?? 'سایت #'.$usage->site_id }}</div>
                  <div class="text-xs text-gray-400">{{ $usage->site->domain ?? '-' }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="flex flex-col items-center gap-1">
                    <span class="font-medium {{ $pct >= 95 ? 'text-red-600' : ($pct >= 80 ? 'text-yellow-700' : 'text-gray-700') }}">
                      {{ $usage->formattedStorage() }}
                    </span>
                    <div class="w-20 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                      <div class="{{ $barColor }} h-full rounded-full" style="width: {{ min(100, $pct) }}%"></div>
                    </div>
                    <span class="text-xs text-gray-500">{{ $pct }}٪</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-center text-gray-700">{{ $usage->formattedDb() }}</td>
                <td class="px-4 py-3 text-center">
                  @if($usage->archive_bytes > 0)
                    <span class="text-gray-700">{{ $usage->formattedArchive() }}</span>
                  @else
                    <span class="text-gray-300">—</span>
                  @endif
                </td>
                <td class="px-4 py-3 text-center text-gray-700">{{ $usage->formattedBandwidth() }}</td>
                <td class="px-4 py-3 text-center text-gray-700">{{ number_format($usage->file_count) }}</td>
                <td class="px-4 py-3 text-center text-xs text-gray-400">
                  @if($usage->calculated_at)
                    {{ \App\Helpers\Jalali::diffForHumansFa($usage->calculated_at) }}
                  @else
                    <span class="text-red-400">هنوز نه</span>
                  @endif
                </td>
                <td class="px-4 py-3 text-center">
                  <a href="{{ route('admin.resource-governance.site', $usage->site_id) }}"
                     class="inline-flex items-center gap-1 px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-medium hover:bg-indigo-100">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    جزئیات
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
        <p class="text-xs text-gray-500">
          {{ $usages->total() }} سایت
          @if($sitesWithNoRecord > 0)
            · <span class="text-amber-600">{{ $sitesWithNoRecord }} سایت هنوز محاسبه نشده</span>
          @endif
        </p>
        {{ $usages->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
