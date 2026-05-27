@props([
    'userId'   => null,
    'tenantId' => null,
    'status'   => 'online',  // online | offline | away | busy
    'label'    => null,
    'size'     => 'sm',      // xs | sm | md | lg
    'pollingInterval' => 30, // seconds
])
@php
$sizeMap  = ['xs' => 'w-1.5 h-1.5', 'sm' => 'w-2.5 h-2.5', 'md' => 'w-3.5 h-3.5', 'lg' => 'w-4 h-4'];
$colorMap = [
    'online'  => 'bg-emerald-500 shadow-emerald-200',
    'offline' => 'bg-gray-300',
    'away'    => 'bg-amber-400 shadow-amber-200',
    'busy'    => 'bg-red-500 shadow-red-200',
];
$sz = $sizeMap[$size] ?? $sizeMap['sm'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}
      @if($userId) x-data="livePresenceDot('{{ $userId }}', '{{ $tenantId }}')" @else x-data="{ status: '{{ $status }}' }" @endif>

    <span class="relative inline-flex {{ $sz }}">
        <span class="absolute inset-0 rounded-full animate-ping opacity-60 duration-1000"
              :class="{
                  'bg-emerald-400': status === 'online',
                  'bg-amber-400':   status === 'away',
                  'bg-red-400':     status === 'busy',
                  'hidden':         status === 'offline',
              }"></span>
        <span class="relative rounded-full {{ $sz }} shadow"
              :class="{
                  'bg-emerald-500': status === 'online',
                  'bg-gray-300':    status === 'offline',
                  'bg-amber-400':   status === 'away',
                  'bg-red-500':     status === 'busy',
              }"></span>
    </span>

    @if($label)
    <span class="text-xs text-gray-500"
          x-text="{online: '{{ $label }}', offline: 'آفلاین', away: 'دور', busy: 'مشغول'}[status] ?? '{{ $label }}'"></span>
    @endif
</span>

@once
@push('scripts')
<script>
function livePresenceDot(userId, tenantId) {
    return {
        status: '{{ $status }}',
        async check() {
            try {
                const r = await fetch(`/api/presence/status?user_id=${userId}&tenant_id=${tenantId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (r.ok) {
                    const d = await r.json();
                    this.status = d.status ?? 'offline';
                }
            } catch {}
        },
        init() {
            setInterval(() => this.check(), {{ $pollingInterval }} * 1000);
        }
    };
}
</script>
@endpush
@endonce
