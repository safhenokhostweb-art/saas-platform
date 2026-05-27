@props([
    'users'     => [],   // array of ['id','name','avatar','status']
    'max'       => 4,
    'size'      => 'md', // sm | md | lg
    'showNames' => false,
    'resource'  => null,
    'resourceId'=> null,
])
@php
$sizes   = ['sm' => 'w-6 h-6 text-xs', 'md' => 'w-8 h-8 text-sm', 'lg' => 'w-10 h-10 text-base'];
$dotSz   = ['sm' => 'w-1.5 h-1.5 bottom-0 right-0', 'md' => 'w-2 h-2 bottom-0 right-0', 'lg' => 'w-2.5 h-2.5 bottom-0.5 right-0.5'];
$sz      = $sizes[$size] ?? $sizes['md'];
$dSz     = $dotSz[$size] ?? $dotSz['md'];
$visible = array_slice($users, 0, $max);
$hidden  = max(0, count($users) - $max);
$statusColors = ['online' => 'bg-emerald-500', 'away' => 'bg-amber-500', 'busy' => 'bg-red-500', 'offline' => 'bg-gray-300'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center']) }}
     @if($resource) x-data="realtimeAvatarStack('{{ $resource }}', '{{ $resourceId }}')" @endif>
    <div class="flex -space-x-2 rtl:space-x-reverse">
        @foreach($visible as $user)
        @php
            $initials = collect(explode(' ', $user['name'] ?? 'U'))->map(fn($p) => mb_substr($p,0,1))->take(2)->implode('');
            $statusColor = $statusColors[$user['status'] ?? 'offline'] ?? 'bg-gray-300';
        @endphp
        <div class="relative inline-flex shrink-0 group" title="{{ $user['name'] ?? '' }}">
            @if(!empty($user['avatar']))
            <img src="{{ $user['avatar'] }}"
                 alt="{{ $user['name'] ?? '' }}"
                 class="{{ $sz }} rounded-full ring-2 ring-white object-cover">
            @else
            <span class="{{ $sz }} rounded-full ring-2 ring-white bg-gradient-to-br from-indigo-400 to-purple-500 inline-flex items-center justify-center font-semibold text-white uppercase">
                {{ $initials }}
            </span>
            @endif
            <span class="absolute {{ $dSz }} rounded-full {{ $statusColor }} ring-1 ring-white"></span>
            @if($showNames)
            <span class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-xs text-gray-600 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none bg-white shadow px-1.5 py-0.5 rounded text-center">
                {{ $user['name'] ?? '' }}
            </span>
            @endif
        </div>
        @endforeach

        @if($hidden > 0)
        <div class="relative inline-flex shrink-0" title="{{ $hidden }} نفر دیگر">
            <span class="{{ $sz }} rounded-full ring-2 ring-white bg-slate-100 inline-flex items-center justify-center text-slate-600 font-semibold">
                +{{ $hidden }}
            </span>
        </div>
        @endif
    </div>

    @if(count($users) === 0)
    <span class="text-xs text-gray-400 mr-1">کسی آنلاین نیست</span>
    @endif
</div>

@once
@push('scripts')
<script>
function realtimeAvatarStack(resource, resourceId) {
    return {
        users: @json($users),
        async refresh() {
            try {
                const r = await fetch(`/api/presence/viewers?resource=${resource}&resource_id=${resourceId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (r.ok) this.users = await r.json();
            } catch {}
        },
        init() {
            setInterval(() => this.refresh(), 15000);
        }
    };
}
</script>
@endpush
@endonce
