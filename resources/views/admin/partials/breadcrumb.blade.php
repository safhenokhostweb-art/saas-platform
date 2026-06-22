@php $crumbs = $crumbs ?? []; @endphp
<nav class="flex items-center gap-1.5 text-sm text-gray-500 mb-5 font-medium" aria-label="breadcrumb">
    @foreach($crumbs as $i => $crumb)
        @if(!$loop->last)
            @if(!empty($crumb['url']))
                <a href="{{ $crumb['url'] }}" class="hover:text-indigo-600 transition-colors">{{ $crumb['label'] }}</a>
            @else
                <span>{{ $crumb['label'] }}</span>
            @endif
            <svg class="w-3.5 h-3.5 text-gray-300 rotate-180 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        @else
            <span class="text-gray-800">{{ $crumb['label'] }}</span>
        @endif
    @endforeach
</nav>
