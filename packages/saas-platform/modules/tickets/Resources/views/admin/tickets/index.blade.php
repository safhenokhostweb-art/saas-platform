@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'مدیریت تیکت‌ها')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">تیکت‌ها</h1>
        <a href="{{ route('tickets.admin.create') }}"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            تیکت جدید
        </a>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        @foreach([['total','کل','gray'],['open','باز','yellow'],['in_progress','در حال بررسی','blue'],['closed','بسته','green']] as [$key, $label, $color])
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs text-gray-500 mb-1">{{ $label }}</p>
            <p class="text-2xl font-bold text-gray-800">{{ $stats[$key] ?? 0 }}</p>
        </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 mb-6 bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="جستجو..."
               class="flex-1 min-w-48 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">

        <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
            <option value="">همه وضعیت‌ها</option>
            <option value="open" @selected(($filters['status'] ?? '') === 'open')>باز</option>
            <option value="in_progress" @selected(($filters['status'] ?? '') === 'in_progress')>در حال بررسی</option>
            <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>بسته</option>
        </select>

        <select name="priority" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
            <option value="">همه اولویت‌ها</option>
            <option value="high" @selected(($filters['priority'] ?? '') === 'high')>فوری</option>
            <option value="normal" @selected(($filters['priority'] ?? '') === 'normal')>معمولی</option>
            <option value="low" @selected(($filters['priority'] ?? '') === 'low')>کم</option>
        </select>

        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-4 py-2 rounded-lg transition">
            فیلتر
        </button>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        @if($tickets->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <p class="text-lg">هیچ تیکتی یافت نشد.</p>
                <a href="{{ route('tickets.admin.create') }}" class="text-blue-600 text-sm mt-2 inline-block">ایجاد تیکت جدید</a>
            </div>
        @else
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-right">شماره</th>
                    <th class="px-4 py-3 text-right">موضوع</th>
                    <th class="px-4 py-3 text-right">وضعیت</th>
                    <th class="px-4 py-3 text-right">اولویت</th>
                    <th class="px-4 py-3 text-right">سایت</th>
                    @if($slaEnabled)
                    <th class="px-4 py-3 text-right">SLA</th>
                    @endif
                    <th class="px-4 py-3 text-right">تاریخ</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($tickets as $ticket)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $ticket->ticket_number }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800 max-w-xs truncate">{{ $ticket->subject }}</td>
                    <td class="px-4 py-3">
                        @include('tickets::components.status-badge', ['status' => $ticket->status])
                    </td>
                    <td class="px-4 py-3">
                        @include('tickets::components.priority-badge', ['priority' => $ticket->priority])
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $ticket->site?->name ?? '—' }}</td>
                    @if($slaEnabled)
                    <td class="px-4 py-3">
                        {{-- SLA indicator placeholder --}}
                        <span class="text-xs text-gray-400">—</span>
                    </td>
                    @endif
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $ticket->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('tickets.admin.show', $ticket->id) }}"
                           class="text-blue-600 hover:underline text-xs">مشاهده</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-100">
            {{ $tickets->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
