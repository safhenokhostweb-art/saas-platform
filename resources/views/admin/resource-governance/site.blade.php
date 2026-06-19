@extends('admin.layouts.app')

@section('title', 'منابع: ' . $site->title)

@section('content')
<div class="space-y-6" dir="rtl">

  {{-- Breadcrumb --}}
  <nav class="flex items-center gap-2 text-sm text-gray-500">
    <a href="{{ route('admin.resource-governance.index') }}" class="hover:text-indigo-600">حاکمیت منابع</a>
    <span>/</span>
    <span class="text-gray-900 font-medium">{{ $site->title }}</span>
  </nav>

  @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
  @endif

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">{{ $site->title }}</h1>
      <p class="text-sm text-gray-500 mt-1">{{ $site->domain }}</p>
    </div>
    <form method="POST" action="{{ route('admin.resource-governance.recalculate', $site->id) }}">
      @csrf
      <button type="submit"
              class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        محاسبه مجدد
      </button>
    </form>
  </div>

  {{-- Usage Cards --}}
  @if($usage->exists)
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      {{-- Storage --}}
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-2">فضای فایل</p>
        <p class="text-2xl font-bold {{ $storagePct >= 95 ? 'text-red-600' : ($storagePct >= 80 ? 'text-yellow-700' : 'text-gray-900') }}">
          {{ $usage->formattedStorage() }}
        </p>
        <div class="mt-2">
          <div class="flex justify-between text-xs text-gray-500 mb-1">
            <span>{{ $storagePct }}٪</span>
            <span>از {{ $limit->formattedStorageLimit() }}</span>
          </div>
          <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="{{ $storagePct >= 95 ? 'bg-red-500' : ($storagePct >= 80 ? 'bg-yellow-500' : 'bg-indigo-500') }} h-full rounded-full transition-all"
                 style="width: {{ min(100, $storagePct) }}%"></div>
          </div>
        </div>
      </div>
      {{-- DB --}}
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-2">دیتابیس</p>
        <p class="text-2xl font-bold text-gray-900">{{ $usage->formattedDb() }}</p>
        <p class="text-xs text-gray-400 mt-2">{{ $site->db_name ?? 'نامشخص' }}</p>
      </div>
      {{-- Bandwidth --}}
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-2">ترافیک ماهانه</p>
        <p class="text-2xl font-bold {{ $bandwidthPct >= 95 ? 'text-red-600' : ($bandwidthPct >= 80 ? 'text-yellow-700' : 'text-gray-900') }}">
          {{ $usage->formattedBandwidth() }}
        </p>
        <div class="mt-2">
          <div class="flex justify-between text-xs text-gray-500 mb-1">
            <span>{{ $bandwidthPct }}٪</span>
            <span>از {{ $limit->formattedBandwidthLimit() }}</span>
          </div>
          <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="{{ $bandwidthPct >= 95 ? 'bg-red-500' : ($bandwidthPct >= 80 ? 'bg-yellow-500' : 'bg-blue-500') }} h-full rounded-full"
                 style="width: {{ min(100, $bandwidthPct) }}%"></div>
          </div>
        </div>
      </div>
      {{-- Archive --}}
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-2">آرشیو</p>
        <p class="text-2xl font-bold text-gray-900">{{ $usage->formattedArchive() }}</p>
        <p class="text-xs text-gray-400 mt-2">
          {{ $usage->file_count > 0 ? number_format($usage->file_count) . ' فایل' : 'بدون فایل' }}
        </p>
      </div>
    </div>

    {{-- Additional stats --}}
    <div class="grid grid-cols-3 gap-4 text-sm">
      <div class="bg-white rounded-xl border border-gray-200 p-3 flex items-center gap-3">
        <div class="p-2 bg-gray-50 rounded-lg"><svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg></div>
        <div>
          <p class="text-xs text-gray-500">پشتیبان</p>
          <p class="font-semibold text-gray-900">{{ $usage->formattedArchive() }}</p>
        </div>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-3 flex items-center gap-3">
        <div class="p-2 bg-gray-50 rounded-lg"><svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg></div>
        <div>
          <p class="text-xs text-gray-500">وزن CPU</p>
          <p class="font-semibold text-gray-900">{{ $usage->cpu_weight }}/۱۰۰</p>
        </div>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-3 flex items-center gap-3">
        <div class="p-2 bg-gray-50 rounded-lg"><svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
        <div>
          <p class="text-xs text-gray-500">آخرین محاسبه</p>
          <p class="font-semibold text-gray-900 text-xs">{{ $usage->calculated_at ? \App\Helpers\Jalali::diffForHumansFa($usage->calculated_at) : 'هنوز نه' }}</p>
        </div>
      </div>
    </div>

  @else
    <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-4 text-sm text-amber-800">
      هنوز داده‌ای برای این سایت محاسبه نشده. روی «محاسبه مجدد» کلیک کنید.
    </div>
  @endif

  {{-- Overage Fees --}}
  @if(!empty($overageFees))
    <div class="bg-white rounded-xl border border-red-200 overflow-hidden">
      <div class="px-4 py-3 bg-red-50 border-b border-red-200">
        <h2 class="font-semibold text-red-800 text-sm">هزینه‌های اضافه‌مصرف</h2>
      </div>
      <div class="p-4 space-y-2">
        @foreach($overageFees as $item)
          <div class="flex justify-between items-center text-sm py-2 border-b border-gray-100 last:border-0">
            <span class="text-gray-700">{{ $item['label'] }}</span>
            <span class="font-semibold text-red-600">{{ number_format($item['amount']) }} ریال</span>
          </div>
        @endforeach
        <div class="flex justify-between items-center text-sm font-bold pt-2">
          <span class="text-gray-900">جمع کل</span>
          <span class="text-red-700">{{ number_format(array_sum(array_column($overageFees, 'amount'))) }} ریال</span>
        </div>
      </div>
    </div>
  @endif

  {{-- Storage Trend Chart --}}
  @if($snapshots->count() > 1)
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <h2 class="font-semibold text-gray-800 text-sm mb-4">روند فضای ذخیره‌سازی (۳۰ روز اخیر)</h2>
      <div class="relative h-40" x-data="resourceChart()" x-init="init()">
        <canvas id="resourceChart" class="w-full h-full"></canvas>
      </div>
    </div>
    <script>
    function resourceChart() {
        return {
            init() {
                const ctx = document.getElementById('resourceChart').getContext('2d');
                const dates   = @json($chartDates);
                const storage = @json($chartStorage);
                const db      = @json($chartDb);
                const limitB  = {{ (int) $limit->storage_limit_bytes }};

                if (typeof Chart === 'undefined') return;

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [
                            {
                                label: 'فضای فایل',
                                data: storage.map(b => Math.round(b / 1048576)),
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99,102,241,0.1)',
                                fill: true, tension: 0.3, pointRadius: 2,
                            },
                            {
                                label: 'دیتابیس',
                                data: db.map(b => Math.round(b / 1048576)),
                                borderColor: '#3b82f6',
                                backgroundColor: 'transparent',
                                tension: 0.3, pointRadius: 2,
                            }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'top', rtl: true } },
                        scales: {
                            y: { title: { display: true, text: 'مگابایت' } }
                        }
                    }
                });
            }
        }
    }
    </script>
  @endif

  {{-- Per-Site Limit Override --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
      <h2 class="font-semibold text-gray-800 text-sm">محدودیت‌های منابع</h2>
      @if(!$override)
        <p class="text-xs text-gray-500 mt-0.5">در حال حاضر از تنظیمات پیش‌فرض پلتفرم استفاده می‌شود.</p>
      @else
        <p class="text-xs text-indigo-600 mt-0.5">محدودیت اختصاصی برای این سایت تنظیم شده.</p>
      @endif
    </div>
    <form method="POST" action="{{ route('admin.resource-governance.update-limits', $site->id) }}" class="p-4">
      @csrf @method('PUT')
      @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-4 text-xs text-red-700">{{ $errors->first() }}</div>
      @endif
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">سقف فضا (گیگابایت)</label>
          <input type="number" step="0.1" min="0.1" name="storage_limit_gb"
                 value="{{ old('storage_limit_gb', round($limit->storage_limit_bytes / 1073741824, 1)) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">سقف ترافیک (گیگابایت/ماه)</label>
          <input type="number" step="0.1" min="0.1" name="bandwidth_limit_gb"
                 value="{{ old('bandwidth_limit_gb', round($limit->bandwidth_limit_bytes / 1073741824, 1)) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">نگهداری آرشیو (روز)</label>
          <input type="number" min="1" name="archive_retention_days"
                 value="{{ old('archive_retention_days', $limit->archive_retention_days) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">نگهداری پشتیبان (روز)</label>
          <input type="number" min="1" name="backup_retention_days"
                 value="{{ old('backup_retention_days', $limit->backup_retention_days) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">وزن CPU</label>
          <input type="number" min="1" max="100" name="cpu_weight"
                 value="{{ old('cpu_weight', $limit->cpu_weight) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">وزن RAM</label>
          <input type="number" min="1" max="100" name="ram_weight"
                 value="{{ old('ram_weight', $limit->ram_weight) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
      </div>
      <div class="mt-4 border-t border-gray-100 pt-4">
        <p class="text-xs font-medium text-gray-700 mb-3">هزینه‌های اضافه‌مصرف (ریال)</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-xs text-gray-600 mb-1">اضافه فضا (هر گیگ)</label>
            <input type="number" min="0" name="storage_overage_fee_per_gb"
                   value="{{ old('storage_overage_fee_per_gb', $limit->storage_overage_fee_per_gb) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">اضافه ترافیک (هر گیگ)</label>
            <input type="number" min="0" name="bandwidth_overage_fee_per_gb"
                   value="{{ old('bandwidth_overage_fee_per_gb', $limit->bandwidth_overage_fee_per_gb) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">نگهداری آرشیو</label>
            <input type="number" min="0" name="archive_retention_fee"
                   value="{{ old('archive_retention_fee', $limit->archive_retention_fee) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">هزینه بازگردانی</label>
            <input type="number" min="0" name="restore_fee"
                   value="{{ old('restore_fee', $limit->restore_fee) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          </div>
        </div>
      </div>
      <div class="mt-4 flex justify-end">
        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">ذخیره محدودیت‌ها</button>
      </div>
    </form>
  </div>

  {{-- Snapshot History --}}
  @if($snapshots->count() > 0)
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
        <h2 class="font-semibold text-gray-800 text-sm">تاریخچه اسنپ‌شات‌ها (۳۰ روز)</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-50 text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-2 text-right">تاریخ</th>
              <th class="px-4 py-2 text-center">فضا</th>
              <th class="px-4 py-2 text-center">دیتابیس</th>
              <th class="px-4 py-2 text-center">پشتیبان</th>
              <th class="px-4 py-2 text-center">آرشیو</th>
              <th class="px-4 py-2 text-center">فایل‌ها</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach($snapshots as $snap)
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-700">{{ \App\Helpers\Jalali::formatFa($snap->snapshot_date, 'Y/m/d') }}</td>
                <td class="px-4 py-2 text-center text-gray-600">\App\Models\SiteResourceUsage::formatBytes($snap->storage_bytes)</td>
                <td class="px-4 py-2 text-center text-gray-600">{{ \App\Models\SiteResourceUsage::formatBytes($snap->db_bytes) }}</td>
                <td class="px-4 py-2 text-center text-gray-600">{{ \App\Models\SiteResourceUsage::formatBytes($snap->backup_bytes) }}</td>
                <td class="px-4 py-2 text-center text-gray-600">{{ \App\Models\SiteResourceUsage::formatBytes($snap->archive_bytes) }}</td>
                <td class="px-4 py-2 text-center text-gray-600">{{ number_format($snap->file_count) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

</div>
@endsection
