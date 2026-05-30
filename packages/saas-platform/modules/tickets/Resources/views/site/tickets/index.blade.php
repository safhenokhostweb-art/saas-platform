@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', 'تیکت‌های پشتیبانی')

@section('content')
<x-core-ui::design-tokens />

<div class="admin-page" role="main" aria-label="پشتیبانی" x-data="{
    search: '{{ request('q') }}',
    status: '{{ request('status') }}',
    priority: '{{ request('priority') }}'
}">

    {{-- ─── Flash Messages ────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl px-5 py-3.5 mb-5"
         x-data="{ show: true }" x-show="show" x-transition.duration.300ms>
        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm text-green-800 font-medium flex-1">{{ session('success') }}</span>
        <button @click="show=false" class="text-green-400 hover:text-green-600 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    {{-- ─── Stat Cards ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">

        {{-- Total --}}
        <div class="tkt-stat col-span-2 sm:col-span-1">
            <div class="tkt-stat__accent bg-gradient-to-b from-indigo-500 to-purple-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-gradient-to-br from-indigo-50 to-purple-50 text-indigo-600">📫</div>
                <div>
                    <div class="tkt-stat__val text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($stats['total'] ?? 0) }}</div>
                    <div class="tkt-stat__label text-gray-500">کل تیکت‌ها</div>
                </div>
            </div>
        </div>

        {{-- Open --}}
        <div class="tkt-stat cursor-pointer" @click="status='open'">
            <div class="tkt-stat__accent bg-gradient-to-b from-yellow-400 to-orange-400"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-yellow-50 text-yellow-600">
                    <span class="relative flex h-6 w-6 items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-30"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-yellow-500"></span>
                    </span>
                </div>
                <div>
                    <div class="tkt-stat__val text-yellow-700">{{ \App\Helpers\NumberHelper::formatFa($stats['open'] ?? 0) }}</div>
                    <div class="tkt-stat__label text-yellow-600">باز</div>
                    @if(($stats['open'] ?? 0) > 0)
                    <span class="tkt-stat__trend bg-yellow-100 text-yellow-700">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        نیاز به پاسخ
                    </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- In Progress --}}
        <div class="tkt-stat cursor-pointer" @click="status='in_progress'">
            <div class="tkt-stat__accent bg-gradient-to-b from-blue-400 to-indigo-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-blue-50 text-blue-600">
                    <svg class="w-6 h-6 animate-spin" style="animation-duration:3s" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-blue-700">{{ \App\Helpers\NumberHelper::formatFa($stats['in_progress'] ?? 0) }}</div>
                    <div class="tkt-stat__label text-blue-600">در حال بررسی</div>
                </div>
            </div>
        </div>

        {{-- Closed --}}
        <div class="tkt-stat cursor-pointer" @click="status='closed'">
            <div class="tkt-stat__accent bg-gradient-to-b from-green-400 to-teal-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-green-50 text-green-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-green-700">{{ \App\Helpers\NumberHelper::formatFa($stats['closed'] ?? 0) }}</div>
                    <div class="tkt-stat__label text-green-600">بسته شده</div>
                </div>
            </div>
        </div>

    </div>

    {{-- ─── Actions Bar ────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            تیکت‌های من
        </h2>
        <a href="{{ route('tickets.site.create') }}" class="btn-gradient">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            تیکت جدید
        </a>
    </div>

    {{-- ─── Filter Bar ─────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('tickets.site.index') }}" class="glass-filter-bar mb-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <div class="relative flex-1 w-full sm:w-auto">
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" x-model="search" placeholder="جستجوی موضوع یا شماره تیکت..."
                       class="w-full pr-9 pl-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
            </div>
            <select name="status" x-model="status" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه وضعیت‌ها</option>
                <option value="open">باز</option>
                <option value="in_progress">در حال بررسی</option>
                <option value="closed">بسته</option>
            </select>
            <select name="priority" x-model="priority" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه اولویت‌ها</option>
                <option value="high">🔴 بالا</option>
                <option value="normal">🔵 عادی</option>
                <option value="low">⚪ کم</option>
            </select>
            <div class="flex gap-2">
                <button type="submit"
                        class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition-all justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    جستجو
                </button>
                <button type="button" @click="search=''; status=''; priority=''"
                        class="flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm text-gray-500 hover:bg-gray-50 transition-all justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    پاک کردن
                </button>
            </div>
        </div>
    </form>

    {{-- ─── Tickets Table ──────────────────────────────────────────────── --}}
    <div class="glass-card-strong overflow-hidden">
    <div class="overflow-x-auto scrollbar-thin">
    <table class="w-full text-right border-collapse" style="min-width:600px">
    <thead>
        <tr class="bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
            <th class="p-3 pr-5 text-xs font-bold text-gray-500 uppercase tracking-wider">شماره</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">موضوع</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">وضعیت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">اولویت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">پاسخ‌ها</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">تاریخ</th>
            <th class="p-3 pl-5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">عملیات</th>
        </tr>
    </thead>
    <tbody>
    @forelse($tickets as $ticket)
    @php
        $p    = $ticket->priority ?? 'normal';
        $pBg  = $p === 'high' ? 'bg-red-50 text-red-700 border-red-200' : ($p === 'low' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-600 border-blue-200');
        $pDot = $p === 'high' ? 'bg-red-500' : ($p === 'low' ? 'bg-gray-400' : 'bg-blue-500');
        $pLbl = $p === 'high' ? 'بالا' : ($p === 'low' ? 'کم' : 'عادی');
    @endphp
    <tr class="ticket-row border-b border-gray-50"
        data-subject="{{ $ticket->subject }}"
        data-ticket-no="{{ $ticket->ticket_number }}"
        data-status="{{ $ticket->status }}"
        data-priority="{{ $p }}"
        x-show="
            (!search || $el.dataset.subject.toLowerCase().includes(search.toLowerCase()) || $el.dataset.ticketNo.includes(search)) &&
            (!status || $el.dataset.status === status) &&
            (!priority || $el.dataset.priority === priority)
        "
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0">

        {{-- شماره --}}
        <td class="p-3 pr-5">
            <span class="font-mono font-bold text-indigo-700 text-xs bg-indigo-50 px-2 py-1 rounded-lg border border-indigo-100">{{ $ticket->ticket_number }}</span>
        </td>

        {{-- موضوع --}}
        <td class="p-3">
            <a href="{{ route('tickets.site.show', $ticket->id) }}" class="group">
                <span class="text-sm font-semibold text-gray-800 group-hover:text-indigo-700 transition-colors">{{ Str::limit($ticket->subject, 45) }}</span>
                @if($ticket->category)
                <span class="ml-2 text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">{{ $ticket->category }}</span>
                @endif
            </a>
        </td>

        {{-- وضعیت --}}
        <td class="p-3">
            @if($ticket->status === 'open')
                <span class="tkt-badge bg-yellow-50 text-yellow-700 border-yellow-200">
                    <span class="pdot bg-yellow-500"></span>باز
                </span>
            @elseif($ticket->status === 'in_progress')
                <span class="tkt-badge bg-blue-50 text-blue-700 border-blue-200">
                    <span class="pdot bg-blue-500"></span>در حال بررسی
                </span>
            @else
                <span class="tkt-badge bg-green-50 text-green-700 border-green-200">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>بسته
                </span>
            @endif
        </td>

        {{-- اولویت --}}
        <td class="p-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $pBg }}">
                <span class="pdot {{ $pDot }}"></span>{{ $pLbl }}
            </span>
        </td>

        {{-- پاسخ‌ها --}}
        <td class="p-3">
            <span class="text-xs font-semibold text-gray-600">
                {{ \App\Helpers\NumberHelper::formatFa(($ticket->publicReplies ? $ticket->publicReplies->count() : 0) + 1) }}
            </span>
        </td>

        {{-- تاریخ --}}
        <td class="p-3 text-xs text-gray-500 whitespace-nowrap">
            {{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d') }}
            <div class="text-[10px] text-gray-400">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'H:i') }}</div>
        </td>

        {{-- عملیات --}}
        <td class="p-3 pl-5">
            <a href="{{ route('tickets.site.show', $ticket->id) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-all hover:shadow-sm whitespace-nowrap">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                مشاهده
            </a>
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="7" class="text-center py-16">
            <div class="flex flex-col items-center gap-3">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center text-3xl shadow-sm">📭</div>
                <p class="text-gray-600 font-semibold">هیچ تیکتی یافت نشد</p>
                <p class="text-gray-400 text-sm">
                    @if(request('q') || request('status') || request('priority'))
                        فیلترها را تغییر دهید.
                    @else
                        اولین تیکت پشتیبانی خود را ثبت کنید.
                    @endif
                </p>
                <a href="{{ route('tickets.site.create') }}" class="btn-gradient !text-sm !py-2 !px-5 mt-2">
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

    {{-- Pagination --}}
    @if(method_exists($tickets, 'links') && $tickets->hasPages())
    <div class="mt-6 flex justify-center">
        {{ $tickets->withQueryString()->links() }}
    </div>
    @endif

</div>
@endsection
