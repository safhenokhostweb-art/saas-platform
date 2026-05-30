@extends(config('tickets.layouts.site', 'admin.layout'))

@section('title', 'تاریخچه تیکت‌ها')

@section('content')
<x-core-ui::design-tokens />

<div class="admin-page" role="main" aria-label="تاریخچه تیکت‌ها">

    {{-- ─── Header ─────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 mb-6 flex-wrap">
        <a href="{{ route('tickets.site.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/80 hover:bg-white border border-gray-200 transition-all hover:shadow-sm shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت به پشتیبانی
        </a>
        <div>
            <h2 class="text-xl font-bold text-gray-900">تاریخچه تیکت‌ها</h2>
            <p class="text-sm text-gray-500 mt-0.5">مشاهده و پیگیری تمام تیکت‌های ثبت‌شده</p>
        </div>
    </div>

    {{-- ─── Alerts ─────────────────────────────────────────────────────── --}}
    @if(!empty($error))
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 rounded-2xl px-5 py-4 mb-5">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-red-700">{{ $error }}</p>
    </div>
    @endif

    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl px-5 py-3.5 mb-5"
         x-data="{ show: true }" x-show="show" x-transition.duration.300ms>
        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm text-green-800 font-medium flex-1">{{ session('success') }}</span>
        <button @click="show=false" class="text-green-400 hover:text-green-600 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    {{-- ─── Ticket List ─────────────────────────────────────────────────── --}}
    @if($tickets->isEmpty())
    <div class="glass-card-strong text-center py-16">
        <div class="flex flex-col items-center gap-3">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center text-3xl shadow-sm">📭</div>
            <p class="text-gray-600 font-semibold">هیچ تیکتی ثبت نشده</p>
            <p class="text-gray-400 text-sm">هنوز تیکتی ارسال نکرده‌اید.</p>
            <a href="{{ route('tickets.site.create') }}" class="btn-gradient !text-sm !py-2 !px-5 mt-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                ثبت تیکت جدید
            </a>
        </div>
    </div>
    @else
    <div class="space-y-5">
        @foreach($tickets as $ticket)
        <div class="glass-card-strong {{ $ticket->status === 'closed' ? 'opacity-80' : '' }}">

            {{-- Ticket header --}}
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="font-mono font-bold text-indigo-700 text-xs bg-indigo-50 px-2 py-1 rounded-lg border border-indigo-100 shrink-0">{{ $ticket->ticket_number ?? '—' }}</span>
                    <a href="{{ route('tickets.site.show', $ticket->id) }}"
                       class="font-semibold text-gray-900 hover:text-indigo-700 transition-colors truncate text-sm">
                        {{ $ticket->subject }}
                    </a>
                </div>
                <div class="flex gap-2 flex-shrink-0">
                    @if($ticket->status === 'open')
                    <span class="tkt-badge bg-yellow-50 text-yellow-700 border-yellow-200">
                        <span class="pdot bg-yellow-500 animate-pulse"></span>باز
                    </span>
                    @elseif($ticket->status === 'in_progress')
                    <span class="tkt-badge bg-blue-50 text-blue-700 border-blue-200">
                        <span class="pdot bg-blue-500 animate-pulse"></span>در حال بررسی
                    </span>
                    @else
                    <span class="tkt-badge bg-green-50 text-green-700 border-green-200">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>بسته
                    </span>
                    @endif

                    @if($ticket->priority)
                    @php
                        $p = $ticket->priority;
                        $pBg = $p === 'high' ? 'bg-red-50 text-red-600 border-red-200' : ($p === 'low' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-600 border-blue-200');
                        $pDot = $p === 'high' ? 'bg-red-500' : ($p === 'low' ? 'bg-gray-400' : 'bg-blue-500');
                        $pLbl = $p === 'high' ? 'بالا' : ($p === 'low' ? 'کم' : 'عادی');
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $pBg }}">
                        <span class="pdot {{ $pDot }}"></span>{{ $pLbl }}
                    </span>
                    @endif
                </div>
            </div>

            {{-- Original message --}}
            <div class="glass-card p-4 mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs font-bold text-gray-600">پیام اصلی</span>
                    <span class="text-[10px] text-gray-400">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d H:i') }}</span>
                </div>
                <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $ticket->description }}</p>
            </div>

            {{-- Replies --}}
            @if($ticket->replies->isNotEmpty())
            <div class="mb-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-1 h-4 bg-gradient-to-b from-indigo-500 to-purple-500 rounded-full"></span>
                    <span class="text-xs font-bold text-gray-600">پاسخ‌ها ({{ \App\Helpers\NumberHelper::formatFa($ticket->replies->count()) }})</span>
                </div>
                <div class="space-y-2 pr-3 border-r-2 border-gray-100">
                    @foreach($ticket->replies as $reply)
                    @if(!($reply->author_type === 'internal'))
                    @php $isStaff = $reply->author_type === 'staff'; @endphp
                    <div class="{{ $isStaff ? 'bubble-staff' : 'bubble-customer' }} px-4 py-3">
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-xs font-bold text-gray-700 flex items-center gap-1.5">
                                @if($isStaff)
                                <span class="w-5 h-5 rounded-full bg-gradient-to-br from-green-400 to-teal-500 flex items-center justify-center text-white text-[9px] font-bold">پ</span>
                                پشتیبانی
                                <span class="text-[9px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">پشتیبان</span>
                                @else
                                <span class="w-5 h-5 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-[9px] font-bold">{{ mb_substr($reply->customer_name ?? 'ش', 0, 1) }}</span>
                                {{ $reply->customer_name ?? 'شما' }}
                                @endif
                            </span>
                            <span class="text-[10px] text-gray-400">{{ \App\Helpers\Jalali::formatFa($reply->created_at, 'Y/m/d H:i') }}</span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $reply->message }}</p>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Quick reply / closed notice --}}
            @if($ticket->status !== 'closed')
            <div class="border-t border-gray-100 pt-4">
                <form method="POST" action="{{ route('tickets.site.replies.store', $ticket->id) }}"
                      x-data="{ msg: '' }">
                    @csrf
                    <div class="flex flex-col gap-2">
                        <textarea name="message" x-model="msg"
                                  class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all resize-y"
                                  style="min-height:80px" rows="3" required maxlength="5000"
                                  placeholder="پاسخ خود را بنویسید..."></textarea>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-400" x-text="msg.length + ' / 5000 کاراکتر'"></span>
                            <button type="submit" class="btn-gradient !text-xs !py-2 !px-4">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                ارسال پاسخ
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @else
            <div class="border-t border-gray-100 pt-3 text-center">
                <span class="text-xs text-gray-400">این تیکت بسته شده است.</span>
                <a href="{{ route('tickets.site.create') }}" class="text-indigo-600 text-xs mr-2 hover:underline">ثبت تیکت جدید</a>
            </div>
            @endif

        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
