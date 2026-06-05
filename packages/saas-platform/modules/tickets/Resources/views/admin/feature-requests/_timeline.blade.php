{{--
    Section L — Feature Request Visual Timeline
    Used inside admin/tickets/show.blade.php when ticket type = feature_request.
    $ticket must have fr_status set.
--}}
@php
    $statuses  = \SaaSPlatform\Modules\Tickets\Models\Ticket::frStatuses();
    $statusKeys= array_keys($statuses);
    $current   = $ticket->fr_status ?? \SaaSPlatform\Modules\Tickets\Models\Ticket::FR_SUBMITTED;
    $currentIdx= array_search($current, $statusKeys);
    if ($currentIdx === false) $currentIdx = 0;
    $isRejected = $current === \SaaSPlatform\Modules\Tickets\Models\Ticket::FR_REJECTED;
@endphp

<div class="p-4 glass-card-strong mb-4">
    <div class="flex items-center gap-2 mb-4">
        <div class="w-8 h-8 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
        </div>
        <div>
            <h3 class="text-sm font-bold text-gray-800">مسیر پیشرفت درخواست</h3>
            @if($ticket->fr_estimated_quarter)
            <p class="text-[10px] text-gray-400 mt-0.5">تخمین تحویل: {{ $ticket->fr_estimated_quarter }}</p>
            @endif
        </div>
        @if($isRejected)
        <span class="mr-auto text-xs font-semibold bg-red-100 text-red-600 px-3 py-1 rounded-full">رد شده</span>
        @endif
    </div>

    {{-- Timeline steps --}}
    <div class="relative">
        <div class="absolute right-3.5 top-0 bottom-0 w-0.5 bg-gray-200 z-0" style="top:1.25rem;bottom:1.25rem"></div>

        <div class="space-y-2 relative z-10">
        @foreach($statuses as $key => $label)
        @if($key === \SaaSPlatform\Modules\Tickets\Models\Ticket::FR_REJECTED) @continue @endif
        @php
            $keyIdx = array_search($key, $statusKeys);
            if ($keyIdx === false) { continue; }
            $isDone    = $keyIdx < $currentIdx;
            $isCurrent = $keyIdx === $currentIdx;
            $isPending = $keyIdx > $currentIdx;
        @endphp
        <div class="flex items-center gap-3">
            {{-- Step dot --}}
            <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs font-bold z-10
                {{ $isDone    ? 'bg-indigo-600 text-white shadow-sm' : '' }}
                {{ $isCurrent ? 'bg-white border-2 border-indigo-500 text-indigo-600 shadow-md' : '' }}
                {{ $isPending ? 'bg-gray-100 text-gray-400 border border-gray-200' : '' }}
            ">
                @if($isDone)
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                @elseif($isCurrent)
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse inline-block"></span>
                @else
                <span class="w-2 h-2 rounded-full bg-gray-300 inline-block"></span>
                @endif
            </div>

            {{-- Step label --}}
            <div class="flex items-center gap-2 flex-1">
                <span class="text-xs font-{{ $isCurrent ? 'bold' : ($isDone ? 'semibold' : 'medium') }}
                    {{ $isDone ? 'text-indigo-700' : ($isCurrent ? 'text-gray-900' : 'text-gray-400') }}">
                    {{ $label }}
                </span>
                @if($isCurrent)
                <span class="text-[9px] font-bold bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full">فعلی</span>
                @endif
            </div>
        </div>
        @endforeach
        </div>
    </div>

    {{-- Admin status change form --}}
    @can('viewAny', \SaaSPlatform\Modules\Tickets\Models\Ticket::class)
    <div class="mt-4 pt-4 border-t border-gray-100"
         x-data="{ open:false, loading:false, sel:'{{ $current }}', quarter:'{{ $ticket->fr_estimated_quarter ?? '' }}', impact:{{ $ticket->fr_impact_score ?? 0 }} }">
        <button @click="open=!open"
                class="w-full flex items-center justify-between text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">
            <span>تغییر وضعیت درخواست</span>
            <svg class="w-4 h-4 transition-transform" :class="open?'rotate-180':''" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
        </button>
        <div x-show="open" x-transition class="mt-3 space-y-2" style="display:none">
            <select x-model="sel" class="form-input w-full text-xs">
                @foreach($statuses as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] text-gray-500 mb-1">تخمین تحویل</label>
                    <input type="text" x-model="quarter" placeholder="مثلاً Q3 2026" class="form-input w-full text-xs">
                </div>
                <div>
                    <label class="block text-[10px] text-gray-500 mb-1">امتیاز تأثیر (0-100)</label>
                    <input type="number" x-model="impact" min="0" max="100" class="form-input w-full text-xs">
                </div>
            </div>
            <button :disabled="loading"
                    @click="
                        loading=true;
                        fetch('{{ route('tickets.admin.fr.status.update', $ticket->id) }}',{
                            method:'PATCH',
                            headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','Content-Type':'application/json'},
                            body:JSON.stringify({fr_status:sel,fr_estimated_quarter:quarter||null,fr_impact_score:impact||0})
                        }).then(r=>r.json()).then(d=>{loading=false;if(d.success){open=false;location.reload()}else{alert(d.error||'خطا')}}).catch(()=>{loading=false})
                    "
                    class="w-full py-2 rounded-xl text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 transition-all">
                <template x-if="!loading">
                    <span>ثبت تغییر</span>
                </template>
                <template x-if="loading">
                    <span>در حال ثبت...</span>
                </template>
            </button>
        </div>
    </div>
    @endcan
</div>
