@extends('admin.layouts.app')

@section('title', 'تنظیمات حاکمیت منابع')

@section('content')
<div class="space-y-6 max-w-3xl" dir="rtl">

  {{-- Breadcrumb --}}
  <nav class="flex items-center gap-2 text-sm text-gray-500">
    <a href="{{ route('admin.resource-governance.index') }}" class="hover:text-indigo-600">حاکمیت منابع</a>
    <span>/</span>
    <span class="text-gray-900 font-medium">تنظیمات</span>
  </nav>

  <h1 class="text-2xl font-bold text-gray-900">تنظیمات حاکمیت منابع</h1>

  @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('admin.resource-governance.settings.update') }}" class="space-y-6">
    @csrf @method('PUT')

    {{-- Storage & Bandwidth Limits --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
        <h2 class="font-semibold text-gray-800">محدودیت‌های پیش‌فرض</h2>
        <p class="text-xs text-gray-500 mt-0.5">برای سایت‌هایی که محدودیت اختصاصی ندارند اعمال می‌شود.</p>
      </div>
      <div class="p-5 grid grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">سقف فضا (گیگابایت)</label>
          <input type="number" step="0.1" min="0.1" name="storage_limit_gb"
                 value="{{ old('storage_limit_gb', $defaultStorageGb) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <p class="text-xs text-gray-400 mt-1">پیش‌فرض: {{ $defaultStorageGb }} گیگ</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">سقف ترافیک ماهانه (گیگابایت)</label>
          <input type="number" step="0.1" min="0.1" name="bandwidth_limit_gb"
                 value="{{ old('bandwidth_limit_gb', $defaultBandwidthGb) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <p class="text-xs text-gray-400 mt-1">پیش‌فرض: {{ $defaultBandwidthGb }} گیگ</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">نگهداری آرشیو (روز)</label>
          <input type="number" min="1" name="archive_retention_days"
                 value="{{ old('archive_retention_days', config('resource_governance.archive_retention_days')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">نگهداری پشتیبان (روز)</label>
          <input type="number" min="1" name="backup_retention_days"
                 value="{{ old('backup_retention_days', config('resource_governance.backup_retention_days')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
      </div>
    </div>

    {{-- Alert Thresholds --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
        <h2 class="font-semibold text-gray-800">آستانه‌های هشدار</h2>
      </div>
      <div class="p-5 grid grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">هشدار اولیه (درصد)</label>
          <input type="number" min="50" max="99" name="alert_warning_pct"
                 value="{{ old('alert_warning_pct', config('resource_governance.alert_warning_percent')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <p class="text-xs text-gray-400 mt-1">پیش‌فرض: ۸۰٪ — ایمیل و داشبورد</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">هشدار بحرانی (درصد)</label>
          <input type="number" min="51" max="100" name="alert_critical_pct"
                 value="{{ old('alert_critical_pct', config('resource_governance.alert_critical_percent')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <p class="text-xs text-gray-400 mt-1">پیش‌فرض: ۹۵٪ — Slack + داشبورد (اولویت بحرانی)</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">نگهداری اسنپ‌شات (روز)</label>
          <input type="number" min="7" max="365" name="snapshot_retention_days"
                 value="{{ old('snapshot_retention_days', config('resource_governance.snapshot_retention_days')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <p class="text-xs text-gray-400 mt-1">اسنپ‌شات‌های قدیمی‌تر حذف می‌شوند</p>
        </div>
      </div>
    </div>

    {{-- Overage Fees --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
        <h2 class="font-semibold text-gray-800">هزینه‌های اضافه‌مصرف (ریال)</h2>
        <p class="text-xs text-gray-500 mt-0.5">صفر = بدون هزینه اضافه</p>
      </div>
      <div class="p-5 grid grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">اضافه‌مصرف فضا (هر گیگ)</label>
          <input type="number" min="0" name="storage_overage_fee"
                 value="{{ old('storage_overage_fee', config('resource_governance.storage_overage_fee_per_gb')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">اضافه‌مصرف ترافیک (هر گیگ)</label>
          <input type="number" min="0" name="bandwidth_overage_fee"
                 value="{{ old('bandwidth_overage_fee', config('resource_governance.bandwidth_overage_fee_per_gb')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">هزینه نگهداری آرشیو</label>
          <input type="number" min="0" name="archive_retention_fee"
                 value="{{ old('archive_retention_fee', config('resource_governance.archive_retention_fee')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">هزینه بازگردانی سایت</label>
          <input type="number" min="0" name="restore_fee"
                 value="{{ old('restore_fee', config('resource_governance.restore_fee')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
      </div>
    </div>

    <div class="flex justify-end">
      <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
        ذخیره تنظیمات
      </button>
    </div>
  </form>

  {{-- Scheduler Commands Reference --}}
  <div class="bg-white rounded-xl border border-gray-200 p-5">
    <h2 class="font-semibold text-gray-800 text-sm mb-3">دستورات زمان‌بندی شده</h2>
    <div class="space-y-2">
      @foreach([
        ['resource:calculate', '۰۶:۰۰ روزانه', 'محاسبه و ذخیره متریک‌های منابع همه سایت‌ها'],
        ['resource:cleanup', 'یکشنبه ۰۲:۰۰', 'پاک‌سازی اسنپ‌شات‌های قدیمی و رکوردهای پهنای‌باند'],
        ['resource:archive-report', 'دوشنبه ۰۷:۰۰', 'شناسایی آرشیوهای بزرگ و رشد غیرعادی'],
        ['resource:usage-report', '۱ هر ماه ۰۸:۰۰', 'گزارش ماهانه اضافه‌مصرف و هزینه‌ها'],
      ] as [$cmd, $schedule, $desc])
        <div class="flex items-start gap-3 py-2 border-b border-gray-100 last:border-0 text-sm">
          <code class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-xs font-mono">{{ $cmd }}</code>
          <span class="text-gray-500 text-xs">{{ $schedule }}</span>
          <span class="text-gray-600">{{ $desc }}</span>
        </div>
      @endforeach
    </div>
  </div>

</div>
@endsection
