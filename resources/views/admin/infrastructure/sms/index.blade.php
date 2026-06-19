@extends('layouts.admin')

@section('header', 'آمار ارسال پیامک')

@push('head')
<style>
.sms-stat{position:relative;overflow:hidden;border-radius:1rem;padding:1.25rem 1.5rem;color:#fff;display:block;border:1px solid rgba(255,255,255,0.12)}
.sms-stat--blue{background:linear-gradient(135deg,#2563eb,#1e3a8a)}
.sms-stat--green{background:linear-gradient(135deg,#059669,#064e3b)}
.sms-stat--red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}
.sms-stat--amber{background:linear-gradient(135deg,#d97706,#78350f)}
.sms-stat__label{font-size:.8rem;font-weight:500;opacity:.85;margin-bottom:.4rem}
.sms-stat__value{font-size:2rem;font-weight:800;line-height:1}
.sms-stat__sub{font-size:.75rem;opacity:.7;margin-top:.4rem}
.sms-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,.05);padding:1.5rem}
.provider-row{display:flex;align-items:center;gap:1rem;padding:1rem 1.25rem;border-radius:.875rem;border:1px solid #e2e8f0;background:#f8fafc;margin-bottom:.75rem}
.provider-badge{display:inline-flex;align-items:center;gap:.4rem;padding:.25rem .75rem;border-radius:999px;font-size:.75rem;font-weight:700}
.badge-green{background:#dcfce7;color:#166534}
.badge-red{background:#fee2e2;color:#991b1b}
.badge-gray{background:#f1f5f9;color:#475569}
.prog-bar{height:.6rem;border-radius:999px;background:#e2e8f0;overflow:hidden}
.prog-bar__fill{height:100%;border-radius:999px;transition:width .4s}
.failure-row{display:flex;align-items:flex-start;gap:.75rem;padding:.75rem 1rem;border-radius:.75rem;border:1px solid #fee2e2;background:#fef2f2;margin-bottom:.5rem;font-size:.875rem}
.chart-bar-wrap{display:flex;flex-direction:column;align-items:center;gap:.25rem;flex:1}
.chart-bar-outer{width:100%;background:#e2e8f0;border-radius:.375rem;overflow:hidden;height:80px;display:flex;flex-direction:column;justify-content:flex-end}
.chart-bar-inner{width:100%;background:linear-gradient(180deg,#2563eb,#6366f1);border-radius:.375rem;transition:height .4s}
.chart-bar-label{font-size:.7rem;color:#64748b;font-weight:500;text-align:center}
</style>
@endpush

@section('content')
<div style="padding:1.5rem;font-family:'Vazirmatn',Tahoma,sans-serif;direction:rtl">

  {{-- Breadcrumb --}}
  <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#64748b;margin-bottom:1.5rem">
    <a href="{{ route('admin.infrastructure.overview') }}" style="color:#4f46e5;text-decoration:none">زیرساخت</a>
    <span>›</span>
    <span style="color:#374151;font-weight:600">آمار پیامک</span>
  </div>

  {{-- Header --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem">
    <div style="display:flex;align-items:center;gap:1rem">
      <div style="width:3rem;height:3rem;border-radius:.875rem;background:linear-gradient(135deg,#0ea5e9,#6366f1);display:flex;align-items:center;justify-content:center">
        <svg style="width:1.5rem;height:1.5rem;color:#fff" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
      </div>
      <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#0f172a;margin:0">آمار ارسال پیامک</h1>
        <p style="color:#64748b;font-size:.875rem;margin:0">وضعیت سرویس‌دهنده‌ها و نرخ موفقیت ارسال</p>
      </div>
    </div>
    <div style="font-size:.8rem;color:#94a3b8">آخرین به‌روزرسانی: {{ now()->format('H:i') }}</div>
  </div>

  {{-- Stats Grid --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.25rem;margin-bottom:2rem">

    <div class="sms-stat sms-stat--blue">
      <div class="sms-stat__label">ارسال امروز</div>
      <div class="sms-stat__value">{{ number_format($todaySent) }}</div>
      <div class="sms-stat__sub">{{ $todaySuccess }} موفق &bull; {{ $todayFailed }} ناموفق</div>
    </div>

    <div class="sms-stat {{ $last30Rate >= 90 ? 'sms-stat--green' : ($last30Rate >= 70 ? 'sms-stat--amber' : 'sms-stat--red') }}">
      <div class="sms-stat__label">نرخ موفقیت ۳۰ روز</div>
      <div class="sms-stat__value">{{ $last30Rate }}٪</div>
      <div class="sms-stat__sub">{{ number_format($last30Success) }} از {{ number_format($last30Sent) }}</div>
    </div>

    <div class="sms-stat {{ $primaryRate >= 90 ? 'sms-stat--green' : 'sms-stat--amber' }}">
      <div class="sms-stat__label">موفقیت Primary</div>
      <div class="sms-stat__value">{{ $primaryRate }}٪</div>
      <div class="sms-stat__sub">در ۳۰ روز گذشته</div>
    </div>

    <div class="sms-stat sms-stat--blue">
      <div class="sms-stat__label">موفقیت Failover</div>
      <div class="sms-stat__value">{{ $secondaryRate }}٪</div>
      <div class="sms-stat__sub">در ۳۰ روز گذشته</div>
    </div>

  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem">

    {{-- Provider Health --}}
    <div class="sms-card">
      <h3 style="font-size:1rem;font-weight:700;color:#0f172a;margin:0 0 1.25rem">سلامت سرویس‌دهنده‌ها</h3>

      @foreach([['primary', 'اصلی', $primaryHealth, $primaryRate], ['secondary', 'پشتیبان', $secondaryHealth, $secondaryRate]] as [$slot, $label, $health, $rate])
      <div class="provider-row">
        <div style="flex:1">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <span style="font-weight:600;color:#374151;font-size:.9rem">{{ $label }}</span>
            <span class="provider-badge {{ $rate >= 90 ? 'badge-green' : ($rate >= 70 ? '' : 'badge-red') }}"
                  style="{{ $rate >= 70 && $rate < 90 ? 'background:#fef9c3;color:#92400e' : '' }}">
              {{ $rate >= 90 ? '✓ سالم' : ($rate >= 70 ? '⚠ هشدار' : '✕ مشکل') }}
            </span>
          </div>
          <div class="prog-bar">
            <div class="prog-bar__fill" style="width:{{ $rate }}%;background:{{ $rate >= 90 ? '#22c55e' : ($rate >= 70 ? '#f59e0b' : '#ef4444') }}"></div>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:.4rem;font-size:.75rem;color:#64748b">
            <span>{{ number_format($health['success']) }} موفق</span>
            <span>{{ number_format($health['failure']) }} ناموفق</span>
            @if($health['last_checked_at'])
            <span>آخرین: {{ \App\Helpers\Jalali::diffForHumansFa(\Carbon\Carbon::parse($health['last_checked_at'])) }}</span>
            @endif
          </div>
        </div>
        @if(isset($avgResponseTimes[$slot]))
        <div style="text-align:center;padding:.5rem .75rem;background:#f8fafc;border-radius:.625rem;border:1px solid #e2e8f0;min-width:80px">
          <div style="font-size:1.1rem;font-weight:800;color:#1e40af">{{ $avgResponseTimes[$slot] }}</div>
          <div style="font-size:.7rem;color:#64748b">ms میانگین</div>
        </div>
        @endif
      </div>
      @endforeach
    </div>

    {{-- 7-Day Chart --}}
    <div class="sms-card">
      <h3 style="font-size:1rem;font-weight:700;color:#0f172a;margin:0 0 1.25rem">نمودار ۷ روز اخیر</h3>
      @if($dailyStats->isEmpty())
        <div style="text-align:center;padding:2rem;color:#94a3b8;font-size:.875rem">داده‌ای برای نمایش وجود ندارد</div>
      @else
        @php $maxTotal = $dailyStats->max('total') ?: 1; @endphp
        <div style="display:flex;align-items:flex-end;gap:.5rem;height:130px;padding:0 .5rem">
          @foreach($dailyStats as $day)
          <div class="chart-bar-wrap">
            <div style="font-size:.7rem;color:#64748b;font-weight:600;margin-bottom:.25rem">{{ $day->total }}</div>
            <div class="chart-bar-outer">
              <div class="chart-bar-inner" style="height:{{ round($day->total / $maxTotal * 100) }}%"></div>
            </div>
            <div class="chart-bar-label">{{ \Carbon\Carbon::parse($day->date)->format('m/d') }}</div>
          </div>
          @endforeach
        </div>
      @endif
    </div>

  </div>

  {{-- Recent Failures --}}
  <div class="sms-card">
    <h3 style="font-size:1rem;font-weight:700;color:#0f172a;margin:0 0 1.25rem">
      <span style="color:#dc2626">✕</span> آخرین ارسال‌های ناموفق
    </h3>
    @forelse($recentFailures as $log)
    <div class="failure-row">
      <div style="width:2rem;height:2rem;border-radius:.5rem;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg style="width:1rem;height:1rem;color:#dc2626" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
          <span style="font-weight:600;color:#374151">{{ $log->recipient }}</span>
          <span class="provider-badge badge-gray">{{ $log->provider }}</span>
          <span style="font-size:.75rem;color:#94a3b8">{{ \App\Helpers\Jalali::diffForHumansFa($log->created_at) }}</span>
        </div>
        @if($log->error_message)
        <div style="font-size:.8rem;color:#991b1b;margin-top:.25rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $log->error_message }}</div>
        @endif
      </div>
    </div>
    @empty
    <div style="text-align:center;padding:2rem;color:#94a3b8;font-size:.875rem">
      <svg style="width:2rem;height:2rem;margin:0 auto .5rem;display:block;color:#22c55e" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      هیچ ارسال ناموفقی ثبت نشده
    </div>
    @endforelse
  </div>

</div>
@endsection
