{{--
  Quick Actions Panel — keyboard-accessible action grid.
  Usage:
    <x-core-ui::quick-actions-panel :groups="$actionGroups" />
--}}
@props([
    'groups'     => [],   // array of [{id,label,icon,actions:[{key,label,description,url,shortcut,icon,badge}]}]
    'compact'    => false,
    'columns'    => 2,    // 1|2|3
    'showShortcuts' => true,
])

<div {{ $attributes->merge(['class' => 'quick-actions']) }}
     x-data="quickActionsPanel()"
     @keydown.window="onKey($event)">

    @if(count($groups) === 0)
    <div class="text-center py-8">
        <p class="text-sm text-gray-400">هیچ اقدامی موجود نیست</p>
    </div>
    @else

    @foreach($groups as $group)
    <div class="mb-4">
        @if(!$compact)
        <div class="flex items-center gap-2 mb-2">
            @if(!empty($group['icon']))
            <span class="text-base leading-none">{{ $group['icon'] }}</span>
            @endif
            <span class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ $group['label'] }}</span>
        </div>
        @endif

        <div class="grid gap-2"
             style="grid-template-columns: repeat({{ min($columns, count($group['actions'] ?? [])) }}, minmax(0, 1fr));">

            @foreach($group['actions'] ?? [] as $action)
            <a href="{{ $action['url'] ?? '#' }}"
               @if(!empty($action['method']) && $action['method'] !== 'GET')
               @click.prevent="execute('{{ $action['key'] }}', '{{ $action['url'] ?? '#' }}', '{{ $action['method'] }}', {{ $action['confirm'] ? 'true' : 'false' }})"
               @endif
               class="group flex items-start gap-3 p-3 bg-white border border-gray-200 rounded-xl hover:border-indigo-300 hover:bg-indigo-50/40 hover:shadow-sm transition cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400"
               data-action-key="{{ $action['key'] }}"
               tabindex="0"
               aria-label="{{ $action['label'] }}">

                <span class="w-8 h-8 rounded-lg bg-gray-100 group-hover:bg-indigo-100 flex items-center justify-center shrink-0 transition">
                    @if(!empty($action['icon']))
                    <span class="text-sm">{{ $action['icon'] }}</span>
                    @else
                    <svg class="w-3.5 h-3.5 text-gray-500 group-hover:text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                    </svg>
                    @endif
                </span>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-1">
                        <p class="text-sm font-semibold text-gray-800 group-hover:text-indigo-700 transition truncate">
                            {{ $action['label'] }}
                        </p>
                        @if(!empty($action['badge']))
                        <span class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 shrink-0"
                              :x-text="badges['{{ $action['badge'] }}'] ?? ''">
                        </span>
                        @endif
                        @if($showShortcuts && !empty($action['shortcut']))
                        <kbd class="hidden sm:inline-flex items-center text-xs text-gray-400 border border-gray-200 rounded px-1 py-0.5 font-mono shrink-0">{{ $action['shortcut'] }}</kbd>
                        @endif
                    </div>
                    @if(!$compact && !empty($action['description']))
                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $action['description'] }}</p>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endforeach
    @endif
</div>

@push('scripts')
<script>
function quickActionsPanel() {
    return {
        badges: {},

        async loadBadges() {
            try {
                const r = await fetch('/api/quick-actions/badges', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) this.badges = await r.json();
            } catch {}
        },

        async execute(key, url, method, confirm) {
            if (confirm && !window.confirm('آیا مطمئنید؟')) return;
            if (method === 'GET') { window.location.href = url; return; }
            try {
                const r = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' }
                });
                if (r.ok) window.location.reload();
            } catch {}
        },

        onKey(e) {
            // Focus first action matching shortcut key sequence
        },

        init() { this.loadBadges(); }
    };
}
</script>
@endpush
