<div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="tenantHealthTable()">
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">سلامت بکاپ تِنِنت‌ها</h2>
        </div>
        <button x-on:click="load()"
                :disabled="loading"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs hover:bg-indigo-700 disabled:opacity-50 transition">
            <svg class="w-3.5 h-3.5" :class="{'animate-spin': loading}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span x-text="loaded ? 'بروزرسانی' : 'بارگذاری'"></span>
        </button>
    </div>

    <div x-show="!loaded && !loading" class="py-10 text-center text-sm text-gray-400">
        برای بارگذاری وضعیت تِنِنت‌ها روی دکمه کلیک کنید (بارگذاری تنبل — ممکن است کمی طول بکشد)
    </div>

    <div x-show="loading" class="py-10 text-center text-sm text-gray-500">
        <svg class="w-6 h-6 mx-auto animate-spin text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        در حال بارگذاری وضعیت تِنِنت‌ها...
    </div>

    <template x-if="loaded && !loading">
        <div class="overflow-x-auto">
            <template x-if="tenants.length === 0">
                <div class="py-12 text-center text-sm text-gray-400">هیچ تِنِنت فعالی یافت نشد</div>
            </template>
            <template x-if="tenants.length > 0">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-right px-4 py-3 font-medium">تِنِنت</th>
                            <th class="text-right px-4 py-3 font-medium">بکاپ DB</th>
                            <th class="text-right px-4 py-3 font-medium">فایل‌های Arvan</th>
                            <th class="text-right px-4 py-3 font-medium">آخرین بازیابی</th>
                            <th class="text-right px-4 py-3 font-medium">امتیاز سلامت</th>
                            <th class="text-right px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="t in tenants" :key="t.site.id">
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800" x-text="t.site.name"></div>
                                    <div class="text-xs text-gray-400" x-text="t.site.domain"></div>
                                </td>
                                <td class="px-4 py-3">
                                    <template x-if="t.last_db_backup">
                                        <div>
                                            <span class="px-2 py-0.5 rounded text-xs font-medium"
                                                  :class="t.db_health === 'OK' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                                  x-text="t.db_health === 'OK' ? 'سالم' : t.db_health"></span>
                                            <div class="text-xs text-gray-400 mt-0.5" x-text="t.last_db_backup_ago"></div>
                                        </div>
                                    </template>
                                    <template x-if="!t.last_db_backup">
                                        <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">N/A</span>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-gray-700" x-text="t.arvan_file_count"></td>
                                <td class="px-4 py-3 text-gray-500 text-xs" x-text="t.last_restore_ago ?? '—'"></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full transition-all"
                                                 :class="t.health_score >= 80 ? 'bg-green-500' : (t.health_score >= 50 ? 'bg-amber-500' : 'bg-red-500')"
                                                 :style="'width:' + t.health_score + '%'"></div>
                                        </div>
                                        <span class="text-xs font-medium w-8 text-right" x-text="t.health_score + '%'"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <a :href="t.detail_url" class="text-blue-500 hover:text-blue-700 text-xs">جزئیات</a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>
        </div>
    </template>
</div>

@push('scripts')
<script>
function tenantHealthTable() {
    return {
        loading: false,
        loaded:  false,
        tenants: [],

        load() {
            this.loading = true;
            fetch('{{ route('admin.disaster-recovery.center.tenant-health-ajax') }}')
                .then(r => r.json())
                .then(data => { this.tenants = data.tenants; this.loaded = true; })
                .catch(() => { this.loaded = true; })
                .finally(() => { this.loading = false; });
        }
    };
}
</script>
@endpush
