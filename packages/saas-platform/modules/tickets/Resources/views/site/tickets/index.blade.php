@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', 'تیکت‌های پشتیبانی')

@push('head')
<style>
.ticket-stat-card{background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(255,255,255,0.65));backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.3);border-radius:1rem;padding:1.25rem;transition:all 0.3s ease;box-shadow:0 4px 16px rgba(15,23,42,0.05)}
.ticket-stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(99,102,241,0.1)}
.btn-gradient{display:inline-flex;align-items:center;gap:0.5rem;padding:0.65rem 1.5rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.25s ease;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.25);text-decoration:none}
.btn-gradient:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,0.35)}
.glass-filter-bar{background:rgba(255,255,255,0.75);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.2);border-radius:1rem;padding:1rem 1.5rem;box-shadow:0 8px 24px rgba(15,23,42,0.04)}
.ticket-item{transition:all 0.2s ease}
.ticket-item:hover{background:rgba(99,102,241,0.03)}
.ticket-status-badge{display:inline-flex;align-items:center;gap:0.4rem;padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.7rem;font-weight:600;white-space:nowrap;border:1px solid transparent}
.priority-dot{display:inline-block!important;width:7px!important;height:7px!important;border-radius:50%!important;animation:pulse-dot 2s ease-in-out infinite!important}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.3;transform:scale(1.5)}}
</style>
@endpush

@section('content')
@tenant
<div class="max-w-4xl mx-auto p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">تیکت‌های پشتیبانی</h1>
            <p class="text-sm text-gray-500 mt-0.5">مشاهده و پیگیری درخواست‌های پشتیبانی</p>
        </div>
        <a href="{{ route('tickets.site.create') }}" class="btn-gradient">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            تیکت جدید
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        @foreach([['open','باز','yellow-100','yellow-700'],['in_progress','در جریان','blue-100','blue-700'],['closed','بسته','green-100','green-700']] as [$key,$label,$bg,$text])
        <div class="ticket-stat-card text-center">
            <p class="text-xs text-gray-500 mb-1">{{ $label }}</p>
            <p class="text-2xl font-bold text-gray-800">{{ \App\Helpers\NumberHelper::formatFa($stats[$key] ?? 0) }}</p>
        </div>
        @endforeach
    </div>

    {{-- Filter + List --}}
    <div x-data="{ search: '', status: '' }">

        {{-- Filter Bar --}}
        <div class="glass-filter-bar mb-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <div class="relative flex-1 w-full">
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="search" placeholder="جستجو..."
                           class="w-full pr-9 pl-4 py-2 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
                </div>
                <select x-model="status" class="px-4 py-2 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="open">باز</option>
                    <option value="in_progress">در جریان</option>
                    <option value="closed">بسته</option>
                </select>
            </div>
        </div>

        {{-- Ticket List --}}
        <div class="glass-card-strong overflow-hidden rounded-2xl">
            @if($tickets->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <p class="text-4xl mb-3">🎫</p>
                <p class="text-lg font-medium">هیچ تیکتی وجود ندارد</p>
                <a href="{{ route('tickets.site.create') }}" class="text-indigo-600 text-sm mt-2 inline-block">اولین تیکت خود را ثبت کنید</a>
            </div>
            @else
            <div class="divide-y divide-gray-100">
                @foreach($tickets as $ticket)
                <a href="{{ route('tickets.site.show', $ticket->id) }}"
                   class="flex items-center justify-between px-5 py-4 ticket-item"
                   data-subject="{{ $ticket->subject }}"
                   data-ticket-no="{{ $ticket->ticket_number }}"
                   data-status="{{ $ticket->status }}"
                   x-show="
                        (!search || $el.dataset.subject.toLowerCase().includes(search.toLowerCase()) || $el.dataset.ticketNo.includes(search)) &&
                        (!status || $el.dataset.status === status)
                   ">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-800 truncate">{{ $ticket->subject }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $ticket->ticket_number }} · {{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d') }}</p>
                    </div>
                    <div class="flex items-center gap-2 ml-4">
                        @if($ticket->status === 'open')
                            <span class="ticket-status-badge bg-yellow-50 text-yellow-700 border-yellow-200">
                                <span class="priority-dot bg-yellow-500"></span> باز
                            </span>
                        @elseif($ticket->status === 'in_progress')
                            <span class="ticket-status-badge bg-blue-50 text-blue-700 border-blue-200">
                                <span class="priority-dot bg-blue-500"></span> در جریان
                            </span>
                        @else
                            <span class="ticket-status-badge bg-gray-50 text-gray-500 border-gray-200">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                بسته
                            </span>
                        @endif
                        @php
                            $p = $ticket->priority ?? 'normal';
                            $pStyle = $p === 'high' ? 'bg-red-50 text-red-600 border-red-200' : ($p === 'low' ? 'bg-gray-50 text-gray-400 border-gray-200' : 'bg-blue-50 text-blue-500 border-blue-200');
                            $pLabel = $p === 'high' ? 'بالا' : ($p === 'low' ? 'کم' : 'عادی');
                        @endphp
                        <span class="ticket-status-badge {{ $pStyle }}">{{ $pLabel }}</span>
                        <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </a>
                @endforeach
            </div>
            <div class="p-4 border-t border-gray-100">
                {{ $tickets->withQueryString()->links() }}
            </div>
            @endif
        </div>

    </div>{{-- end Alpine scope --}}

</div>
@endtenant
@endsection
