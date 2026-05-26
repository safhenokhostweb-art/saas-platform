@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'مدیریت تیکت‌ها')

@push('head')
<style>
.ticket-stat-card{background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(255,255,255,0.65));backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.3);border-radius:1rem;padding:1.25rem;transition:all 0.3s ease;box-shadow:0 4px 16px rgba(15,23,42,0.05)}
.ticket-stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(99,102,241,0.1)}
.ticket-stat-card__icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem}
.btn-gradient{display:inline-flex;align-items:center;gap:0.5rem;padding:0.65rem 1.5rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.25s ease;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.25);text-decoration:none}
.btn-gradient:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,0.35)}
.glass-filter-bar{background:rgba(255,255,255,0.75);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.2);border-radius:1rem;padding:1rem 1.5rem;box-shadow:0 8px 24px rgba(15,23,42,0.04)}
.ticket-row{transition:all 0.2s ease}
.ticket-row:hover{background:rgba(99,102,241,0.03)}
.ticket-status-badge{display:inline-flex;align-items:center;gap:0.4rem;padding:0.3rem 0.85rem;border-radius:9999px;font-size:0.75rem;font-weight:600;white-space:nowrap;border:1px solid transparent}
.priority-dot{display:inline-block!important;width:8px!important;height:8px!important;border-radius:50%!important;animation:pulse-dot 2s ease-in-out infinite!important}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.3;transform:scale(1.5)}}
</style>
@endpush

@section('content')
<div class="admin-page" style="gap:0">

    @if(session('success'))
    <div class="admin-flash admin-flash--success mb-4" role="status">
        <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-{{ $slaEnabled ? '5' : '4' }} gap-4 mb-6">
        <div class="ticket-stat-card">
            <div class="flex items-center gap-3">
                <div class="ticket-stat-card__icon bg-indigo-100 text-indigo-700">📫</div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($stats['total'] ?? 0) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">کل تیکت‌ها</div>
                </div>
            </div>
        </div>
        <div class="ticket-stat-card">
            <div class="flex items-center gap-3">
                <div class="ticket-stat-card__icon bg-yellow-100 text-yellow-700">🟡</div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($stats['open'] ?? 0) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">باز</div>
                </div>
            </div>
        </div>
        <div class="ticket-stat-card">
            <div class="flex items-center gap-3">
                <div class="ticket-stat-card__icon bg-blue-100 text-blue-700">🔵</div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($stats['in_progress'] ?? 0) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">در حال بررسی</div>
                </div>
            </div>
        </div>
        <div class="ticket-stat-card">
            <div class="flex items-center gap-3">
                <div class="ticket-stat-card__icon bg-green-100 text-green-700">✅</div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($stats['closed'] ?? 0) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">بسته شده</div>
                </div>
            </div>
        </div>
        @if($slaEnabled)
        @php $breachCount = $stats['sla_breaches'] ?? 0; @endphp
        <div class="ticket-stat-card {{ $breachCount > 0 ? 'ring-2 ring-red-300' : '' }}">
            <div class="flex items-center gap-3">
                <div class="ticket-stat-card__icon {{ $breachCount > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">⚠️</div>
                <div>
                    <div class="text-2xl font-bold {{ $breachCount > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ \App\Helpers\NumberHelper::formatFa($breachCount) }}</div>
                    <div class="text-xs {{ $breachCount > 0 ? 'text-red-600' : 'text-gray-500' }} mt-0.5">SLA نقض شده</div>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('tickets.admin.create') }}" class="btn-gradient">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            ثبت تیکت جدید
        </a>
    </div>

    {{-- Filter + Table share one Alpine scope --}}
    <div x-data="{ search: '', status: '', priority: '' }">

        {{-- Filter Bar --}}
        <div class="glass-filter-bar mb-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <div class="relative flex-1 w-full sm:w-auto">
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="search" placeholder="جستجوی تیکت‌ها..."
                           class="w-full pr-10 pl-4 py-2.5 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
                </div>
                <select x-model="status" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="open">باز</option>
                    <option value="in_progress">در حال بررسی</option>
                    <option value="closed">بسته</option>
                </select>
                <select x-model="priority" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                    <option value="">همه اولویت‌ها</option>
                    <option value="high">بالا</option>
                    <option value="normal">عادی</option>
                    <option value="low">کم</option>
                </select>
            </div>
        </div>

        {{-- Tickets Table --}}
        <div class="glass-card-strong overflow-hidden rounded-2xl">
        <div class="overflow-x-auto scrollbar-thin">
        <table class="w-full text-right border-collapse glass-table">
        <thead>
            <tr>
                <th class="p-3 pr-5">شماره</th>
                <th class="p-3">سایت</th>
                <th class="p-3">موضوع</th>
                <th class="p-3">وضعیت</th>
                <th class="p-3">اولویت</th>
                @if($slaEnabled)<th class="p-3">SLA</th>@endif
                <th class="p-3">تاریخ</th>
                <th class="p-3 pl-5">عملیات</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tickets as $ticket)
        <tr class="ticket-row border-b border-gray-100"
            data-subject="{{ $ticket->subject }}"
            data-ticket-no="{{ $ticket->ticket_number }}"
            data-status="{{ $ticket->status }}"
            data-priority="{{ $ticket->priority ?? 'normal' }}"
            x-show="
                (!search || $el.dataset.subject.toLowerCase().includes(search.toLowerCase()) || $el.dataset.ticketNo.includes(search)) &&
                (!status || $el.dataset.status === status) &&
                (!priority || $el.dataset.priority === priority)
            ">

            {{-- شماره --}}
            <td class="p-3 pr-5">
                <span class="font-mono font-bold text-indigo-700 text-xs bg-indigo-50 px-2 py-1 rounded-lg">{{ $ticket->ticket_number }}</span>
            </td>

            {{-- سایت --}}
            <td class="p-3">
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500 shrink-0"></span>
                    <span class="text-sm text-gray-700 font-medium">{{ $ticket->site?->name ?? '—' }}</span>
                </div>
            </td>

            {{-- موضوع --}}
            <td class="p-3">
                <span class="text-sm font-medium text-gray-800">{{ Str::limit($ticket->subject, 50) }}</span>
            </td>

            {{-- وضعیت --}}
            <td class="p-3">
                @if($ticket->status === 'open')
                    <span class="ticket-status-badge bg-yellow-50 text-yellow-700 border-yellow-200">
                        <span class="priority-dot bg-yellow-500"></span> باز
                    </span>
                @elseif($ticket->status === 'in_progress')
                    <span class="ticket-status-badge bg-blue-50 text-blue-700 border-blue-200">
                        <span class="priority-dot bg-blue-500"></span> در حال بررسی
                    </span>
                @else
                    <span class="ticket-status-badge bg-gray-50 text-gray-600 border-gray-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        بسته
                    </span>
                @endif
            </td>

            {{-- اولویت --}}
            <td class="p-3">
                @php
                    $p = $ticket->priority ?? 'normal';
                    $pBg    = $p === 'high' ? 'bg-red-50 text-red-700 border-red-200' : ($p === 'low' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-600 border-blue-200');
                    $pLabel = $p === 'high' ? 'بالا' : ($p === 'low' ? 'کم' : 'عادی');
                    $pDot   = $p === 'high' ? 'bg-red-500' : ($p === 'low' ? 'bg-gray-400' : 'bg-blue-500');
                @endphp
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $pBg }}">
                    <span class="priority-dot {{ $pDot }}"></span> {{ $pLabel }}
                </span>
            </td>

            {{-- SLA --}}
            @if($slaEnabled)
            <td class="p-3"><span class="text-xs text-gray-400">—</span></td>
            @endif

            {{-- تاریخ --}}
            <td class="p-3 text-sm text-gray-500">
                {{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d H:i') }}
            </td>

            {{-- عملیات --}}
            <td class="p-3 pl-5">
                <a href="{{ route('tickets.admin.show', $ticket->id) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-all hover:shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    مشاهده
                </a>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="{{ $slaEnabled ? 8 : 7 }}" class="text-center py-12">
                <div class="flex flex-col items-center gap-3">
                    <span class="text-5xl">📭</span>
                    <span class="text-gray-400 font-medium">هیچ تیکتی یافت نشد</span>
                    <p class="text-gray-400 text-sm">هنوز تیکتی ثبت نشده است.</p>
                    <a href="{{ route('tickets.admin.create') }}" class="btn-gradient !text-sm !py-2 !px-4 mt-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        ثبت تیکت جدید
                    </a>
                </div>
            </td>
        </tr>
        @endforelse
        </tbody>
        </table>
        </div>
        </div>

    </div>{{-- end Alpine scope --}}

    {{-- Pagination --}}
    <div class="mt-6 flex justify-center">
        {{ $tickets->withQueryString()->links() }}
    </div>

</div>
@endsection
