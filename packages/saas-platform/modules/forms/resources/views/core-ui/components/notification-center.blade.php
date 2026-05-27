@props([
    'notifications' => [],  // array of {id, type, title, body, read_at, created_at, url?}
    'unreadCount'   => 0,
    'markAllUrl'    => '',
])

<div {{ $attributes }} x-data="{
    open: false,
    unread: {{ (int) $unreadCount }},
    notifications: {{ json_encode(collect($notifications)->map(fn($n) => [
        'id'         => $n['id'] ?? uniqid(),
        'type'       => $n['type'] ?? 'info',
        'title'      => $n['title'] ?? '',
        'body'       => $n['body'] ?? '',
        'read'       => isset($n['read_at']),
        'time'       => isset($n['created_at']) ? $n['created_at'] : '',
        'url'        => $n['url'] ?? null,
    ])->values()) }},
    markRead(id) {
        const n = this.notifications.find(x => x.id === id);
        if (n && !n.read) { n.read = true; this.unread = Math.max(0, this.unread - 1); }
    },
    markAll() {
        this.notifications.forEach(n => { n.read = true; });
        this.unread = 0;
        @if($markAllUrl)
        fetch('{{ $markAllUrl }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' } });
        @endif
    }
}">
    {{-- Bell button --}}
    <button @click="open = !open"
            class="relative p-2 rounded-xl text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-300"
            aria-label="اعلان‌ها"
            :aria-expanded="open">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
        </svg>
        <template x-if="unread > 0">
            <span class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white" x-text="unread > 9 ? '9+' : unread"></span>
        </template>
    </button>

    {{-- Dropdown --}}
    <div x-show="open"
         @click.outside="open = false"
         @keydown.escape.window="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
         class="absolute left-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 overflow-hidden"
         style="display:none"
         role="menu" aria-label="پانل اعلان‌ها">

        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <span class="text-sm font-bold text-gray-800">اعلان‌ها</span>
            <template x-if="unread > 0">
                <button @click="markAll()" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">
                    همه خواندم
                </button>
            </template>
        </div>

        {{-- List --}}
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            <template x-if="notifications.length === 0">
                <div class="flex flex-col items-center py-8 text-gray-400">
                    <svg class="w-8 h-8 mb-2 opacity-40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a3 3 0 11-5.714 0"/>
                    </svg>
                    <p class="text-xs">اعلانی وجود ندارد</p>
                </div>
            </template>
            <template x-for="n in notifications" :key="n.id">
                <div @click="markRead(n.id)"
                     :class="n.read ? 'bg-white' : 'bg-indigo-50/60'"
                     class="flex gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors">
                    <div class="shrink-0 mt-0.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full"
                              :class="{
                                  'bg-blue-100 text-blue-600': n.type === 'info',
                                  'bg-green-100 text-green-600': n.type === 'success',
                                  'bg-amber-100 text-amber-600': n.type === 'warning',
                                  'bg-red-100 text-red-600': n.type === 'error',
                              }">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-800 truncate" x-text="n.title"></p>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2" x-text="n.body"></p>
                        <p class="text-[10px] text-gray-400 mt-1" x-text="n.time"></p>
                    </div>
                    <template x-if="!n.read">
                        <span class="shrink-0 mt-1.5 w-2 h-2 rounded-full bg-indigo-500"></span>
                    </template>
                </div>
            </template>
        </div>

    </div>
</div>
