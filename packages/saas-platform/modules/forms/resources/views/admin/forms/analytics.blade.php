@extends(config('forms.layouts.admin'))

@section('header', 'آمار فرم: ' . $form->title)

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')

{{-- Back link & period selector --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-2">
    <a href="{{ route('admin.forms.builder', $form) }}"
       class="flex items-center gap-1.5 text-slate-500 hover:text-slate-800 text-sm transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
      بازگشت به سازنده
    </a>
    <span class="text-slate-300">|</span>
    <span class="text-sm text-slate-600">آمار <strong>{{ $form->title }}</strong></span>
  </div>
  <div class="flex items-center gap-2">
    @foreach([7, 14, 30, 90] as $d)
    <a href="{{ route('admin.forms.analytics', ['form' => $form, 'days' => $d]) }}"
       class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $days == $d ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
      {{ $d }} روز
    </a>
    @endforeach
  </div>
</div>

{{-- ═══ KPI CARDS ═══ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  {{-- Total submissions --}}
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-3">
      <p class="text-xs font-medium text-slate-500">کل ارسال‌ها</p>
      <div class="w-8 h-8 rounded-xl bg-indigo-50 flex items-center justify-center">
        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75z"/></svg>
      </div>
    </div>
    <p class="text-3xl font-bold text-slate-800">{{ number_format($analytics['total_submissions']) }}</p>
  </div>

  {{-- Recent submissions --}}
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-3">
      <p class="text-xs font-medium text-slate-500">{{ $days }} روز اخیر</p>
      <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg>
      </div>
    </div>
    <p class="text-3xl font-bold text-slate-800">{{ number_format($analytics['recent_submissions']) }}</p>
    <p class="text-xs mt-1 flex items-center gap-1"
       style="color: {{ $analytics['trend'] === 'up' ? '#10b981' : ($analytics['trend'] === 'down' ? '#ef4444' : '#6b7280') }}">
      @if($analytics['trend'] === 'up')
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg> روند صعودی
      @elseif($analytics['trend'] === 'down')
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg> روند نزولی
      @else
        ثابت
      @endif
    </p>
  </div>

  {{-- Avg per day --}}
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-3">
      <p class="text-xs font-medium text-slate-500">میانگین روزانه</p>
      <div class="w-8 h-8 rounded-xl bg-amber-50 flex items-center justify-center">
        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25"/></svg>
      </div>
    </div>
    <p class="text-3xl font-bold text-slate-800">{{ $analytics['avg_per_day'] }}</p>
    <p class="text-xs text-slate-400 mt-1">در روز</p>
  </div>

  {{-- Active fields --}}
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-3">
      <p class="text-xs font-medium text-slate-500">فیلدهای فعال</p>
      <div class="w-8 h-8 rounded-xl bg-purple-50 flex items-center justify-center">
        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>
      </div>
    </div>
    <p class="text-3xl font-bold text-slate-800">{{ $form->fields()->where('is_active', true)->count() }}</p>
  </div>
</div>

{{-- ═══ CHARTS ROW ═══ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
  {{-- Submission trend chart --}}
  <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <h3 class="font-semibold text-slate-700 mb-4">روند ارسال‌ها</h3>
    <div class="h-56">
      <canvas id="trendChart"></canvas>
    </div>
  </div>

  {{-- Device breakdown --}}
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <h3 class="font-semibold text-slate-700 mb-4">دستگاه کاربران</h3>
    <div class="h-48 flex items-center justify-center">
      <canvas id="deviceChart"></canvas>
    </div>
    <div class="mt-4 space-y-2">
      @foreach($analytics['device_breakdown'] as $device => $count)
      @if($count > 0)
      <div class="flex items-center justify-between text-xs">
        <span class="text-slate-600">{{ match($device) { 'desktop' => 'دسکتاپ', 'mobile' => 'موبایل', 'tablet' => 'تبلت', default => 'سایر' } }}</span>
        <span class="font-medium text-slate-800">{{ $count }}</span>
      </div>
      @endif
      @endforeach
    </div>
  </div>
</div>

{{-- ═══ FIELD COMPLETION RATE ═══ --}}
@if(!empty($analytics['field_completion_rate']))
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-6">
  <h3 class="font-semibold text-slate-700 mb-4">نرخ تکمیل فیلدها</h3>
  <div class="space-y-3">
    @foreach($analytics['field_completion_rate'] as $fieldName => $rate)
    <div>
      <div class="flex items-center justify-between text-xs mb-1">
        <span class="text-slate-600 font-medium">{{ $fieldName }}</span>
        <span class="text-slate-500">{{ $rate }}%</span>
      </div>
      <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
        <div class="h-full rounded-full transition-all"
             style="width: {{ $rate }}%; background: {{ $rate >= 80 ? '#10b981' : ($rate >= 50 ? '#f59e0b' : '#ef4444') }}"></div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endif

{{-- Quick actions --}}
<div class="flex flex-wrap gap-3">
  <a href="{{ route('admin.forms.submissions.index', ['form_id' => $form->id]) }}"
     class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white rounded-xl text-sm hover:bg-indigo-700 transition">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75z"/></svg>
    مشاهده ارسال‌ها
  </a>
  <a href="{{ route('admin.forms.submissions.export', ['form_id' => $form->id]) }}"
     class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 rounded-xl text-sm hover:bg-slate-50 transition">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
    دانلود CSV
  </a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Trend chart
  const trendCtx = document.getElementById('trendChart');
  if (trendCtx) {
    const days  = @json(array_keys($analytics['submissions_by_day']));
    const counts = @json(array_values($analytics['submissions_by_day']));
    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: days,
        datasets: [{
          label: 'ارسال',
          data: counts,
          borderColor: '#6366f1',
          backgroundColor: 'rgba(99,102,241,.08)',
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: '#6366f1',
          tension: 0.4,
          fill: true,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 10 } } },
          y: { grid: { color: '#f1f5f9' }, ticks: { stepSize: 1, font: { size: 10 } }, beginAtZero: true },
        },
      },
    });
  }

  // Device chart
  const deviceCtx = document.getElementById('deviceChart');
  if (deviceCtx) {
    const deviceData = @json($analytics['device_breakdown']);
    const labels = { desktop: 'دسکتاپ', mobile: 'موبایل', tablet: 'تبلت', other: 'سایر' };
    const filtered = Object.entries(deviceData).filter(([,v]) => v > 0);
    if (filtered.length) {
      new Chart(deviceCtx, {
        type: 'doughnut',
        data: {
          labels: filtered.map(([k]) => labels[k] || k),
          datasets: [{
            data: filtered.map(([,v]) => v),
            backgroundColor: ['#6366f1','#10b981','#f59e0b','#94a3b8'],
            borderWidth: 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          cutout: '65%',
        },
      });
    }
  }
});
</script>
@endpush
@endsection
