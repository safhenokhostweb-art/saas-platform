{{--
  core-ui::security-patch-banner
  Shows active critical/high security advisories as dismissible banners.
  Props: $advisories (array from SecurityPatchDashboardService)
--}}
@props(['advisories' => []])

@if(count($advisories) > 0)
<div
    x-data="{ dismissed: false }"
    x-show="!dismissed"
    role="alert"
    aria-live="assertive"
    class="w-full"
>
    @foreach($advisories as $advisory)
    @php
        $color = match($advisory['severity'] ?? 'info') {
            'critical' => 'bg-red-600 text-white',
            'high'     => 'bg-orange-500 text-white',
            'medium'   => 'bg-yellow-400 text-gray-900',
            default    => 'bg-blue-500 text-white',
        };
        $icon = match($advisory['severity'] ?? 'info') {
            'critical', 'high' => '⚠',
            default            => 'ℹ',
        };
    @endphp
    <div class="flex items-start gap-3 px-4 py-3 {{ $color }} text-sm font-medium">
        <span class="text-lg leading-none mt-0.5" aria-hidden="true">{{ $icon }}</span>
        <div class="flex-1 min-w-0">
            <span class="font-semibold">{{ $advisory['title'] }}</span>
            @if(!empty($advisory['module']))
                <span class="opacity-75 mr-1">({{ $advisory['module'] }})</span>
            @endif
            — {{ $advisory['action'] ?? 'به‌روزرسانی توصیه می‌شود.' }}
            @if($advisory['forced'] ?? false)
                <span class="inline-flex items-center mr-2 px-1.5 py-0.5 rounded text-[10px] font-bold bg-white/20 uppercase tracking-wider">اجباری</span>
            @endif
        </div>
        @if(!($advisory['forced'] ?? false))
        <button
            @click="dismissed = true"
            type="button"
            class="shrink-0 opacity-75 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50 rounded p-0.5"
            aria-label="بستن"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        @endif
    </div>
    @endforeach
</div>
@endif
