@extends('layouts.admin')

@section('header', 'جزئیات نصب')

@push('head')
<style>
.status-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .75rem;border-radius:9999px;font-size:.8rem;font-weight:600}
.status-pending{background:#fef9c3;color:#92400e}
.status-running{background:#dbeafe;color:#1e40af}
.status-rolling_back{background:#ede9fe;color:#5b21b6}
.status-complete{background:#d1fae5;color:#065f46}
.status-failed{background:#fee2e2;color:#991b1b}
.status-rollback_failed{background:#fce7f3;color:#9d174d}
.status-rolled_back{background:#f3f4f6;color:#374151}
.detail-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(15,23,42,0.04);padding:1.25rem 1.5rem;margin-bottom:1rem}
.timeline-item{display:flex;gap:1rem;padding:.75rem 0;position:relative}
.timeline-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:.35rem;border:2px solid}
.timeline-dot--started{background:#bfdbfe;border-color:#3b82f6}
.timeline-dot--completed{background:#a7f3d0;border-color:#10b981}
.timeline-dot--failed{background:#fecaca;border-color:#ef4444}
.timeline-dot--warning{background:#fed7aa;border-color:#f97316}
.timeline-dot--checkpoint{background:#e9d5ff;border-color:#8b5cf6}
.timeline-dot--rolled_back{background:#e5e7eb;border-color:#6b7280}
.timeline-line{position:absolute;right:4px;top:1.5rem;bottom:0;width:2px;background:#f1f5f9}
.prog-bar-wrap{background:#f3f4f6;border-radius:999px;height:6px;overflow:hidden}
.prog-bar{height:100%;border-radius:999px;background:linear-gradient(90deg,#4f46e5,#7c3aed);transition:width .5s ease}
.stage-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:.35rem}
.stage-dot--done{background:#10b981}
.stage-dot--active{background:#3b82f6}
.stage-dot--pending{background:#d1d5db}
</style>
@endpush

@section('content')

{{-- Breadcrumb --}}
<div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
    <a href="{{ route('admin.infrastructure.customer-deployment.dashboard') }}" class="hover:text-indigo-600">استقرار مشتریان</a>
    <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <a href="{{ route('admin.infrastructure.customer-deployment.history') }}" class="hover:text-indigo-600">تاریخچه</a>
    <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-800 font-medium">{{ $installation->customer_company }}</span>
</div>

@if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">{{ session('error') }}</div>
@endif

{{-- Header Card --}}
<div class="detail-card mb-4">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <h1 class="text-xl font-bold text-gray-900">{{ $installation->customer_company }}</h1>
                @php $statusMap = ['pending'=>['در صف','status-pending'], 'running'=>['در حال اجرا','status-running'], 'complete'=>['موفق','status-complete'], 'failed'=>['ناموفق','status-failed'], 'rolling_back'=>['بازگشت','status-rolling_back'], 'rolled_back'=>['بازگشت‌خورد','status-rolled_back'], 'rollback_failed'=>['خطای بازگشت','status-rollback_failed']]; @endphp
                <span class="status-badge {{ $statusMap[$installation->status][1] ?? 'status-pending' }}">{{ $statusMap[$installation->status][0] ?? $installation->status }}</span>
            </div>
            <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-500">
                <span><strong class="text-gray-700">دامنه:</strong> {{ $installation->customer_domain }}</span>
                <span><strong class="text-gray-700">ایمیل:</strong> {{ $installation->customer_email }}</span>
                <span><strong class="text-gray-700">سرور:</strong> {{ $installation->remoteServer?->label ?? '—' }}</span>
                <span><strong class="text-gray-700">حالت:</strong> <span class="font-mono">{{ str_replace('MODE_', '', $installation->install_mode) }}</span></span>
                <span><strong class="text-gray-700">مرحله:</strong> {{ $installation->current_stage ?? '—' }}</span>
            </div>
        </div>
        <div class="flex gap-2 flex-shrink-0">
            @if($installation->isRunning())
                <a href="{{ route('admin.infrastructure.customer-deployment.installations.progress', $installation) }}" class="btn btn-sm" style="background:#4f46e5;color:#fff">
                    <svg class="w-4 h-4 ml-1 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="4"/></svg>
                    پیشرفت زنده
                </a>
            @endif
            @if($canRollback)
                <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.installations.rollback', $installation) }}" onsubmit="return confirm('آیا مطمئن هستید؟ عملیات بازگشت شروع می‌شود.')">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff">
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        بازگشت (Rollback)
                    </button>
                </form>
            @endif
            @if(!$installation->isRunning() && $installation->isComplete())
                <a href="{{ route('admin.infrastructure.customer-deployment.installations.delete.confirm', $installation) }}"
                   style="display:inline-flex;align-items:center;gap:.4rem;background:#ef4444;color:#fff;border:none;border-radius:.5rem;padding:.45rem 1rem;font-size:.85rem;font-weight:600;text-decoration:none">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    حذف مشتری
                </a>
            @endif
            @if(in_array($installation->status, ['rolled_back', 'failed', 'rollback_failed']))
                <form method="POST"
                      action="{{ route('admin.infrastructure.customer-deployment.installations.force-destroy', $installation) }}"
                      onsubmit="return confirm('⚠️ حذف دستی\n\nاین عملیات تمام رکوردهای دیتابیس این نصب را به‌صورت مستقیم حذف می‌کند (بدون اتصال SSH).\n\nاگر هنوز منابع روی سرور باقی مانده‌اند، باید دستی آن‌ها را پاک کنید.\n\nادامه می‌دهید؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            style="display:inline-flex;align-items:center;gap:.4rem;background:#6b7280;color:#fff;border:none;border-radius:.5rem;padding:.45rem 1rem;font-size:.85rem;font-weight:600;cursor:pointer">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        حذف دستی
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Progress bar for running installations --}}
    @if($installation->isRunning())
    <div class="mt-4 border-t border-gray-100 pt-4" id="liveProgressSection">
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-medium text-gray-600">پیشرفت</span>
            <span class="text-xs text-gray-500" id="stageCount">{{ count($installation->completedStagesList()) }} / {{ count($stageLabels) }} مرحله</span>
        </div>
        <div class="prog-bar-wrap">
            <div class="prog-bar" id="progressBar" style="width: {{ min(100, (count($installation->completedStagesList()) / max(1, count($stageLabels))) * 100) }}%"></div>
        </div>
        <div class="mt-1.5 text-xs text-indigo-600 font-medium" id="currentStageLabel">{{ $installation->current_stage ? ($stageLabels[$installation->current_stage] ?? str_replace('_', ' ', $installation->current_stage)) : 'در انتظار شروع...' }}</div>
    </div>
    @elseif($installation->isComplete())
    <div class="mt-4 border-t border-gray-100 pt-4">
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-medium text-green-700">نصب با موفقیت کامل شد</span>
            @if($installation->completed_at && $installation->started_at)
                <span class="text-xs text-gray-500">مدت: {{ gmdate('H:i:s', $installation->completed_at->diffInSeconds($installation->started_at)) }}</span>
            @endif
        </div>
        <div class="prog-bar-wrap"><div class="prog-bar" style="width:100%;background:linear-gradient(90deg,#059669,#10b981)"></div></div>
    </div>
    @elseif($installation->isFailed() || $installation->status === 'rolled_back')
    <div class="mt-4 border-t border-gray-100 pt-4">
        <div class="text-xs text-red-600 font-medium mb-1">{{ $installation->status === 'rolled_back' ? 'نصب ناموفق — rollback اجرا شد' : 'نصب ناموفق بود' }}</div>
        @if($installation->failure_reason)
            <div class="text-xs text-gray-600 bg-red-50 rounded-lg px-3 py-2 border border-red-100">{{ $installation->failure_reason }}</div>
        @endif
    </div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- Timeline / Logs --}}
    <div class="lg:col-span-2">
        <div class="detail-card">
            <h2 class="font-semibold text-gray-800 mb-4">تایم‌لاین رویدادها</h2>

            @if($installation->logs->isEmpty())
                <p class="text-sm text-gray-400 text-center py-8">هنوز رویدادی ثبت نشده</p>
            @else
                <div class="space-y-0 relative">
                    @foreach($installation->logs as $log)
                    <div class="timeline-item">
                        @if(!$loop->last)
                        <div class="timeline-line"></div>
                        @endif
                        <div class="timeline-dot timeline-dot--{{ $log->event }} flex-shrink-0 mt-1.5 z-10"></div>
                        <div class="flex-1 pb-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="text-xs font-semibold text-gray-700 font-mono">{{ $log->stage }}</span>
                                    <span class="mx-1.5 text-gray-300">·</span>
                                    <span class="text-xs text-gray-500">{{ match($log->event) {
                                        'started' => 'شروع شد',
                                        'completed' => 'تکمیل شد',
                                        'failed' => 'خطا',
                                        'warning' => 'هشدار',
                                        'checkpoint' => 'checkpoint',
                                        'rolled_back' => 'بازگشت',
                                        default => $log->event
                                    } }}</span>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-xs text-gray-400">{{ $log->created_at->format('H:i:s') }}</div>
                                    @if($log->duration_ms)
                                        <div class="text-xs text-gray-400">{{ number_format($log->duration_ms) }}ms</div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-sm text-gray-600 mt-0.5">{{ $log->message }}</div>
                            @if($log->output && count($log->output))
                                <div class="mt-1.5 p-2 bg-gray-50 rounded-lg border border-gray-100">
                                    @foreach($log->output as $k => $v)
                                    <div class="text-xs text-gray-600"><span class="font-mono text-gray-400">{{ $k }}:</span> {{ is_array($v) ? json_encode($v) : $v }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Right Side: Info + Stage Progress + Rollback Checkpoints --}}
    <div class="space-y-4">

        {{-- Installation Info --}}
        <div class="detail-card">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">اطلاعات نصب</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">شناسه:</span><span class="font-mono text-gray-700">#{{ $installation->id }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">حالت:</span><span class="font-mono text-xs text-gray-700">{{ $installation->install_mode }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">تاریخ ایجاد:</span><span class="text-gray-700">{{ $installation->created_at->format('Y/m/d H:i') }}</span></div>
                @if($installation->started_at)
                <div class="flex justify-between"><span class="text-gray-500">شروع اجرا:</span><span class="text-gray-700">{{ $installation->started_at->format('H:i:s') }}</span></div>
                @endif
                @if($installation->completed_at)
                <div class="flex justify-between"><span class="text-gray-500">پایان:</span><span class="text-gray-700">{{ $installation->completed_at->format('H:i:s') }}</span></div>
                @endif
                @if($installation->failed_at)
                <div class="flex justify-between"><span class="text-gray-500">خطا در:</span><span class="text-red-600">{{ $installation->failed_at->format('H:i:s') }}</span></div>
                @endif
                @if($installation->remoteServer)
                <div class="flex justify-between"><span class="text-gray-500">سرور:</span><span class="text-gray-700">{{ $installation->remoteServer->label }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">هاست:</span><span class="font-mono text-xs text-gray-600">{{ $installation->remoteServer->host }}</span></div>
                @endif
                @if($installation->package_version)
                <div class="flex justify-between"><span class="text-gray-500">نسخه:</span><span class="font-mono text-xs text-gray-600">{{ $installation->package_version }}</span></div>
                @endif
            </div>
        </div>

        {{-- Stage Progress --}}
        <div class="detail-card">
            @php
            $allStages = array_keys($stageLabels);
            $completedList = $installation->completedStagesList();
            $currentStage = $installation->current_stage;
            @endphp
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">پیشرفت مراحل ({{ \SaaSPlatform\ThemeRuntime\Support\PersianNumber::digits(count(array_intersect($allStages, $completedList))) }}/{{ \SaaSPlatform\ThemeRuntime\Support\PersianNumber::digits(count($allStages)) }})</h3>
            <div class="space-y-1.5">
                @foreach($allStages as $stage)
                <div class="flex items-center gap-2">
                    @if(in_array($stage, $completedList))
                        <div class="stage-dot stage-dot--done"></div>
                        <span class="text-xs text-gray-500 line-through">{{ $stageLabels[$stage] ?? str_replace('_', ' ', $stage) }}</span>
                    @elseif($stage === $currentStage)
                        <div class="stage-dot stage-dot--active animate-pulse"></div>
                        <span class="text-xs text-indigo-600 font-semibold">{{ $stageLabels[$stage] ?? str_replace('_', ' ', $stage) }}</span>
                    @else
                        <div class="stage-dot stage-dot--pending"></div>
                        <span class="text-xs text-gray-400">{{ $stageLabels[$stage] ?? str_replace('_', ' ', $stage) }}</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        {{-- ADR-088 — template binding of an onboarded site --}}
        @if($templateBinding)
        @php
            $tb = $templateBinding;
            $tbMap = [
                'pending_push' => ['در انتظار پایان نصب و اعمال روی سایت', '#1e40af', '#dbeafe'],
                'bound'        => ['روی سایت اعمال شد', '#065f46', '#d1fae5'],
                'failed'       => ['اعمال نشد', '#991b1b', '#fee2e2'],
            ];
            [$tbLabel, $tbColor, $tbBg] = $tbMap[$tb['status']] ?? [$tb['status'], '#374151', '#f3f4f6'];
        @endphp
        <div class="detail-card" aria-live="polite">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">قالب سایت</h3>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between gap-2"><span class="text-gray-500">قالب:</span><span class="text-gray-800 font-medium">{{ $tb['theme_name'] ?? '—' }}</span></div>
                <div class="flex justify-between gap-2 items-center"><span class="text-gray-500">وضعیت:</span><span class="status-badge" style="background:{{ $tbBg }};color:{{ $tbColor }}">{{ $tbLabel }}</span></div>
                @if(!empty($tb['message']))
                    <p class="text-red-700 leading-6">{{ $tb['message'] }}</p>
                @endif
                @if(($tb['status'] ?? '') === 'failed' && ($tb['attempts'] ?? 0) > 0 && $installation->isComplete())
                    <p class="text-gray-500">{{ \SaaSPlatform\ThemeRuntime\Support\PersianNumber::digits($tb['attempts']) }} تلاش انجام شد؛ تا چند دقیقه پس از نصب خودکار دوباره تلاش می‌شود.</p>
                @endif
            </div>
            @if($installation->isComplete() && ($tb['status'] ?? '') !== 'bound' && !empty($tb['theme_id']))
            <form method="POST" action="{{ route('admin.onboarding.retry-template', $installation) }}" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm w-full">اعمال دوبارهٔ قالب</button>
            </form>
            @endif
        </div>
        @endif

        {{-- Admin Credentials (shown after GENERATE_ENVIRONMENT completes) --}}
        {{-- Phase 13-O9e — Secure Reveal: the password is never in the initial HTML; it's fetched from
             installations.credential (permission + audit logged server-side) only after an explicit click. --}}
        @if($installation->metadata && $hasCredential)
        <div class="detail-card border-amber-200" style="background:#fffbeb">
            <h3 class="font-semibold text-amber-800 mb-3 text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                اطلاعات ورود مشتری
            </h3>
            <div class="space-y-2 text-xs">
                <div>
                    <span class="text-gray-500">ایمیل:</span>
                    <span class="font-mono text-gray-800 select-all">{{ $installation->metadata['admin_email'] ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">نام:</span>
                    <span class="text-gray-800">{{ $installation->metadata['admin_name'] ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500">رمز عبور:</span>
                    <span id="credPwValue" class="font-mono bg-amber-100 px-2 py-0.5 rounded text-amber-900 select-all hidden"></span>
                    <button type="button" id="credRevealBtn" onclick="revealCredential()" class="text-indigo-600 font-medium hover:text-indigo-800" style="background:none;border:none;padding:0;cursor:pointer">
                        نمایش رمز
                    </button>
                </div>
                <p class="text-amber-700 text-xs mt-2">این رمز عبور را ذخیره کنید — پس از اولین ورود تغییر دهید</p>
            </div>
        </div>
        @endif

        {{-- Rollback Checkpoints --}}
        @if($installation->rollbacks->isNotEmpty())
        <div class="detail-card">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">نقاط بازگشت</h3>
            <div class="space-y-2">
                @foreach($installation->rollbacks as $rb)
                <div class="flex items-start justify-between p-2 rounded-lg {{ $rb->executed_at ? 'bg-gray-50' : 'bg-amber-50 border border-amber-100' }}">
                    <div>
                        <div class="text-xs font-mono font-semibold {{ $rb->executed_at ? 'text-gray-500' : 'text-amber-700' }}">{{ $rb->stage }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $rb->created_at->format('H:i:s') }}</div>
                    </div>
                    <span class="text-xs {{ $rb->executed_at ? 'text-gray-400' : 'text-amber-700 font-medium' }}">
                        {{ $rb->executed_at ? 'اجرا شد' : 'در انتظار' }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Live SSE Monitor (only for running installations) --}}
@if($installation->isRunning())
@push('scripts')
<script>
(function() {
    const totalStages = {{ max(1, count($stageLabels)) }};
    const stageLabels = @js($stageLabels);
    const stageText = (name) => stageLabels[name] || name.replace(/_/g, ' ');
    const src = new EventSource('{{ route('admin.infrastructure.customer-deployment.installations.progress', $installation) }}');
    src.addEventListener('progress', function(e) {
        const d = JSON.parse(e.data);
        if (d.stage) document.getElementById('currentStageLabel').textContent = stageText(d.stage) + ' — ' + (d.message || '');
    });
    src.addEventListener('status', function(e) {
        const d = JSON.parse(e.data);
        if (d.completed_stages !== undefined) {
            const pct = Math.min(100, Math.round((d.completed_stages / totalStages) * 100));
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('stageCount').textContent = d.completed_stages + ' / ' + totalStages + ' مرحله';
        }
        if (d.current_stage) {
            document.getElementById('currentStageLabel').textContent = stageText(d.current_stage);
        }
    });
    src.addEventListener('done', function(e) {
        src.close();
        // Reload page after 2s to show final state
        setTimeout(() => window.location.reload(), 2000);
    });
    src.addEventListener('error', function() { src.close(); });
})();
</script>
@endpush
@endif

{{-- Phase 13-O9e — Secure Reveal fetch, mirrors admin.sites.agents.show's revealPassword() pattern --}}
@if($hasCredential)
@push('scripts')
<script>
async function revealCredential() {
    try {
        const res = await fetch('{{ route('admin.infrastructure.customer-deployment.installations.credential', $installation) }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json',
                       'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
        if (!res.ok) { alert('خطا در دریافت رمز عبور'); return; }
        const data = await res.json();
        document.getElementById('credPwValue').textContent = data.password;
        document.getElementById('credPwValue').classList.remove('hidden');
        document.getElementById('credRevealBtn').classList.add('hidden');
    } catch (e) {
        alert('خطا در دریافت رمز عبور');
    }
}
</script>
@endpush
@endif

@endsection
