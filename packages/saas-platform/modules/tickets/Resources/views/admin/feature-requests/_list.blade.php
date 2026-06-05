{{-- Reusable FR list for approved/released tabs --}}
<div class="space-y-3">
    @forelse($items as $fr)
    <div class="fr-card flex items-center gap-4">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <a href="{{ route('tickets.admin.show', $fr->id) }}" class="text-sm font-bold text-gray-800 hover:text-indigo-700 transition-colors">
                    {{ $fr->subject }}
                </a>
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">{{ $fr->frStatusLabel() }}</span>
                @if($fr->fr_estimated_quarter)
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200">{{ $fr->fr_estimated_quarter }}</span>
                @endif
            </div>
            <p class="text-xs text-gray-500">{{ Str::limit($fr->description, 100) }}</p>
        </div>
        <div class="shrink-0 text-center">
            <div class="text-sm font-bold text-indigo-700">{{ $fr->votes_count }}</div>
            <div class="text-[10px] text-gray-400">رأی</div>
        </div>
        <div class="shrink-0 text-xs text-gray-400 whitespace-nowrap">
            {{ \App\Helpers\Jalali::formatFa($fr->fr_status_updated_at ?? $fr->updated_at, 'Y/m/d') }}
        </div>
    </div>
    @empty
    <div class="text-center py-16 text-gray-400">
        <div class="text-4xl mb-3">📋</div>
        <p>{{ $emptyMsg ?? 'موردی یافت نشد.' }}</p>
    </div>
    @endforelse
</div>
