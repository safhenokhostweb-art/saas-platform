@extends('layouts.admin')

@section('header', 'حذف مشتری — پیشرفت')

@push('head')
<style>
.del-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(15,23,42,0.04);padding:1.25rem 1.5rem;margin-bottom:1rem}
.status-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .75rem;border-radius:9999px;font-size:.8rem;font-weight:600}
.status-pending{background:#fef9c3;color:#92400e}
.status-running{background:#dbeafe;color:#1e40af}
.status-complete{background:#d1fae5;color:#065f46}
.status-failed{background:#fee2e2;color:#991b1b}
.prog-bar-wrap{background:#f3f4f6;border-radius:999px;height:8px;overflow:hidden}
.prog-bar{height:100%;border-radius:999px;background:linear-gradient(90deg,#ef4444,#8b5cf6);transition:width .5s ease}
.log-entry{display:flex;gap:.75rem;padding:.5rem 0;border-bottom:1px solid #f8fafc}
.log-entry:last-child{border-bottom:none}
.event-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:.35rem}
.event-completed{background:#10b981}
.event-started{background:#3b82f6}
.event-failed{background:#ef4444}
.event-skipped{background:#94a3b8}
.verify-check{display:flex;align-items:center;gap:.5rem;padding:.4rem 0;font-size:.85rem}
.check-pass{color:#059669}
.check-fail{color:#dc2626}
</style>
@endpush

@section('content')
<div style="max-width:900px;margin:0 auto">

  {{-- Header --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
    <div style="display:flex;align-items:center;gap:1rem">
      <a href="{{ route('admin.infrastructure.customer-deployment.history') }}"
         style="display:flex;align-items:center;gap:.4rem;color:#6b7280;font-size:.9rem;text-decoration:none">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        تاریخچه
      </a>
      <h1 style="font-size:1.4rem;font-weight:700;color:#0f172a;margin:0">
        حذف: {{ $deletion->installation->customer_domain ?? '—' }}
      </h1>
    </div>
    <span class="status-badge status-{{ $deletion->status }}" id="statusBadge">
      {{ $deletion->status }}
    </span>
  </div>

  @if($deletion->isFailed())
    <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.deletions.retry', $deletion) }}" class="mb-4">
      @csrf
      <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
        تلاش مجدد و ادامه از آخرین مرحله
      </button>
    </form>
  @endif

  {{-- Summary --}}
  <div class="del-card">
    <div style="display:flex;gap:2rem;flex-wrap:wrap">
      <div>
        <div style="font-size:.75rem;color:#94a3b8;margin-bottom:.2rem">شرکت</div>
        <div style="font-weight:600">{{ $deletion->installation->customer_company ?? '—' }}</div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#94a3b8;margin-bottom:.2rem">سیاست</div>
        <div style="font-weight:600">{{ $policyLabel }}</div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#94a3b8;margin-bottom:.2rem">آغاز</div>
        <div>{{ $deletion->started_at ? digits_fa($deletion->started_at->format('H:i')) : '—' }}</div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#94a3b8;margin-bottom:.2rem">مرحله جاری</div>
        <div id="currentStage" style="color:#6366f1;font-weight:600">{{ $deletion->current_stage ?? '—' }}</div>
      </div>
    </div>
  </div>

  {{-- Progress Bar --}}
  @php $totalStages = 14; $doneStages = count($deletion->completedStagesList()); @endphp
  <div class="del-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
      <span style="font-size:.85rem;color:#64748b">پیشرفت</span>
      <span style="font-size:.85rem;font-weight:600;color:#0f172a" id="progressText">
        {{ num_fa($doneStages) }} از {{ num_fa($totalStages) }} مرحله
      </span>
    </div>
    <div class="prog-bar-wrap">
      <div class="prog-bar" id="progressBar" style="width:{{ round($doneStages / $totalStages * 100) }}%"></div>
    </div>
  </div>

  {{-- Live Log --}}
  <div class="del-card">
    <h3 style="font-size:.95rem;font-weight:700;color:#0f172a;margin:0 0 1rem 0">
      گزارش زنده
      @if($deletion->isRunning())
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#3b82f6;animation:pulse 1.2s infinite;margin-right:.5rem"></span>
      @endif
    </h3>
    <div id="logContainer" style="max-height:350px;overflow-y:auto">
      @foreach($deletion->logs as $log)
      <div class="log-entry">
        <div class="event-dot event-{{ $log->event }}"></div>
        <div style="flex:1">
          <div style="display:flex;gap:.5rem;align-items:baseline;flex-wrap:wrap">
            <span style="font-size:.8rem;font-weight:600;color:#475569">{{ $log->stage }}</span>
            <span style="font-size:.78rem;color:#94a3b8">{{ $log->created_at->format('H:i:s') }}</span>
          </div>
          <div style="font-size:.85rem;color:#0f172a;margin-top:.15rem">{{ $log->message }}</div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- Verification Report --}}
  @if($deletion->verify_report)
  @php $report = $deletion->verify_report; @endphp
  <div class="del-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
      <h3 style="font-size:.95rem;font-weight:700;color:#0f172a;margin:0">گزارش تأیید نهایی</h3>
      <span style="font-weight:700;font-size:1.1rem;color:{{ $report['verdict'] === 'PASS' ? '#059669' : ($report['verdict'] === 'PARTIAL' ? '#d97706' : '#dc2626') }}">
        {{ $report['verdict'] ?? '—' }}
        ({{ num_fa((int)($report['score'] ?? 0)) }}%)
      </span>
    </div>
    @foreach(($report['checks'] ?? []) as $check)
    <div class="verify-check">
      @if($check['passed'])
        <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="check-pass">{{ $check['check'] }}</span>
      @else
        <svg width="16" height="16" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span class="check-fail">{{ $check['check'] }}</span>
      @endif
      <span style="font-size:.8rem;color:#94a3b8">— {{ $check['detail'] ?? '' }}</span>
    </div>
    @endforeach
  </div>
  @endif

  {{-- Failure Info --}}
  @if($deletion->isFailed() && $deletion->failure_reason)
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1rem">
    <div style="font-weight:700;color:#991b1b;margin-bottom:.3rem">دلیل خطا</div>
    <div style="font-size:.85rem;color:#b91c1c">{{ $deletion->failure_reason }}</div>
  </div>
  @endif

</div>

@if($deletion->isRunning())
<script>
(function() {
  var es       = new EventSource('{{ route('admin.infrastructure.customer-deployment.deletions.stream', $deletion) }}');
  var logCont  = document.getElementById('logContainer');
  var progBar  = document.getElementById('progressBar');
  var progText = document.getElementById('progressText');
  var stageLbl = document.getElementById('currentStage');
  var badge    = document.getElementById('statusBadge');
  var done     = {{ count($deletion->completedStagesList()) }};
  var total    = 14;

  es.onmessage = function(e) {
    try {
      var data = JSON.parse(e.data);

      if (data.event === 'terminal') {
        es.close();
        badge.className = 'status-badge status-' + data.status;
        badge.textContent = data.status;
        if (data.status === 'complete') {
          setTimeout(function() { location.reload(); }, 800);
        }
        return;
      }

      // Append log entry
      var dot = document.createElement('div');
      dot.className = 'log-entry';
      dot.innerHTML = '<div class="event-dot event-' + (data.status || 'started') + '"></div>'
        + '<div style="flex:1">'
        + '<div style="display:flex;gap:.5rem;align-items:baseline">'
        + '<span style="font-size:.8rem;font-weight:600;color:#475569">' + data.stage + '</span>'
        + '</div>'
        + '<div style="font-size:.85rem;color:#0f172a;margin-top:.15rem">' + data.message + '</div>'
        + '</div>';
      logCont.appendChild(dot);
      logCont.scrollTop = logCont.scrollHeight;

      // Update stage label
      if (data.stage) stageLbl.textContent = data.stage;

      // Update progress bar
      if (data.status === 'completed' || data.status === 'skipped') {
        done++;
        var pct = Math.round(done / total * 100);
        progBar.style.width = pct + '%';
        progText.textContent = done + ' از ' + total + ' مرحله';
      }

    } catch(ex) {}
  };

  es.onerror = function() { es.close(); };
})();
</script>
@endif
@endsection
