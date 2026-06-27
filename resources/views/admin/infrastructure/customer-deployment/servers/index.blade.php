@extends('layouts.admin')

@section('header', 'مدیریت سرورهای راه‌دور')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">سرورهای راه‌دور</h1>
        <p class="text-sm text-gray-500">مدیریت سرورهای SSH برای نصب مشتریان</p>
    </div>
    <a href="{{ route('admin.infrastructure.customer-deployment.servers.create') }}" class="btn btn-primary text-sm">
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        افزودن سرور
    </a>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('error') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    @if($servers->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <svg class="w-14 h-14 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 10h.01M9 16h.01"/>
            </svg>
            <p class="font-medium">هیچ سروری ثبت نشده</p>
            <p class="text-xs mt-1">اولین سرور SSH را اضافه کنید</p>
            <a href="{{ route('admin.infrastructure.customer-deployment.servers.create') }}" class="mt-4 inline-flex items-center btn btn-primary text-sm">افزودن سرور</a>
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">برچسب</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">هاست</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">پورت</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">کاربر</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">احراز هویت</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">آخرین تست</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">نصب‌ها</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">وضعیت</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" x-data="serversTable()">
                @foreach($servers as $server)
                <tr class="hover:bg-gray-50 transition-colors" id="row-{{ $server->id }}">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $server->label }}</div>
                        <div class="text-xs text-gray-400 font-mono">{{ $server->type }}</div>
                    </td>
                    <td class="px-4 py-3 font-mono text-gray-700 text-xs">{{ $server->host }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $server->port }}</td>
                    <td class="px-4 py-3 font-mono text-gray-700 text-xs">{{ $server->username }}</td>
                    <td class="px-4 py-3">
                        @if($server->auth_method === 'key')
                            <span class="inline-flex items-center gap-1 text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                کلید SSH
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                رمز عبور
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        @if($server->last_tested_at)
                            <div>{{ $server->last_tested_at->diffForHumans() }}</div>
                            @if($server->last_test_status === 'ok')
                                <span class="text-green-600">✓ موفق</span>
                            @else
                                <span class="text-red-500">✗ ناموفق</span>
                            @endif
                        @else
                            <span class="text-gray-400">تست نشده</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $server->installations_count }}</td>
                    <td class="px-4 py-3">
                        @if($server->is_active ?? true)
                            <span class="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>فعال
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 justify-end">
                            {{-- Test Connection --}}
                            <button
                                onclick="testServer({{ $server->id }}, this)"
                                title="تست اتصال"
                                class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded transition-colors"
                                id="test-btn-{{ $server->id }}"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </button>
                            {{-- Edit --}}
                            <a href="{{ route('admin.infrastructure.customer-deployment.servers.edit', $server) }}" title="ویرایش" class="p-1.5 text-gray-500 hover:bg-gray-100 rounded transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            {{-- Delete --}}
                            <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.servers.destroy', $server) }}" onsubmit="return confirm('حذف سرور «{{ $server->label }}»؟ این عمل قابل بازگشت نیست.')">
                                @csrf @method('DELETE')
                                <button type="submit" title="حذف" class="p-1.5 text-red-500 hover:bg-red-50 rounded transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                {{-- Test result row (hidden by default) --}}
                <tr id="test-result-{{ $server->id }}" class="hidden">
                    <td colspan="9" class="px-4 py-3 bg-gray-50">
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
function testServer(serverId, btn) {
    const resultRow = document.getElementById('test-result-' + serverId);
    const resultContent = document.getElementById('test-result-content-' + serverId);
    const originalIcon = btn.innerHTML;

    // Show loading
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
    resultRow.classList.add('hidden');

    fetch('/admin/infrastructure/customer-deployment/servers/' + serverId + '/test', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalIcon;
        resultRow.classList.remove('hidden');

        if (data.success) {
            resultContent.innerHTML =
                '<span class="text-green-700 font-semibold">✓ اتصال موفق</span>' +
                (data.php_version ? ' &nbsp;|&nbsp; PHP: <code class="bg-white px-1 rounded">' + data.php_version + '</code>' : '') +
                (data.disk_free   ? ' &nbsp;|&nbsp; دیسک آزاد: <code class="bg-white px-1 rounded">' + data.disk_free + '</code>' : '') +
                (data.fingerprint ? ' &nbsp;|&nbsp; Fingerprint: <code class="bg-white px-1 rounded text-gray-500">' + data.fingerprint.substring(0, 32) + '…</code>' : '');
        } else {
            resultContent.innerHTML = '<span class="text-red-600 font-semibold">✗ اتصال ناموفق</span>: ' + (data.message || 'خطای ناشناخته');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalIcon;
        resultRow.classList.remove('hidden');
        resultContent.innerHTML = '<span class="text-red-600">✗ خطا در ارتباط با سرور: ' + err.message + '</span>';
    });
}
</script>
@endpush

@endsection
