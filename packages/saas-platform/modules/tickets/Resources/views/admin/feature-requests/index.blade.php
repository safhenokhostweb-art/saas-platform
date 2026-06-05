@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'درخواست‌های ویژگی')

@push('head')
<style>
/* Section N — Feature Request demand dashboard */
.fr-card{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem 1.25rem;transition:box-shadow .18s}
.fr-card:hover{box-shadow:0 4px 20px rgba(99,102,241,.1)}
.fr-vote-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .8rem;border-radius:9999px;font-size:.72rem;font-weight:700;border:1px solid;cursor:pointer;transition:all .18s}
.fr-vote-btn.voted{background:#e0e7ff;color:#4f46e5;border-color:#a5b4fc}
.fr-vote-btn:not(.voted){background:#f9fafb;color:#6b7280;border-color:#e5e7eb}
.fr-vote-btn:hover:not(.voted){background:#ede9fe;color:#7c3aed;border-color:#c4b5fd}
.demand-bar{height:6px;background:#e5e7eb;border-radius:9999px;overflow:hidden;margin-top:.5rem}
.demand-fill{height:100%;border-radius:9999px;background:linear-gradient(90deg,#6366f1,#a855f7);transition:width .4s}
</style>
@endpush

@section('content')
<x-core-ui::design-tokens />

<div class="admin-page">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">درخواست‌های ویژگی</h1>
            <p class="text-sm text-gray-500 mt-0.5">مدیریت، بررسی و پاسخ‌دهی به درخواست‌های بهبود محصول</p>
        </div>
        <a href="{{ route('tickets.admin.create') }}?type=feature_request"
           class="btn-gradient">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            درخواست جدید
        </a>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 mb-6 border-b border-gray-200 overflow-x-auto">
        @foreach([['top','پرطرفدارترین'],['approved','تأیید شده'],['released','منتشر شده']] as [$t,$l])
        <a href="?tab={{ $t }}"
           class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap
                  {{ $tab===$t ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            {{ $l }}
        </a>
        @endforeach
    </div>

    {{-- Top requested --}}
    @if($tab === 'top')
    <div class="space-y-3" x-data="{}">
        @php $maxVotes = $topRequested->max(fn($t) => $t->votes_count) ?: 1; @endphp
        @forelse($topRequested as $fr)
        @php
            $pct = round($fr->votes_count / $maxVotes * 100);
            $statusLabel = $fr->frStatusLabel();
            $statusMap = [
                'submitted'=>'bg-gray-100 text-gray-600',
                'received'=>'bg-blue-100 text-blue-700',
                'under_review'=>'bg-indigo-100 text-indigo-700',
                'feasibility_analysis'=>'bg-purple-100 text-purple-700',
                'approved'=>'bg-green-100 text-green-700',
                'rejected'=>'bg-red-100 text-red-600',
                'scheduled'=>'bg-sky-100 text-sky-700',
                'in_development'=>'bg-cyan-100 text-cyan-700',
                'testing'=>'bg-yellow-100 text-yellow-700',
                'released'=>'bg-emerald-100 text-emerald-700',
            ];
            $stClass = $statusMap[$fr->fr_status ?? 'submitted'] ?? 'bg-gray-100 text-gray-600';
        @endphp
        <div class="fr-card" x-data="{ voted: false, voteCount: {{ $fr->votes_count }} }">
            <div class="flex items-start gap-3">
                {{-- Vote button --}}
                <div class="flex flex-col items-center gap-0.5 shrink-0 pt-1">
                    <button
                        :class="voted ? 'voted' : ''"
                        class="fr-vote-btn"
                        @click="
                            fetch('{{ route('tickets.admin.fr.vote', $fr->id) }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json'
                                }
                            }).then(r=>r.json()).then(d=>{voted=d.voted;voteCount=d.vote_count})
                        ">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
                        </svg>
                        <span x-text="voteCount"></span>
                    </button>
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <a href="{{ route('tickets.admin.show', $fr->id) }}"
                           class="text-sm font-bold text-gray-800 hover:text-indigo-700 transition-colors">
                            {{ $fr->subject }}
                        </a>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $stClass }}">{{ $statusLabel }}</span>
                        @if($fr->fr_estimated_quarter)
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200">
                            {{ $fr->fr_estimated_quarter }}
                        </span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 mb-2">{{ Str::limit($fr->description, 120) }}</p>

                    {{-- Demand bar --}}
                    <div class="demand-bar">
                        <div class="demand-fill" style="width:{{ $pct }}%"></div>
                    </div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-[10px] text-gray-400">{{ $fr->votes_count }} رأی</span>
                        @if($fr->fr_impact_score > 0)
                        <span class="text-[10px] text-purple-600 font-semibold">امتیاز تأثیر: {{ $fr->fr_impact_score }}%</span>
                        @endif
                    </div>
                </div>

                {{-- Admin status change --}}
                @can('viewAny', \SaaSPlatform\Modules\Tickets\Models\Ticket::class)
                <div class="shrink-0" x-data="{ open:false }">
                    <button @click="open=!open"
                            class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                    </button>
                    <div x-show="open" @click.away="open=false"
                         class="absolute z-30 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl p-2 min-w-[160px]"
                         style="display:none">
                        @foreach(\SaaSPlatform\Modules\Tickets\Models\Ticket::frStatuses() as $key => $label)
                        <button class="block w-full text-right px-3 py-1.5 text-xs rounded-lg hover:bg-indigo-50 hover:text-indigo-700 transition-all {{ ($fr->fr_status??'submitted')===$key ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-gray-700' }}"
                                @click="
                                    fetch('{{ route('tickets.admin.fr.status.update', $fr->id) }}', {
                                        method:'PATCH',
                                        headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','Content-Type':'application/json'},
                                        body: JSON.stringify({fr_status:'{{ $key }}'})
                                    }).then(r=>r.json()).then(d=>{if(d.success)location.reload()});
                                    open=false
                                ">
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>
                </div>
                @endcan
            </div>
        </div>
        @empty
        <div class="text-center py-16 text-gray-400">
            <div class="text-4xl mb-3">💡</div>
            <p>هیچ درخواست ویژگی‌ای ثبت نشده است.</p>
        </div>
        @endforelse
    </div>
    @endif

    {{-- Recently Approved --}}
    @if($tab === 'approved')
    @include('tickets::admin.feature-requests._list', ['items' => $recentApproved, 'emptyMsg' => 'هیچ درخواست تأییدشده‌ای وجود ندارد.'])
    @endif

    {{-- Released --}}
    @if($tab === 'released')
    @include('tickets::admin.feature-requests._list', ['items' => $released, 'emptyMsg' => 'هنوز ویژگی‌ای منتشر نشده است.'])
    @endif

</div>
@endsection
