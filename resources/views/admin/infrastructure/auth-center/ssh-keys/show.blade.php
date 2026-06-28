@extends('layouts.admin')

@section('header', 'کلید SSH: ' . $key->label)

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div class="flex-1">
        <h1 class="text-xl font-bold text-gray-900">{{ $key->label }}</h1>
        <p class="text-sm text-gray-500 font-mono">{{ $key->fingerprint }}</p>
    </div>
    @if($key->status === 'active')
        <span class="text-sm bg-green-50 text-green-700 border border-green-200 px-3 py-1 rounded-full">فعال</span>
    @elseif($key->status === 'pending')
        <span class="text-sm bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-full">در انتظار نصب</span>
    @elseif($key->status === 'rotated')
        <span class="text-sm bg-gray-100 text-gray-500 border border-gray-200 px-3 py-1 rounded-full">چرخیده</span>
    @else
        <span class="text-sm bg-red-50 text-red-600 border border-red-200 px-3 py-1 rounded-full">ابطال‌شده</span>
    @endif
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
@endif

<div class="max-w-2xl space-y-4">

    {{-- Key info --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">اطلاعات کلید</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-xs text-gray-400 mb-0.5">نوع</div>
                <div class="font-medium text-gray-800">{{ $key->keyTypeLabel() }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400 mb-0.5">وضعیت</div>
                <div class="font-medium text-gray-800">{{ $key->status }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400 mb-0.5">مقصد متصل</div>
                <div class="font-medium text-gray-800">{{ $key->deploymentTarget?->label ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400 mb-0.5">نصب‌شده</div>
                <div class="font-medium text-gray-800">{{ $key->installed_at?->format('Y-m-d H:i') ?? 'نشده' }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400 mb-0.5">آخرین استفاده</div>
                <div class="font-medium text-gray-800">{{ $key->last_used_at?->diffForHumans() ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400 mb-0.5">تاریخ ایجاد</div>
                <div class="font-medium text-gray-800">{{ $key->created_at->format('Y-m-d') }}</div>
            </div>
        </div>
    </div>

    {{-- Public Key display --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-700">کلید عمومی (Public Key)</h2>
            <button onclick="copyPublicKey()" id="copy-btn"
                    class="text-xs bg-indigo-50 text-indigo-600 border border-indigo-200 px-3 py-1 rounded-lg hover:bg-indigo-100 transition-colors flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                کپی
            </button>
        </div>
        <textarea id="public-key-text" readonly rows="4"
                  class="w-full font-mono text-xs bg-gray-50 border border-gray-200 rounded-lg p-3 text-gray-700 resize-none focus:outline-none">{{ $key->public_key }}</textarea>
        <p class="text-xs text-gray-400 mt-2">این کلید را روی سرور در <code class="bg-gray-100 px-1 rounded">~/.ssh/authorized_keys</code> اضافه کنید.</p>
    </div>

    {{-- SSH Installer --}}
    @if($key->status !== 'revoked')
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5" x-data="{ serverId: '', step: '' }">
        <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            نصب روی سرور
        </h2>

        @if($servers->isEmpty())
            <p class="text-sm text-gray-400">ابتدا مقصد استقرار از نوع SSH اضافه کنید.</p>
        @else
            <div class="space-y-3">
                <div>
                    <label class="form-label text-xs">انتخاب سرور هدف</label>
                    <select x-model="serverId" class="form-control text-sm">
                        <option value="">— انتخاب کنید —</option>
                        @foreach($servers as $server)
                            <option value="{{ $server->id }}">{{ $server->label }} — {{ $server->host }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2" x-show="serverId">
                    {{-- Install --}}
                    <button type="button" x-on:click="installKey(serverId)" id="install-btn"
                            class="btn btn-primary text-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        نصب کلید عمومی
                    </button>

                    {{-- Test --}}
                    <button type="button" x-on:click="testKey(serverId)" id="test-btn"
                            class="btn btn-outline-secondary text-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        تست اتصال
                    </button>

                    {{-- Activate --}}
                    <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.activate', $key) }}">
                        @csrf
                        <input type="hidden" name="server_id" x-bind:value="serverId">
                        <button type="submit" class="btn btn-outline-secondary text-sm flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            فعال کردن
                        </button>
                    </form>
                </div>

                <div id="installer-result" class="hidden text-xs rounded-lg p-3"></div>
            </div>
        @endif
    </div>
    @endif

    {{-- Rotation --}}
    @if($key->status === 'active' && $key->deploymentTarget)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-amber-800">چرخش کلید (Key Rotation)</p>
                <p class="text-xs text-amber-700 mt-0.5">کلید جدید تولید، نصب و فعال می‌شود. کلید قدیمی غیرفعال خواهد شد.</p>
            </div>
            <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.rotate', $key) }}"
                  onsubmit="return confirm('Rotate کلید «{{ $key->label }}»؟ این عمل کلید جدیدی تولید می‌کند.')">
                @csrf
                <button type="submit" class="btn btn-outline-secondary text-sm">Rotate</button>
            </form>
        </div>
    </div>
    @endif

    {{-- Danger zone --}}
    @if($key->status !== 'active')
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-red-800">حذف کلید</p>
                <p class="text-xs text-red-700 mt-0.5">فقط کلیدهای revoked یا rotated قابل حذف هستند.</p>
            </div>
            <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.delete', $key) }}"
                  onsubmit="return confirm('حذف دائمی کلید «{{ $key->label }}»؟')">
                @csrf @method('DELETE')
                <button type="submit" class="btn text-sm bg-red-600 text-white hover:bg-red-700">حذف</button>
            </form>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
function copyPublicKey() {
    const textarea = document.getElementById('public-key-text');
    navigator.clipboard.writeText(textarea.value).then(() => {
        const btn = document.getElementById('copy-btn');
        const orig = btn.textContent;
        btn.textContent = '✓ کپی شد';
        btn.classList.add('bg-green-50', 'text-green-600', 'border-green-200');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('bg-green-50','text-green-600','border-green-200'); }, 2000);
    });
}

function installKey(serverId) {
    ajaxAction('/admin/infrastructure/customer-deployment/auth-center/ssh-keys/{{ $key->id }}/install', { server_id: serverId }, 'install-btn', 'نصب می‌شود...');
}

function testKey(serverId) {
    ajaxAction('/admin/infrastructure/customer-deployment/auth-center/ssh-keys/{{ $key->id }}/test', { server_id: serverId }, 'test-btn', 'تست می‌شود...');
}

function ajaxAction(url, data, btnId, loadingText) {
    const btn    = document.getElementById(btnId);
    const result = document.getElementById('installer-result');
    const orig   = btn.innerHTML;

    btn.disabled  = true;
    btn.textContent = loadingText;
    result.classList.add('hidden');

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled  = false;
        btn.innerHTML = orig;
        result.classList.remove('hidden');
        result.className = d.success
            ? 'text-xs rounded-lg p-3 bg-green-50 border border-green-200 text-green-800'
            : 'text-xs rounded-lg p-3 bg-red-50 border border-red-200 text-red-800';
        result.textContent = (d.success ? '✓ ' : '✗ ') + d.message;
    })
    .catch(err => {
        btn.disabled  = false;
        btn.innerHTML = orig;
        result.classList.remove('hidden');
        result.className = 'text-xs rounded-lg p-3 bg-red-50 border border-red-200 text-red-800';
        result.textContent = '✗ خطا: ' + err.message;
    });
}
</script>
@endpush

@endsection
