@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', 'تیکت‌های پشتیبانی')

@section('content')
@tenant
<div class="p-6 max-w-4xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">تیکت‌های پشتیبانی</h1>
        <a href="{{ route('tickets.site.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            تیکت جدید
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        @foreach([['open','باز','yellow'],['in_progress','در جریان','blue'],['closed','بسته','gray']] as [$key,$label,$color])
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xs text-gray-500">{{ $label }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stats[$key] ?? 0 }}</p>
        </div>
        @endforeach
    </div>

    {{-- Filter --}}
    <form method="GET" class="flex gap-3 mb-6">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="جستجو..."
               class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
        <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none">
            <option value="">همه</option>
            <option value="open" @selected(($filters['status'] ?? '') === 'open')>باز</option>
            <option value="in_progress" @selected(($filters['status'] ?? '') === 'in_progress')>در جریان</option>
            <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>بسته</option>
        </select>
        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-4 py-2 rounded-lg transition">
            فیلتر
        </button>
    </form>

    {{-- Ticket List --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        @if($tickets->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <p class="text-4xl mb-3">🎫</p>
            <p class="text-lg font-medium">هیچ تیکتی وجود ندارد</p>
            <a href="{{ route('tickets.site.create') }}" class="text-blue-600 text-sm mt-2 inline-block">اولین تیکت خود را ثبت کنید</a>
        </div>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($tickets as $ticket)
            <a href="{{ route('tickets.site.show', $ticket->id) }}"
               class="flex items-center justify-between px-5 py-4 hover:bg-gray-50 transition">
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-800 truncate">{{ $ticket->subject }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $ticket->ticket_number }} · {{ $ticket->created_at->diffForHumans() }}</p>
                </div>
                <div class="flex items-center gap-3 ml-4">
                    @include('tickets::components.status-badge', ['status' => $ticket->status])
                    @include('tickets::components.priority-badge', ['priority' => $ticket->priority])
                </div>
            </a>
            @endforeach
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $tickets->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endtenant
@endsection
