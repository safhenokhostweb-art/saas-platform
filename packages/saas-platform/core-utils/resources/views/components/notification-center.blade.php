{{--
  core-ui::notification-center
  Enterprise notification center panel with realtime updates, read/unread state,
  severity badges, and Alpine.js store integration.

  Props:
    $tenantId (string)
    $userId   (int)
--}}
@props(['tenantId' => '', 'userId' => 0, 'maxVisible' => 20])

<div
    x-data="{
        open: false,
        loading: false,
        notifications: [],
        unreadCount: 0,
        tenantId: '{{ $tenantId }}',
        userId: '{{ $userId }}',

        async init() {
            await this.load();
            // Auto-refresh every 30s when panel not open
            setInterval(() => { if (!this.open) this.load(); }, 30000);
        },

        async load() {
            this.loading = true;
            try {
                const resp = await fetch(`/api/notifications?tenant_id=${this.tenantId}&user_id=${this.userId}&limit={{ $maxVisible }}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' }
                });
                if (resp.ok) {
                    const data = await resp.json();
                    this.notifications = data.notifications ?? [];
                    this.unreadCount   = this.notifications.filter(n => !n.read).length;
                }
            } catch(_) {}
            this.loading = false;
        },

        async markRead(id) {
            this.notifications = this.notifications.map(n => n.id === id ? {...n, read: true} : n);
            this.unreadCount   = this.notifications.filter(n => !n.read).length;
            await fetch(`/api/notifications/${id}/read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' }
            }).catch(() => {});
        },

        async markAllRead() {
            this.notifications = this.notifications.map(n => ({...n, read: true}));
            this.unreadCount   = 0;
            await fetch('/api/notifications/mark-all-read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' }
            }).catch(() => {});
        },

        severityColor(sev) {
            return { critical: 'red', warning: 'yellow', success: 'green', info: 'blue' }[sev] ?? 'gray';
        },

        timeAgo(iso) {
            const ms  = Date.now() - new Date(iso).getTime();
            const min = Math.floor(ms / 60000);
            if (min < 1)  return 'همین الان';
            if (min < 60) return `${min} دقیقه پیش`;
            const hr = Math.floor(min / 60);
            if (hr < 24)  return `${hr} ساعت پیش`;
            return `${Math.floor(hr/24)} روز پیش`;
        }
    }"
    @keydown.escape.window="open = false"
    class="relative"
>
    {{-- Bell button --}}
    <button
        @click="open = !open; if(open) load()"
        type="button"
        class="relative p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
        :aria-expanded="open"
        aria-label="مرکز اعلان‌ها"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span
            x-show="unreadCount > 0"
            x-text="unreadCount > 99 ? '99+' : unreadCount"
            class="absolute -top-1 -right-1 min-w-[1.1rem] h-[1.1rem] flex items-center justify-center px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none"
            aria-live="polite"
        ></span>
    </button>

    {{-- Dropdown panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-end="opacity-0 scale-95 translate-y-1"
        @click.outside="open = false"
        class="absolute left-0 mt-2 w-96 max-h-[28rem] flex flex-col bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden"
        role="dialog"
        aria-label="پنل اعلان‌ها"
        style="display:none"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <h3 class="font-semibold text-gray-800 text-sm">اعلان‌ها</h3>
            <div class="flex items-center gap-2">
                <button
                    x-show="unreadCount > 0"
                    @click="markAllRead()"
                    type="button"
                    class="text-xs text-indigo-600 hover:text-indigo-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded px-1"
                >
                    همه را خوانده شد
                </button>
                <button @click="open = false" type="button" class="text-gray-400 hover:text-gray-600 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 p-0.5" aria-label="بستن">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Loading skeleton --}}
        <div x-show="loading" class="flex flex-col gap-2 p-3">
            <template x-for="i in 3">
                <div class="flex gap-3 animate-pulse">
                    <div class="w-2 h-2 mt-1.5 rounded-full bg-gray-200 shrink-0"></div>
                    <div class="flex-1 space-y-1.5">
                        <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-2.5 bg-gray-100 rounded w-full"></div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Empty state --}}
        <div x-show="!loading && notifications.length === 0" class="flex flex-col items-center justify-center py-10 text-center text-gray-400">
            <svg class="w-10 h-10 mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm">هیچ اعلانی وجود ندارد</p>
        </div>

        {{-- Notification list --}}
        <ul x-show="!loading && notifications.length > 0" class="overflow-y-auto divide-y divide-gray-50" role="list">
            <template x-for="notif in notifications" :key="notif.id">
                <li
                    class="flex gap-3 px-4 py-3 transition-colors hover:bg-gray-50 cursor-pointer"
                    :class="{'bg-indigo-50/60': !notif.read}"
                    @click="markRead(notif.id)"
                    role="listitem"
                    tabindex="0"
                    @keydown.enter="markRead(notif.id)"
                >
                    {{-- Severity dot --}}
                    <div class="mt-1.5 shrink-0">
                        <div
                            class="w-2 h-2 rounded-full"
                            :class="{
                                'bg-red-500': notif.severity === 'critical',
                                'bg-yellow-400': notif.severity === 'warning',
                                'bg-green-500': notif.severity === 'success',
                                'bg-blue-500': notif.severity === 'info',
                                'bg-gray-300': !['critical','warning','success','info'].includes(notif.severity)
                            }"
                        ></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate" x-text="notif.title"></p>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2" x-text="notif.body"></p>
                        <p class="text-[10px] text-gray-400 mt-1" x-text="timeAgo(notif.created_at)"></p>
                    </div>
                    <div x-show="!notif.read" class="mt-1.5 shrink-0">
                        <div class="w-1.5 h-1.5 rounded-full bg-indigo-500"></div>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
