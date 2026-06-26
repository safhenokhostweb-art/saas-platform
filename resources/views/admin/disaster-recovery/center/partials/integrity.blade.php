<div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="drIntegrity()">
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">بررسی یکپارچگی Arvan</h2>
        </div>
        <button x-on:click="runCheck('platform')"
                :disabled="loading"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs hover:bg-blue-700 disabled:opacity-50 transition">
            <svg class="w-3.5 h-3.5" :class="{'animate-spin': loading}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span x-text="loading ? 'در حال بررسی...' : 'بررسی پلتفرم'"></span>
        </button>
    </div>

    {{-- Sync Gap Info --}}
    @if($syncGap > 0)
    <div class="mx-4 mt-4 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
        <strong>{{ $syncGap }} اجرا</strong> در ۷ روز اخیر احتمالاً به Arvan sync نشده‌اند
        (اجراهای DB: {{ $dbRunsLast7d }} — فایل‌های Arvan: {{ $arvanDbLast7d }})
    </div>
    @else
    <div class="mx-4 mt-4 px-3 py-2 bg-green-50 border border-green-200 rounded-lg text-xs text-green-800">
        همه بکاپ‌های ۷ روز اخیر در Arvan موجودند ({{ $dbRunsLast7d }} اجرا / {{ $arvanDbLast7d }} فایل)
    </div>
    @endif

    {{-- Results (appear after check) --}}
    <div class="p-5 space-y-4">
        <template x-if="!result && !error">
            <p class="text-sm text-gray-400 text-center py-6">برای بررسی یکپارچگی فایل‌های Arvan روی دکمه کلیک کنید</p>
        </template>

        <template x-if="error">
            <div class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2" x-text="error"></div>
        </template>

        <template x-if="result">
            <div class="space-y-3">
                {{-- DB integrity --}}
                <div class="border border-gray-200 rounded-lg p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">دیتابیس</span>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold"
                              :class="{
                                'bg-green-100 text-green-700': result.db?.status === 'INTEGRITY_OK',
                                'bg-red-100 text-red-700': ['ARVAN_MISSING','SIZE_MISMATCH','MISSING'].includes(result.db?.status),
                              }"
                              x-text="result.db?.status"></span>
                    </div>
                    <dl class="grid grid-cols-2 gap-1 text-xs">
                        <dt class="text-gray-500">پیام</dt>       <dd class="text-gray-700" x-text="result.db?.message"></dd>
                        <dt class="text-gray-500">فایل‌های Arvan</dt> <dd class="text-gray-700" x-text="result.db?.arvan_count ?? '—'"></dd>
                        <dt class="text-gray-500">آخرین فایل</dt> <dd class="text-gray-700 truncate" x-text="result.db?.latest_file ?? '—'"></dd>
                        <dt class="text-gray-500">اندازه مطابق</dt>
                        <dd class="font-medium"
                            :class="result.db?.size_match === true ? 'text-green-600' : (result.db?.size_match === false ? 'text-red-600' : 'text-gray-400')"
                            x-text="result.db?.size_match === true ? '✓ بله' : (result.db?.size_match === false ? '✗ خیر' : '—')"></dd>
                    </dl>
                </div>

                {{-- Storage integrity --}}
                <div class="border border-gray-200 rounded-lg p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">Storage</span>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold"
                              :class="{
                                'bg-green-100 text-green-700': result.storage?.status === 'INTEGRITY_OK',
                                'bg-red-100 text-red-700': ['ARVAN_MISSING','MISSING'].includes(result.storage?.status),
                              }"
                              x-text="result.storage?.status"></span>
                    </div>
                    <dl class="grid grid-cols-2 gap-1 text-xs">
                        <dt class="text-gray-500">پیام</dt>        <dd class="text-gray-700" x-text="result.storage?.message"></dd>
                        <dt class="text-gray-500">فایل‌های Arvan</dt> <dd class="text-gray-700" x-text="result.storage?.arvan_count ?? '—'"></dd>
                    </dl>
                </div>

                <p class="text-xs text-gray-400 text-left ltr" x-text="'بررسی‌شده در: ' + (result.checked_at ?? '')"></p>
            </div>
        </template>
    </div>
</div>

@push('scripts')
<script>
function drIntegrity() {
    return {
        loading: false,
        result: null,
        error: null,

        runCheck(type) {
            this.loading = true;
            this.error   = null;
            this.result  = null;

            fetch('{{ route('admin.disaster-recovery.center.integrity') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ type }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) { this.error = data.error; } else { this.result = data; }
            })
            .catch(e => { this.error = 'خطا در ارتباط با سرور'; })
            .finally(() => { this.loading = false; });
        }
    };
}
</script>
@endpush
