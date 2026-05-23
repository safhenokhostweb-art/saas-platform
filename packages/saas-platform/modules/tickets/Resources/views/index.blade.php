@push('head')
@asset_module('tickets', 'css/tickets.css')
@endpush

@extends('admin.layout')
@section('title', 'مدیریت تیکت‌ها')
@section('header', 'مدیریت تیکت‌ها')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-3" role="status">
        <svg class="w-5 h-5 flex-shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- Stat Cards --}}
    @php
    $stats = app(\SaasPlatform\Modules\Tickets\Services\TicketService::class)->getStats();
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">کل تیکت‌ها</div>
                </div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-yellow-100 text-yellow-600 flex items-center justify-center">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['open']) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">باز</div>
                </div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['in_progress']) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">در حال بررسی</div>
                </div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-green-100 text-green-600 flex items-center justify-center">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['closed']) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">بسته شده</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <a href="{{ route('admin.tickets.create') }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 shadow-sm transition-all">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            ثبت تیکت جدید
        </a>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white/75 backdrop-blur-xl border border-gray-200/50 rounded-2xl p-4 shadow-sm" x-data="{ search: '', status: '', priority: '' }">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <div class="relative flex-1 w-full sm:w-auto">
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="جستجوی تیکت‌ها..." class="w-full pr-10 pl-4 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
            </div>
            <select x-model="status" class="px-4 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه وضعیت‌ها</option>
                <option value="open">باز</option>
                <option value="in_progress">در حال بررسی</option>
                <option value="closed">بسته</option>
            </select>
            <select x-model="priority" class="px-4 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه اولویت‌ها</option>
                <option value="high">بالا</option>
                <option value="normal">عادی</option>
                <option value="low">کم</option>
            </select>
        </div>
    </div>

    {{-- Tickets Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="px-4 py-3 text-xs font-medium text-gray-500">شماره</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500">موضوع</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500">وضعیت</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500">اولویت</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500">تاریخ</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3">
                            <span class="font-mono font-bold text-indigo-700 text-xs bg-indigo-50 px-2 py-1 rounded-lg">{{ $ticket->ticket_number }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-sm font-medium text-gray-800">{{ Str::limit($ticket->subject, 50) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($ticket->status === 'open')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">
                                <span class="w-2 h-2 rounded-full bg-yellow-500"></span> باز
                            </span>
                            @elseif($ticket->status === 'in_progress')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span> در حال بررسی
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg> بسته
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                            $p = $ticket->priority ?? 'normal';
                            $pColor = $p === 'high' ? 'red' : ($p === 'low' ? 'gray' : 'blue');
                            $pLabel = $p === 'high' ? 'بالا' : ($p === 'low' ? 'کم' : 'عادی');
                            $pBg = $p === 'high' ? 'bg-red-50 text-red-700 border-red-200' : ($p === 'low' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-600 border-blue-200');
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium {{ $pBg }}">
                                <span class="w-2 h-2 rounded-full bg-{{ $pColor }}-500"></span> {{ $pLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $ticket->created_at->format('Y/m/d H:i') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-all hover:shadow-sm">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                مشاهده
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-12">
                            <div class="flex flex-col items-center gap-3">
                                <span class="text-5xl">📭</span>
                                <span class="text-gray-400 font-medium">هیچ تیکتی یافت نشد</span>
                                <p class="text-gray-400 text-sm">هنوز تیکتی ثبت نشده است.</p>
                                <a href="{{ route('admin.tickets.create') }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 shadow-sm transition-all">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
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
    <div class="flex justify-center">
        {{ $tickets->links() }}
    </div>

</div>
@endsection
