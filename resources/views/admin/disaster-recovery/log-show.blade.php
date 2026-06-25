@extends('layouts.admin')

@section('title', 'جزئیات بازیابی #' . $log->id)

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">جزئیات بازیابی #{{ $log->id }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $log->typeLabelFa() }}</p>
        </div>
        <a href="{{ route('admin.disaster-recovery.logs') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
            ← بازگشت
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Info Card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <h3 class="font-semibold text-gray-800">اطلاعات بازیابی</h3>
            @php
            $statusColor = match($log->status) {
                'success' => 'bg-green-100 text-green-700',
                'failed'  => 'bg-red-100 text-red-700',
                'running' => 'bg-blue-100 text-blue-700',
                default   => 'bg-gray-100 text-gray-600',
            };
            @endphp
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">وضعیت:</dt>
                    <dd><span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">{{ $log->statusLabelFa() }}</span></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">نوع:</dt>
                    <dd class="font-medium text-gray-800">{{ $log->typeLabelFa() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Dry Run:</dt>
                    <dd>{{ $log->dry_run ? 'بله' : 'خیر' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">فایل بکاپ:</dt>
                    <dd class="font-mono text-xs text-gray-600 break-all">{{ $log->backup_file }}</dd>
                </div>
                @if($log->site)
                <div class="flex justify-between">
                    <dt class="text-gray-500">سایت:</dt>
                    <dd class="font-medium">{{ $log->site->name }} (ID: {{ $log->site_id }})</dd>
                </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-gray-500">اجراکننده:</dt>
                    <dd>{{ $log->executor ? $log->executor->email : '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">شروع:</dt>
                    <dd>{{ $log->started_at ? $log->started_at->format('Y/m/d H:i:s') : '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">پایان:</dt>
                    <dd>{{ $log->completed_at ? $log->completed_at->format('Y/m/d H:i:s') : '—' }}</dd>
                </div>
                @if($log->rollback_snapshot_path)
                <div class="flex justify-between">
                    <dt class="text-gray-500">Snapshot:</dt>
                    <dd class="font-mono text-xs">{{ $log->rollback_snapshot_path }}</dd>
                </div>
                @endif
            </dl>

            @if($log->error_message)
            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                <p class="text-xs font-semibold text-red-800 mb-1">پیام خطا:</p>
                <p class="text-sm text-red-700">{{ $log->error_message }}</p>
            </div>
            @endif
        </div>

        {{-- Validation Result --}}
        @if($log->validation_result)
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <h3 class="font-semibold text-gray-800">نتیجه اعتبارسنجی</h3>
            <div class="space-y-2">
                @foreach($log->validation_result['checks'] ?? [] as $check)
                @php
                $checkColor = match($check['result']) {
                    'pass' => 'border-green-200 bg-green-50',
                    'warn' => 'border-yellow-200 bg-yellow-50',
                    default => 'border-red-200 bg-red-50',
                };
                @endphp
                <div class="flex items-start gap-2 p-2 rounded border {{ $checkColor }} text-sm">
                    @if($check['result'] === 'pass')
                    <svg class="w-4 h-4 text-green-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @elseif($check['result'] === 'warn')
                    <svg class="w-4 h-4 text-yellow-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                    @else
                    <svg class="w-4 h-4 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @endif
                    <div>
                        <p class="font-medium text-gray-800">{{ $check['check'] }}</p>
                        <p class="text-xs text-gray-600">{{ $check['detail'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Execution Log --}}
    @if($log->execution_log && count($log->execution_log) > 0)
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">لاگ اجرا</h3>
        <div class="bg-gray-900 rounded-xl p-4 font-mono text-sm space-y-1 max-h-96 overflow-y-auto">
            @foreach($log->execution_log as $event)
            @php
            $eventColor = match($event['status'] ?? 'info') {
                'error'   => 'text-red-400',
                'success' => 'text-green-400',
                'warn'    => 'text-yellow-400',
                default   => 'text-gray-300',
            };
            @endphp
            <div class="flex items-start gap-2 {{ $eventColor }}">
                <span class="text-gray-500 shrink-0">{{ $event['time'] ?? '' }}</span>
                <span>{{ $event['message'] ?? '' }}</span>
                @if(isset($event['progress']))
                <span class="text-gray-600 mr-auto">[{{ $event['progress'] }}%]</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
