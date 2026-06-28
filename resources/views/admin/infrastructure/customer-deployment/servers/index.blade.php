@extends('layouts.admin')

@section('header', 'مقصدهای استقرار')

@php
$capLabels = [
    'queue'      => 'صف کار',
    'supervisor' => 'Supervisor',
    'redis'      => 'Redis',
    'ssl'        => 'SSL',
    'database'   => 'ساخت DB',
    'storage'    => 'Storage Link',
    'cron'       => 'زمانبند',
    'docker'     => 'Docker',
    'reboot'     => 'ریبوت',
];

$typeColors = [
    'ssh'         => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'dot' => 'bg-indigo-500'],
    'directadmin' => ['bg' => 'bg-blue-50',   'text' => 'text-blue-700',   'border' => 'border-blue-200',   'dot' => 'bg-blue-500'],
    'cpanel'      => ['bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'dot' => 'bg-orange-500'],
    'sftp'        => ['bg' => 'bg-teal-50',   'text' => 'text-teal-700',   'border' => 'border-teal-200',   'dot' => 'bg-teal-500'],
    'docker'      => ['bg' => 'bg-cyan-50',   'text' => 'text-cyan-700',   'border' => 'border-cyan-200',   'dot' => 'bg-cyan-500'],
];
@endphp

@section('content')

{{-- Page header --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">مقصدهای استقرار</h1>
        <p class="text-sm text-gray-500">مدیریت تمام محیط‌های استقرار برای نصب مشتریان — VPS، DirectAdmin، cPanel، SFTP، Docker</p>
    </div>
    <a href="{{ route('admin.infrastructure.customer-deployment.servers.create') }}"
       class="btn btn-primary text-sm flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        افزودن مقصد استقرار
    </a>
</div>

{{-- Alerts --}}
@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('error') }}</div>
@endif

{{-- Driver support banner --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-6">
    @foreach($driverMeta as $type => $meta)
    <div class="rounded-lg border {{ $typeColors[$type]['border'] ?? 'border-gray-200' }} {{ $typeColors[$type]['bg'] ?? 'bg-gray-50' }} px-3 py-2 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full {{ $typeColors[$type]['dot'] ?? 'bg-gray-400' }}"></span>
        <div class="min-w-0">
            <div class="text-xs font-semibold {{ $typeColors[$type]['text'] ?? 'text-gray-700' }} truncate">{{ $meta['label_short'] }}</div>
            <div class="text-xs {{ $meta['implemented'] ? 'text-green-600' : 'text-gray-400' }}">
                {{ $meta['implemented'] ? '● فعال' : '○ به زودی' }}
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Main table --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    @if($servers->isEmpty())
        <div class="text-center py-20 text-gray-400">
            <svg class="w-16 h-16 mx-auto mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
            </svg>
            <p class="text-base font-semibold text-gray-500 mb-1">هیچ مقصد استقراری ثبت نشده</p>
            <p class="text-sm text-gray-400 mb-5">اولین مقصد را اضافه کنید — VPS، هاستینگ، Docker یا SFTP</p>
            <a href="{{ route('admin.infrastructure.customer-deployment.servers.create') }}"
               class="btn btn-primary text-sm">افزودن مقصد استقرار</a>
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">مقصد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">اتصال</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 hidden lg:table-cell">قابلیت‌ها</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">آخرین تست</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">نصب‌ها</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">وضعیت</th>
                    <th class="px-4 py-3 w-28"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($servers as $server)
                @php
                    $meta = $driverMeta[$server->type] ?? $driverMeta['ssh'];
                    $tc   = $typeColors[$server->type] ?? $typeColors['ssh'];
                @endphp
                <tr class="hover:bg-gray-50 transition-colors" id="row-{{ $server->id }}">

                    {{-- Name + type --}}
                    <td class="px-4 py-3">
                        <div class="font-semibold text-gray-900">{{ $server->label }}</div>
                        <span class="inline-flex items-center gap-1 text-xs {{ $tc['bg'] }} {{ $tc['text'] }} {{ $tc['border'] }} border px-2 py-0.5 rounded-full mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full {{ $tc['dot'] }}"></span>
                            {{ $meta['label_short'] }}
                        </span>
                        @if(!$meta['implemented'])
                            <span class="mr-1 text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">stub</span>
                        @endif
                    </td>

                    {{-- Connection --}}
                    <td class="px-4 py-3">
                        <div class="font-mono text-xs text-gray-700">{{ $server->host }}:{{ $server->port }}</div>
                        @if($server->username)
                            <div class="text-xs text-gray-400 font-mono">{{ $server->username }}</div>
                        @endif
                        @if($server->domain)
                            <div class="text-xs text-indigo-500">{{ $server->domain }}</div>
                        @endif
                    </td>

                    {{-- Capabilities (desktop only) --}}
                    <td class="px-4 py-3 hidden lg:table-cell">
                        <div class="flex flex-wrap gap-1">
                            @foreach($meta['capabilities'] as $cap => $supported)
                                @if($supported)
                                <span class="text-xs bg-green-50 text-green-700 border border-green-200 px-1.5 py-0.5 rounded">
                                    {{ $capLabels[$cap] ?? $cap }}
                                </span>
                                @endif
                            @endforeach
                        </div>
                    </td>

                    {{-- Last test --}}
                    <td class="px-4 py-3 text-xs">
                        @if($server->last_tested_at)
                            <div class="text-gray-500">{{ $server->last_tested_at->diffForHumans() }}</div>
                            @if($server->last_test_result === 'success')
                                <span class="text-green-600 font-medium">✓ موفق</span>
                            @else
                                <span class="text-red-500 font-medium">✗ ناموفق</span>
                            @endif
                        @else
                            <span class="text-gray-400">تست نشده</span>
                        @endif
                    </td>

                    {{-- Installations count --}}
                    <td class="px-4 py-3 text-xs text-gray-500 text-center">
                        {{ $server->installations_count }}
                    </td>

                    {{-- Status --}}
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>فعال
                        </span>
                    </td>

                    {{-- Actions --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1 justify-end">
                            {{-- Test connection (only for implemented drivers) --}}
                            @if($meta['implemented'])
                            <button
                                onclick="testTarget({{ $server->id }}, this)"
                                title="تست اتصال"
                                id="test-btn-{{ $server->id }}"
                                class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </button>
                            @else
                            <span title="این نوع مقصد هنوز پیاده‌سازی نشده" class="p-1.5 text-gray-300 cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </span>
                            @endif

                            {{-- Edit --}}
                            <a href="{{ route('admin.infrastructure.customer-deployment.servers.edit', $server) }}"
                               title="ویرایش"
                               class="p-1.5 text-gray-500 hover:bg-gray-100 rounded transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>

                            {{-- Delete --}}
                            <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.servers.destroy', $server) }}"
                                  onsubmit="return confirm('حذف مقصد «{{ $server->label }}»؟ این عمل قابل بازگشت نیست.')">
                                @csrf @method('DELETE')
                                <button type="submit" title="حذف" class="p-1.5 text-red-400 hover:bg-red-50 rounded transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>

                {{-- Test result row --}}
                <tr id="test-result-{{ $server->id }}" class="hidden bg-gray-50">
                    <td colspan="7" class="px-4 py-3">
                        <div id="test-result-content-{{ $server->id }}" class="text-xs"></div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="px-4 py-3 border-t border-gray-100">
            {{ $servers->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
function testTarget(id, btn) {
    const resultRow     = document.getElementById('test-result-' + id);
    const resultContent = document.getElementById('test-result-content-' + id);
    const origIcon      = btn.innerHTML;

    btn.disabled  = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
    resultRow.classList.add('hidden');

    fetch('/admin/infrastructure/customer-deployment/servers/' + id + '/test', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled  = false;
        btn.innerHTML = origIcon;
        resultRow.classList.remove('hidden');

        if (data.success) {
            resultContent.innerHTML =
                '<span class="text-green-700 font-semibold">✓ اتصال موفق</span>' +
                (data.os          ? ' &nbsp;·&nbsp; <span class="text-gray-500">' + data.os + '</span>'                                                   : '') +
                (data.php_version ? ' &nbsp;·&nbsp; PHP <code class="bg-white px-1 rounded border">' + data.php_version + '</code>'                        : '') +
                (data.disk_free   ? ' &nbsp;·&nbsp; دیسک آزاد: <code class="bg-white px-1 rounded border">' + data.disk_free + '</code>'                   : '') +
                (data.fingerprint ? ' &nbsp;·&nbsp; <span class="text-gray-400 font-mono">' + data.fingerprint.substring(0, 20) + '…</span>'               : '');
        } else {
            resultContent.innerHTML = '<span class="text-red-600 font-semibold">✗ اتصال ناموفق</span>: ' + (data.message || 'خطای ناشناخته');
        }
    })
    .catch(err => {
        btn.disabled  = false;
        btn.innerHTML = origIcon;
        resultRow.classList.remove('hidden');
        resultContent.innerHTML = '<span class="text-red-600">✗ خطا: ' + err.message + '</span>';
    });
}
</script>
@endpush

@endsection
