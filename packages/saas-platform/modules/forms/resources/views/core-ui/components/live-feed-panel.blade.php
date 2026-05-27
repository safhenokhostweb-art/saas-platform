@props([
    'tenantId'    => '',
    'maxItems'    => 20,
    'pollInterval'=> 15,  // seconds
    'compact'     => false,
    'title'       => 'فعالیت‌های زنده',
])
@php
$iconMap = [
    'ticket.created'   => ['icon' => 'M16.5 6v.75a3.75 3.75 0 0 1-7.5 0V6m-4.5 0h16.5m-16.5 0a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 21h15a2.25 2.25 0 0 0 2.25-2.25V8.25A2.25 2.25 0 0 0 19.5 6', 'color' => 'text-indigo-500 bg-indigo-50'],
    'ticket.replied'   => ['icon' => 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z', 'color' => 'text-emerald-500 bg-emerald-50'],
    'form.submitted'   => ['icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z', 'color' => 'text-violet-500 bg-violet-50'],
    'ticket.assigned'  => ['icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z', 'color' => 'text-blue-500 bg-blue-50'],
    'default'          => ['icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z', 'color' => 'text-gray-400 bg-gray-50'],
];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col']) }}
     x-data="liveFeedPanel('{{ $tenantId }}', {{ $pollInterval }})"
     x-init="init()">

    @if(!$compact)
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <span class="relative flex w-2 h-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full w-2 h-2 bg-emerald-500"></span>
            </span>
            {{ $title }}
        </h3>
        <span class="text-xs text-gray-400" x-text="events.length > 0 ? events.length + ' رویداد' : 'بدون رویداد'"></span>
    </div>
    @endif

    <div class="space-y-1.5 overflow-y-auto" style="max-height: {{ $compact ? '200px' : '400px' }}">
        <template x-if="loading">
            <div class="space-y-2">
                @for($i = 0; $i < 3; $i++)
                <div class="flex items-center gap-2 p-2 rounded-lg animate-pulse">
                    <div class="w-7 h-7 bg-gray-100 rounded-lg shrink-0"></div>
                    <div class="flex-1 space-y-1">
                        <div class="h-3 bg-gray-100 rounded w-3/4"></div>
                        <div class="h-2 bg-gray-50 rounded w-1/2"></div>
                    </div>
                </div>
                @endfor
            </div>
        </template>

        <template x-if="!loading && events.length === 0">
            <div class="text-center py-6 text-gray-400 text-xs">
                <svg class="w-8 h-8 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                </svg>
                فعالیتی ثبت نشده
            </div>
        </template>

        <template x-for="event in events" :key="event.id">
            <div class="flex items-start gap-2 p-2 rounded-lg hover:bg-gray-50 transition group">
                <div class="w-7 h-7 rounded-lg shrink-0 flex items-center justify-center"
                     :class="getIconMeta(event.type).color">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="getIconMeta(event.type).icon"></path>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-700 truncate" x-text="event.payload?.message ?? event.type"></p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="timeAgo(event.occurred_at)"></p>
                </div>
                <template x-if="event.payload?.severity === 'critical' || event.payload?.severity === 'error'">
                    <span class="text-red-500 text-xs font-bold shrink-0">!</span>
                </template>
            </div>
        </template>
    </div>
</div>

@once
@push('scripts')
<script>
const _liveFeedIconMap = {
    'ticket.created':  { icon: 'M16.5 6v.75a3.75 3.75 0 0 1-7.5 0V6m-4.5 0h16.5m-16.5 0a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 21h15a2.25 2.25 0 0 0 2.25-2.25V8.25A2.25 2.25 0 0 0 19.5 6', color: 'text-indigo-500 bg-indigo-50' },
    'ticket.replied':  { icon: 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z', color: 'text-emerald-500 bg-emerald-50' },
    'form.submitted':  { icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z', color: 'text-violet-500 bg-violet-50' },
};
function liveFeedPanel(tenantId, pollInterval) {
    return {
        events: [],
        loading: true,
        lastId: null,
        getIconMeta(type) {
            return _liveFeedIconMap[type] ?? { icon: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z', color: 'text-gray-400 bg-gray-50' };
        },
        timeAgo(iso) {
            const d = new Date(iso), now = new Date(), s = Math.round((now - d) / 1000);
            if (s < 60) return 'لحظاتی پیش';
            if (s < 3600) return Math.floor(s / 60) + ' دقیقه پیش';
            if (s < 86400) return Math.floor(s / 3600) + ' ساعت پیش';
            return Math.floor(s / 86400) + ' روز پیش';
        },
        async fetchFeed() {
            try {
                const url = `/api/realtime/feed?tenant_id=${tenantId}` + (this.lastId ? `&since=${this.lastId}` : '');
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) {
                    const data = await r.json();
                    if (data.events?.length) {
                        this.events = [...data.events, ...this.events].slice(0, {{ $maxItems }});
                        this.lastId = data.events[0]?.id ?? this.lastId;
                    }
                }
            } catch {}
            this.loading = false;
        },
        init() {
            this.fetchFeed();
            setInterval(() => this.fetchFeed(), pollInterval * 1000);
        }
    };
}
</script>
@endpush
@endonce
