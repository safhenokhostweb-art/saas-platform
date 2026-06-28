@extends('layouts.admin')

@section('header', 'توکن‌های API')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.index') }}" class="hover:text-indigo-600">مرکز احراز هویت</a>
            <span>/</span>
            <span>توکن‌های API</span>
        </div>
        <h1 class="text-xl font-bold text-gray-900">توکن‌های API</h1>
        <p class="text-sm text-gray-500">مدیریت توکن‌های cPanel و DirectAdmin — هرگز به‌صورت متن ساده نمایش داده نمی‌شوند</p>
    </div>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('error') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    @if($servers->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <p class="text-base font-semibold text-gray-500 mb-1">هیچ مقصد cPanel/DirectAdmin ثبت نشده</p>
            <p class="text-sm text-gray-400">ابتدا مقصد از نوع cPanel یا DirectAdmin اضافه کنید.</p>
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">مقصد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">توکن</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 hidden md:table-cell">ایجاد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 hidden md:table-cell">آخرین استفاده</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">وضعیت</th>
                    <th class="px-4 py-3 w-32"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($servers as $server)
                <tr class="hover:bg-gray-50" id="row-{{ $server->id }}">
                    <td class="px-4 py-3">
                        <div class="font-semibold text-gray-900">{{ $server->label }}</div>
                        <div class="text-xs text-gray-400 font-mono">{{ $server->host }}</div>
                        <div class="text-xs mt-0.5">
                            <span class="bg-{{ $server->type === 'cpanel' ? 'orange' : 'blue' }}-50 text-{{ $server->type === 'cpanel' ? 'orange' : 'blue' }}-700 border border-{{ $server->type === 'cpanel' ? 'orange' : 'blue' }}-200 px-1.5 py-0.5 rounded-full">
                                {{ $server->type === 'cpanel' ? 'cPanel' : 'DirectAdmin' }}
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">
                        @if($server->token_has)
                            <span title="متن ساده هرگز نمایش داده نمی‌شود">{{ $server->token_masked }}</span>
                        @else
                            <span class="text-gray-300 italic">تنظیم نشده</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400 hidden md:table-cell">
                        {{ $server->api_token_created_at?->diffForHumans() ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400 hidden md:table-cell">
                        {{ $server->api_token_last_used_at?->diffForHumans() ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @php $health = $server->token_health; @endphp
                        @if($health === 'ok')
                            <span class="text-xs bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-full">✓ فعال</span>
                        @elseif($health === 'stale')
                            <span class="text-xs bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full">⚠ قدیمی</span>
                        @elseif($health === 'unused')
                            <span class="text-xs bg-gray-100 text-gray-500 border border-gray-200 px-2 py-0.5 rounded-full">استفاده نشده</span>
                        @else
                            <span class="text-xs bg-red-50 text-red-600 border border-red-200 px-2 py-0.5 rounded-full">تنظیم نشده</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1 justify-end" x-data="{ showForm{{ $server->id }}: false }">
                            <button x-on:click="showForm{{ $server->id }} = !showForm{{ $server->id }}"
                                    class="text-xs bg-indigo-50 text-indigo-600 border border-indigo-200 px-2 py-1 rounded hover:bg-indigo-100 transition-colors">
                                {{ $server->token_has ? 'Rotate' : 'تنظیم' }}
                            </button>
                            @if($server->token_has)
                            <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.auth-center.api-tokens.delete', $server) }}"
                                  onsubmit="return confirm('حذف توکن «{{ $server->label }}»؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600 p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            @endif

                            {{-- Inline token form --}}
                            <div x-show="showForm{{ $server->id }}" x-cloak
                                 class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center"
                                 x-on:click.self="showForm{{ $server->id }} = false">
                                <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm mx-4">
                                    <h3 class="font-semibold text-gray-900 mb-4">{{ $server->token_has ? 'Rotate توکن' : 'تنظیم توکن' }} — {{ $server->label }}</h3>
                                    <form id="token-form-{{ $server->id }}">
                                        @csrf
                                        <input type="password" name="api_token" placeholder="توکن جدید" autocomplete="new-password"
                                               class="form-control text-sm mb-3">
                                        <div class="flex gap-2">
                                            <button type="button" onclick="submitToken({{ $server->id }})"
                                                    class="btn btn-primary text-sm flex-1">ذخیره</button>
                                            <button type="button" x-on:click="showForm{{ $server->id }} = false"
                                                    class="btn btn-outline-secondary text-sm">انصراف</button>
                                        </div>
                                    </form>
                                    <div id="token-result-{{ $server->id }}" class="hidden mt-3 text-xs rounded-lg p-2"></div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@push('scripts')
<script>
function submitToken(serverId) {
    const form   = document.getElementById('token-form-' + serverId);
    const result = document.getElementById('token-result-' + serverId);
    const token  = form.querySelector('[name=api_token]').value;

    if (!token) { result.textContent = 'توکن نمی‌تواند خالی باشد.'; result.classList.remove('hidden'); return; }

    fetch('/admin/infrastructure/customer-deployment/auth-center/api-tokens/' + serverId, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ api_token: token }),
    })
    .then(r => r.json())
    .then(d => {
        result.classList.remove('hidden');
        result.className = d.success
            ? 'mt-3 text-xs rounded-lg p-2 bg-green-50 border border-green-200 text-green-800'
            : 'mt-3 text-xs rounded-lg p-2 bg-red-50 border border-red-200 text-red-800';
        result.textContent = d.message;
        if (d.success) setTimeout(() => location.reload(), 1200);
    })
    .catch(err => {
        result.classList.remove('hidden');
        result.textContent = '✗ خطا: ' + err.message;
    });
}
</script>
@endpush

@endsection
