@extends('layouts.admin')

@section('header', 'ویزارد انتقال احراز هویت')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.security-audit') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-bold text-gray-900">ویزارد انتقال — {{ $server->label }}</h1>
        <p class="text-sm text-gray-500">انتقال از رمز عبور SSH به کلید SSH</p>
    </div>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('error') }}</div>
@endif

<div class="max-w-2xl" x-data="migrationWizard()">

    {{-- Current state --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">وضعیت فعلی</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-xs text-gray-400">مقصد</div>
                <div class="font-medium text-gray-900">{{ $server->label }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400">هاست</div>
                <div class="font-mono text-gray-700">{{ $server->host }}:{{ $server->port }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400">روش احراز هویت فعلی</div>
                <div>
                    @if($server->auth_method === 'key')
                        <span class="text-green-600 font-medium">🔑 کلید SSH — نیازی به انتقال نیست</span>
                    @elseif($server->auth_method === 'password')
                        <span class="text-red-600 font-medium">🔒 رمز عبور — توصیه به انتقال</span>
                    @else
                        {{ $server->auth_method }}
                    @endif
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-400">Fingerprint</div>
                <div class="font-mono text-xs {{ $server->key_fingerprint ? 'text-green-600' : 'text-amber-500' }}">
                    {{ $server->key_fingerprint ? '✓ ثبت‌شده' : 'ثبت نشده' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Steps --}}
    <div class="space-y-4">

        {{-- Step 1: Choose or generate key --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                <span class="w-7 h-7 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-bold shrink-0">۱</span>
                <h2 class="font-semibold text-gray-800">انتخاب یا تولید کلید SSH</h2>
            </div>
            <div class="p-5">

                @if($existingKeys->isNotEmpty())
                <div class="mb-4">
                    <label class="form-label text-xs">کلید موجود</label>
                    <select x-model="selectedKeyId" class="form-control text-sm">
                        <option value="">— تولید کلید جدید —</option>
                        @foreach($existingKeys as $k)
                            <option value="{{ $k->id }}">{{ $k->label }} ({{ $k->keyTypeLabel() }}) — {{ $k->shortFingerprint() }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div x-show="!selectedKeyId">
                    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.generate') }}?target_id={{ $server->id }}"
                       class="btn btn-primary text-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        تولید کلید Ed25519 جدید
                    </a>
                </div>

            </div>
        </div>

        {{-- Step 2: Install --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-show="selectedKeyId" x-cloak>
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                <span class="w-7 h-7 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-bold shrink-0">۲</span>
                <h2 class="font-semibold text-gray-800">نصب کلید عمومی روی سرور</h2>
            </div>
            <div class="p-5">
                <p class="text-sm text-gray-600 mb-4">کلید عمومی به <code class="bg-gray-100 px-1 rounded">~/.ssh/authorized_keys</code> روی سرور اضافه می‌شود. اتصال با اعتبارنامه‌های فعلی (رمز عبور) برقرار می‌شود.</p>
                <button type="button" x-on:click="installKey()" id="mig-install-btn"
                        class="btn btn-primary text-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    نصب کلید عمومی
                </button>
                <div id="mig-install-result" class="hidden mt-3 text-xs rounded-lg p-3"></div>
            </div>
        </div>

        {{-- Step 3: Test --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-show="installDone" x-cloak>
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                <span class="w-7 h-7 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-bold shrink-0">۳</span>
                <h2 class="font-semibold text-gray-800">تست اتصال با کلید</h2>
            </div>
            <div class="p-5">
                <button type="button" x-on:click="testKey()" id="mig-test-btn"
                        class="btn btn-outline-secondary text-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    تست اتصال با کلید
                </button>
                <div id="mig-test-result" class="hidden mt-3 text-xs rounded-lg p-3"></div>
            </div>
        </div>

        {{-- Step 4: Activate --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-show="testPassed" x-cloak>
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                <span class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-sm font-bold shrink-0">۴</span>
                <h2 class="font-semibold text-gray-800">فعال‌سازی کلید و غیرفعال کردن رمز عبور</h2>
            </div>
            <div class="p-5">
                <p class="text-sm text-gray-600 mb-4">با فعال‌سازی، مقصد به جای رمز عبور از کلید SSH استفاده خواهد کرد.</p>
                <form method="POST" x-bind:action="`/admin/infrastructure/customer-deployment/auth-center/ssh-keys/${selectedKeyId}/activate`">
                    @csrf
                    <input type="hidden" name="server_id" value="{{ $server->id }}">
                    <button type="submit" class="btn btn-primary text-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        فعال‌سازی کلید SSH — غیرفعال کردن رمز عبور
                    </button>
                </form>
            </div>
        </div>

    </div>

</div>

@push('scripts')
<script>
function migrationWizard() {
    return {
        selectedKeyId: '',
        installDone:   false,
        testPassed:    false,

        installKey() {
            const btn    = document.getElementById('mig-install-btn');
            const result = document.getElementById('mig-install-result');

            btn.disabled = true;
            btn.textContent = 'در حال نصب...';

            fetch(`/admin/infrastructure/customer-deployment/auth-center/ssh-keys/${this.selectedKeyId}/install`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ server_id: {{ $server->id }} }),
            })
            .then(r => r.json())
            .then(d => {
                btn.disabled = false;
                btn.textContent = 'نصب کلید عمومی';
                result.classList.remove('hidden');
                result.className = d.success ? 'mt-3 text-xs rounded-lg p-3 bg-green-50 border border-green-200 text-green-800' : 'mt-3 text-xs rounded-lg p-3 bg-red-50 border border-red-200 text-red-800';
                result.textContent = (d.success ? '✓ ' : '✗ ') + d.message;
                if (d.success) this.installDone = true;
            })
            .catch(err => { btn.disabled = false; btn.textContent = 'نصب کلید عمومی'; result.classList.remove('hidden'); result.textContent = '✗ خطا: ' + err.message; });
        },

        testKey() {
            const btn    = document.getElementById('mig-test-btn');
            const result = document.getElementById('mig-test-result');

            btn.disabled = true;
            btn.textContent = 'در حال تست...';

            fetch(`/admin/infrastructure/customer-deployment/auth-center/ssh-keys/${this.selectedKeyId}/test`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ server_id: {{ $server->id }} }),
            })
            .then(r => r.json())
            .then(d => {
                btn.disabled = false;
                btn.textContent = 'تست اتصال با کلید';
                result.classList.remove('hidden');
                result.className = d.success ? 'mt-3 text-xs rounded-lg p-3 bg-green-50 border border-green-200 text-green-800' : 'mt-3 text-xs rounded-lg p-3 bg-red-50 border border-red-200 text-red-800';
                result.textContent = (d.success ? '✓ ' : '✗ ') + d.message;
                if (d.success) this.testPassed = true;
            })
            .catch(err => { btn.disabled = false; btn.textContent = 'تست اتصال با کلید'; });
        },
    };
}
</script>
@endpush

@endsection
