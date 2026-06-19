@extends('layouts.admin')
@section('header', 'Remote Agent Layer')

@push('head')
<style>
.agent-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(15,23,42,.05);padding:1.25rem 1.5rem;margin-bottom:1rem}
.status-dot{display:inline-block;width:.625rem;height:.625rem;border-radius:50%;margin-left:.5rem}
.dot-green{background:#22c55e}.dot-amber{background:#f59e0b}.dot-red{background:#ef4444}.dot-gray{background:#94a3b8}
.cmd-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.375rem .75rem;border-radius:.625rem;font-size:.8rem;font-weight:600;border:1px solid;cursor:pointer;transition:all .15s}
.cmd-btn-blue{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.cmd-btn-blue:hover{background:#dbeafe}
.cmd-btn-amber{background:#fffbeb;color:#92400e;border-color:#fde68a}.cmd-btn-amber:hover{background:#fef3c7}
.cmd-btn-green{background:#f0fdf4;color:#166534;border-color:#bbf7d0}.cmd-btn-green:hover{background:#dcfce7}
.cmd-btn-red{background:#fef2f2;color:#991b1b;border-color:#fecaca}.cmd-btn-red:hover{background:#fee2e2}
.result-box{margin-top:.75rem;padding:.75rem 1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.625rem;font-size:.8rem;font-family:monospace;white-space:pre-wrap;max-height:200px;overflow-y:auto;display:none}
</style>
@endpush

@section('content')
<div style="font-family:'Vazirmatn',Tahoma,sans-serif;direction:rtl;padding:1.5rem">

  {{-- Header --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem">
    <div style="display:flex;align-items:center;gap:1rem">
      <div style="width:3rem;height:3rem;border-radius:.875rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center">
        <svg style="width:1.5rem;height:1.5rem;color:#fff" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      </div>
      <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#0f172a;margin:0">Remote Agent Layer</h1>
        <p style="color:#64748b;font-size:.875rem;margin:0">مدیریت agents روی سرورهای مشتری — HMAC-signed commands</p>
      </div>
    </div>
    <a href="{{ route('admin.infrastructure.remote-agents.create') }}" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Agent جدید
    </a>
  </div>

  @if($agents->isEmpty())
    <div style="text-align:center;padding:4rem;background:#fff;border-radius:1rem;border:1px solid #e2e8f0">
      <svg style="width:3rem;height:3rem;margin:0 auto 1rem;display:block;color:#94a3b8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      <p style="color:#64748b">هنوز هیچ Agent ثبت نشده است.</p>
      <a href="{{ route('admin.infrastructure.remote-agents.create') }}" class="btn btn-primary" style="margin-top:1rem">ثبت اولین Agent</a>
    </div>
  @else
    @foreach($agents as $agent)
    <div class="agent-card" x-data="{ result: null, loading: '' }">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
        <div style="display:flex;align-items:center;gap:.75rem">
          <span class="status-dot dot-{{ $agent->statusBadgeColor() }}"></span>
          <div>
            <div style="font-weight:700;color:#0f172a;font-size:.95rem">{{ $agent->name }}</div>
            <div style="font-size:.8rem;color:#64748b;font-family:monospace;margin-top:.15rem">{{ $agent->base_url }}</div>
          </div>
          @if(!$agent->is_active)
            <span style="padding:.2rem .6rem;border-radius:999px;background:#f1f5f9;color:#94a3b8;font-size:.72rem;font-weight:700">غیرفعال</span>
          @endif
          <span style="padding:.2rem .6rem;border-radius:999px;background:{{ $agent->environment === 'production' ? '#fef2f2' : '#eff6ff' }};color:{{ $agent->environment === 'production' ? '#991b1b' : '#1d4ed8' }};font-size:.72rem;font-weight:700">{{ $agent->environment }}</span>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
          <button class="cmd-btn cmd-btn-blue" @click="loading='health'; fetch('{{ route('admin.infrastructure.remote-agents.health', $agent) }}').then(r=>r.json()).then(d=>{result=JSON.stringify(d,null,2);loading=''}).catch(e=>{result='Error: '+e;loading=''})">
            <svg style="width:.875rem;height:.875rem" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span x-text="loading==='health' ? 'در حال بررسی...' : 'Health Check'"></span>
          </button>
          <button class="cmd-btn cmd-btn-green" @click="if(confirm('Trigger backup?')){loading='backup'; fetch('{{ route('admin.infrastructure.remote-agents.backup', $agent) }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(r=>r.json()).then(d=>{result=JSON.stringify(d,null,2);loading=''}).catch(e=>{result='Error: '+e;loading=''})}">
            <svg style="width:.875rem;height:.875rem" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/></svg>
            <span x-text="loading==='backup' ? 'در حال اجرا...' : 'Backup'"></span>
          </button>
          <button class="cmd-btn cmd-btn-amber" @click="if(confirm('Trigger deploy?')){loading='deploy'; fetch('{{ route('admin.infrastructure.remote-agents.deploy', $agent) }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(r=>r.json()).then(d=>{result=JSON.stringify(d,null,2);loading=''}).catch(e=>{result='Error: '+e;loading=''})}">
            <svg style="width:.875rem;height:.875rem" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            <span x-text="loading==='deploy' ? 'در حال deploy...' : 'Deploy'"></span>
          </button>
          <a href="{{ route('admin.infrastructure.remote-agents.edit', $agent) }}" class="cmd-btn" style="background:#f8fafc;color:#475569;border-color:#e2e8f0">ویرایش</a>
          <form method="POST" action="{{ route('admin.infrastructure.remote-agents.destroy', $agent) }}" onsubmit="return confirm('حذف این Agent؟')">
            @csrf @method('DELETE')
            <button type="submit" class="cmd-btn cmd-btn-red">حذف</button>
          </form>
        </div>
      </div>
      @if($agent->last_health_checked_at)
      <div style="margin-top:.75rem;font-size:.78rem;color:#94a3b8">
        آخرین بررسی: {{ \App\Helpers\Jalali::diffForHumansFa($agent->last_health_checked_at) }} —
        وضعیت: <span style="font-weight:700;color:{{ $agent->isHealthy() ? '#16a34a' : '#dc2626' }}">{{ $agent->last_health_status ?? '—' }}</span>
      </div>
      @endif
      <div class="result-box" :class="result ? 'block' : 'hidden'" :style="result ? 'display:block' : 'display:none'" x-text="result"></div>
    </div>
    @endforeach
  @endif

</div>
@endsection
