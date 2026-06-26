<div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="alertFeed()">
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">هشدارهای اخیر DR</h2>
            <span class="text-xs text-gray-400">(بروزرسانی خودکار هر ۶۰ ثانیه)</span>
        </div>
        <a href="{{ route('admin.disaster-recovery.center.alerts') }}" class="text-xs text-blue-600 hover:underline">مشاهده همه</a>
    </div>

    <div x-show="items.length === 0" class="py-10 text-center text-sm text-gray-400">
        هیچ هشداری ثبت نشده
    </div>

    <template x-if="items.length > 0">
        <div class="divide-y divide-gray-100">
            <template x-for="item in items" :key="item.id">
                <div class="px-5 py-3 flex items-start gap-3">
                    <span class="shrink-0 mt-0.5 w-2 h-2 rounded-full"
                          :class="{
                            'bg-red-500': item.severity === 'critical',
                            'bg-amber-500': item.severity === 'high',
                            'bg-blue-500': item.severity === 'medium',
                          }"></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-gray-500" x-text="item.alert_type"></span>
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                  :class="item.resolved ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
                                  x-text="item.resolved ? 'حل‌شده' : 'باز'"></span>
                        </div>
                        <p class="text-sm text-gray-700 mt-0.5 truncate" x-text="item.message"></p>
                    </div>
                    <span class="text-xs text-gray-400 shrink-0" x-text="item.created_at"></span>
                </div>
            </template>
        </div>
    </template>
</div>

@push('scripts')
<script>
function alertFeed() {
    return {
        items: [],

        init() {
            this.fetch();
            setInterval(() => this.fetch(), 60000);
        },

        fetch() {
            fetch('{{ route('admin.disaster-recovery.center.alert-feed') }}')
                .then(r => r.json())
                .then(data => { if (data.table_exists) this.items = data.items; })
                .catch(() => {});
        }
    };
}
</script>
@endpush
