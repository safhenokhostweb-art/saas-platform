@php
    use App\Helpers\Jalali;
    $settings = [
        'customer_bg' => App\Models\Setting::get('support_chat_customer_bg', '#e3f2fd'),
        'customer_text' => App\Models\Setting::get('support_chat_customer_text', '#1a1a2e'),
        'staff_bg'    => App\Models\Setting::get('support_chat_staff_bg', '#dcf8c6'),
        'staff_text'  => App\Models\Setting::get('support_chat_staff_text', '#1a1a2e'),
    ];
@endphp

@extends('layouts.admin')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('customer.support-tickets.index') }}" class="btn btn-secondary btn-sm !px-3 !py-1.5 text-sm">
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            بازگشت
        </a>
        <span>تیکت {{ $supportTicket->ticket_number ?? '' }} — {{ Str::limit($supportTicket->subject, 50) }}</span>
    </div>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
@endif
@if(session('error'))

<div class="max-w-4xl mx-auto">
    {{-- Chat Header / Ticket Info Bar --}}
    <div class="bg-white rounded-t-2xl shadow-sm border border-gray-200 border-b-0 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                @if($supportTicket->status === 'open')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-yellow-50 text-yellow-700 border border-yellow-200">
                        <span class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></span>
                        باز
                    </span>
                @elseif($supportTicket->status === 'in_progress')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                        در حال بررسی
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-gray-50 text-gray-600 border border-gray-200">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        بسته شده
                    </span>
                @endif
                @if($supportTicket->priority === 'high')
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-600 border border-red-200">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        اولویت بالا
                    </span>
                @elseif($supportTicket->priority === 'low')
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200">اولویت کم</span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-600 border border-blue-200">اولویت معمولی</span>
                @endif
            </div>

            {{-- Initial Message (Ticket Creator) --}}
            <div class="flex flex-col" style="align-items: flex-start;">
                <div class="flex justify-center my-3 w-full">
                    <span class="text-[10px] text-gray-400 bg-white/80 px-3 py-1 rounded-full shadow-sm">
                        {{ \Carbon\Carbon::parse($supportTicket->created_at)->diffInDays(now()) === 0 ? 'امروز' : (\Carbon\Carbon::parse($supportTicket->created_at)->diffInDays(now()) === 1 ? 'دیروز' : Jalali::format($supportTicket->created_at)) }}
                    </span>
                </div>
                <div class="flex items-end gap-2 max-w-[85%] sm:max-w-[75%] mb-4" style="align-self: flex-start;">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm" style="background-color: #6366f1;">
                        {{ $supportTicket->customer_name ? mb_substr($supportTicket->customer_name, 0, 1) : 'ش' }}
                    </div>
                    <div>
                        <div class="rounded-2xl rounded-br-sm px-4 py-2.5 shadow-sm"
                             style="background-color: {{ $settings['customer_bg'] }}; color: {{ $settings['customer_text'] }};">
                            <p class="text-xs font-semibold mb-1 opacity-80">{{ $supportTicket->customer_name ?? 'شما' }}</p>
                            <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $supportTicket->message }}</p>
                            @if($supportTicket->attachments)
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach($supportTicket->attachments as $file)
                                        <a href="{{ Storage::url($file['path']) }}" target="_blank"
                                           class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all"
                                           title="{{ $file['name'] }}">
                                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <span class="truncate max-w-[100px]">{{ $file['name'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <span class="text-[10px] text-gray-400 mt-1 px-1">{{ Jalali::formatTime($supportTicket->created_at) }}</span>
                    </div>
                </div>
            </div>

            {{-- Replies Loop --}}
            @php $lastDate = null; @endphp
            @foreach($supportTicket->replies as $index => $reply)
                @php
                    $replyDate = \Carbon\Carbon::parse($reply->created_at)->toDateString();
                    $isStaff = $reply->isFromStaff();
                    $authorName = $isStaff ? ($reply->user->name ?? 'پشتیبان') : ($reply->customer_name ?? 'شما');
                @endphp

                @if($replyDate !== $lastDate)
                    @php $lastDate = $replyDate; @endphp
                    <div class="flex justify-center my-3 w-full">
                        <span class="text-[10px] text-gray-400 bg-white/80 px-3 py-1 rounded-full shadow-sm">

            <div x-show="showScrollBtn"

        {{-- Reply Input Area --}}
        @if($supportTicket->status !== 'closed')
            <div class="border-t border-gray-200 bg-white p-3">
                <form method="post" action="{{ route('customer.support-tickets.replies.store', $supportTicket) }}"
                      @submit="isSending = true; setTimeout(() => { isSending = false; }, 3000);"
                      class="flex items-end gap-2">
                    @csrf
                    <div class="flex-1 relative">
                        <textarea name="message"
                                  x-model="message"
                                  class="form-control w-full !rounded-2xl !border-gray-300 !resize-none !py-3 !px-4 !text-sm focus:!border-blue-400 focus:!ring-2 focus:!ring-blue-100 transition-all"
                                  rows="2" required maxlength="5000"
                                  placeholder="پیام خود را بنویسید..."
                                  @keydown.enter.prevent="if(!$event.shiftKey) $el.closest('form').submit()"></textarea>
                        <small class="text-[10px] text-gray-400 mt-1 block mr-2">اینتر = ارسال | Shift+Enter = خط جدید</small>
                    </div>
                    <button type="submit"
                            :disabled="isSending || !message.trim()"
                            class="btn !rounded-full !p-3 !min-w-[48px] !h-[48px] flex items-center justify-center transition-all"
                            :class="isSending || !message.trim() ? '!bg-gray-300 !text-gray-500 !cursor-not-allowed' : '!bg-blue-600 !text-white hover:!bg-blue-700'">
                        <template x-if="!isSending">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </template>
                        <template x-if="isSending">
                            <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </template>
                    </button>
                </form>
            </div>
        @else
            <div class="border-t border-gray-200 bg-gray-50 p-4 text-center">
                <div class="flex items-center justify-center gap-2 text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    <span class="text-sm">این تیکت بسته شده است. برای پیگیری مجدد لطفاً یک تیکت جدید ثبت کنید.</span>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="sticky bottom-2 flex justify-center">
                <button @click="scrollDown"
                        class="bg-white text-gray-600 rounded-full px-4 py-2 shadow-lg border border-gray-200 hover:bg-gray-50 transition-all text-xs font-medium flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                    پیام‌های جدید
                </button>
            </div>
        </div>
                            @if(\Carbon\Carbon::parse($reply->created_at)->isToday())
                                امروز
                            @elseif(\Carbon\Carbon::parse($reply->created_at)->isYesterday())
                                دیروز
                            @else
                                {{ Jalali::format($reply->created_at) }}
                            @endif
                        </span>
                    </div>
                @endif

                <div class="flex items-end gap-2 max-w-[85%] sm:max-w-[75%] mb-4 {{ $isStaff ? 'flex-row-reverse' : '' }}"
                     style="align-self: {{ $isStaff ? 'flex-end' : 'flex-start' }};">
                    @if($isStaff)
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm" style="background-color: #10b981;">
                            {{ $authorName ? mb_substr($authorName, 0, 1) : 'پ' }}
                        </div>
                    @else
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm" style="background-color: #6366f1;">
                            {{ $authorName ? mb_substr($authorName, 0, 1) : 'ش' }}
                        </div>
                    @endif
                    <div>
                        <div class="rounded-2xl px-4 py-2.5 shadow-sm {{ $isStaff ? 'rounded-bl-sm' : 'rounded-br-sm' }}"
                             style="background-color: {{ $isStaff ? $settings['staff_bg'] : $settings['customer_bg'] }}; color: {{ $isStaff ? $settings['staff_text'] : $settings['customer_text'] }};">
                            <p class="text-xs font-semibold mb-1 opacity-80">
                                {{ $authorName }}
                                @if($isStaff)
                                    <span class="inline-flex items-center gap-0.5 text-[9px] bg-green-200/50 px-1.5 py-0.5 rounded-full mr-1">
                                        <svg class="w-2.5 h-2.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        پشتیبان
                                    </span>
                                @endif
                            </p>
                            <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $reply->message }}</p>
                            @if($reply->attachments)
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach($reply->attachments as $file)
                                        <a href="{{ Storage::url($file['path']) }}" target="_blank"
                                           class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all"
                                           title="{{ $file['name'] }}">
                                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <span class="truncate max-w-[100px]">{{ $file['name'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <span class="text-[10px] text-gray-400 mt-1 px-1 {{ $isStaff ? 'text-left block' : '' }}">{{ Jalali::formatTime($reply->created_at) }}</span>
                    </div>
                </div>
            @endforeach
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span><svg class="w-3.5 h-3.5 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>{{ Jalali::format($supportTicket->created_at) }}</span>
                @if($supportTicket->site)<span>{{ $supportTicket->site->title }}</span>@endif
                @if($supportTicket->assignee)<span>{{ $supportTicket->assignee->name }}</span>@endif
            </div>
        </div>
        <div class="mt-3">
            <h2 class="text-lg font-bold text-gray-900">{{ $supportTicket->subject }}</h2>
        </div>
    </div>

    {{-- Chat Messages Area --}}
    <div class="bg-white shadow-sm border border-gray-200"
         x-data="supportChat()"
         data-customer-bg="{{ $settings['customer_bg'] }}"
         data-customer-text="{{ $settings['customer_text'] }}"
         data-staff-bg="{{ $settings['staff_bg'] }}"
         data-staff-text="{{ $settings['staff_text'] }}"
         style="height: 500px; display: flex; flex-direction: column;">

        {{-- Messages Container --}}
        <div x-ref="chatMessages"
             @scroll="handleScroll"
             class="flex-1 overflow-y-auto p-4 space-y-1"
             style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); scroll-behavior: smooth;">
    <div class="alert alert-danger mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('error') }}
    </div>
@endif
