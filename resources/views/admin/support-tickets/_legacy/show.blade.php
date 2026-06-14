{{-- @deprecated PHASE 7.15 — Use tickets::admin.tickets.show (canonical package view). Route to: tickets.admin.show --}}
@php
    use App\Helpers\Jalali;
    $settings = [
        'customer_bg' => App\Models\Setting::get('support_chat_customer_bg', '#e3f2fd'),
        'customer_text' => App\Models\Setting::get('support_chat_customer_text', '#1a1a2e'),
        'staff_bg'    => App\Models\Setting::get('support_chat_staff_bg', '#dcf8c6'),
        'staff_text'  => App\Models\Setting::get('support_chat_staff_text', '#1a1a2e'),
    ];
    $sla = app(\App\Services\SupportSlaService::class);
@endphp

@extends('layouts.admin')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-secondary btn-sm !px-3 !py-1.5 text-sm">
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            بازگشت
        </a>
        <span>تیکت {{ $supportTicket->ticket_number ?? '' }} — {{ Str::limit($supportTicket->subject, 50) }}</span>
        <a href="{{ route('admin.support-tickets.settings') }}" class="btn btn-outline-secondary btn-sm !px-3 !py-1.5 text-sm mr-auto">
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            تنظیمات چت
        </a>
    </div>
@endsection

@section('content')

<div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-4">
    {{-- Chat Panel (2/3 width) --}}
    <div class="lg:col-span-2">
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

        {{-- Chat Messages Area --}}
        <div class="bg-white shadow-sm border border-gray-200"
             x-data="supportChat()"
             data-customer-bg="{{ $settings['customer_bg'] }}"
             data-customer-text="{{ $settings['customer_text'] }}"
             data-staff-bg="{{ $settings['staff_bg'] }}"
             data-staff-text="{{ $settings['staff_text'] }}"
             style="height: 450px; display: flex; flex-direction: column;">

            {{-- Messages Container --}}
            <div x-ref="chatMessages"
                 @scroll="handleScroll"
                 class="flex-1 overflow-y-auto p-4 space-y-1"
                 style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); scroll-behavior: smooth;">

                {{-- Initial Message (Customer) --}}
                <div class="flex flex-col" style="align-items: flex-start;">
                    <div class="flex justify-center my-3 w-full">

                {{-- Replies Loop --}}
                @php $lastDate = null; @endphp
                @foreach($supportTicket->replies as $index => $reply)
                    @php
                        $replyDate = \Carbon\Carbon::parse($reply->created_at)->toDateString();
                        $isStaff = $reply->isFromStaff();
                        $authorName = $isStaff ? ($reply->user->name ?? 'پشتیبان') : ($reply->customer_name ?? 'مشتری');
                    @endphp

                    @if($replyDate !== $lastDate)
                        @php $lastDate = $replyDate; @endphp
                        <div class="flex justify-center my-3 w-full">
                            <span class="text-[10px] text-gray-400 bg-white/80 px-3 py-1 rounded-full shadow-sm">
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

            {{-- Staff Reply Input --}}
            @if($supportTicket->status !== 'closed')

    {{-- Admin Sidebar Panel (1/3 width) --}}
    <div class="space-y-4">
        {{-- Ticket Info --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                اطلاعات تیکت
            </h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">شماره</span>
                    <span class="font-medium">{{ $supportTicket->ticket_number ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">سایت</span>
                    <span class="font-medium">{{ $supportTicket->site->title ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">سطح پشتیبانی</span>
                    <span class="font-medium">{{ $supportTicket->support_level ?? 'basic' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">مشتری</span>
                    <span class="font-medium">{{ $supportTicket->customer_name ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">موبایل</span>
                    <span class="font-medium text-left" dir="ltr">{{ $supportTicket->customer_mobile }}</span>
                </div>
                @if($supportTicket->category)
                <div class="flex justify-between">
                    <span class="text-gray-500">دسته‌بندی</span>
                    <span class="font-medium">{{ $supportTicket->category }}</span>
                </div>
                @endif
                @if($supportTicket->tags)
                <div class="flex justify-between">
                    <span class="text-gray-500">برچسب‌ها</span>
                    <span class="font-medium text-left" dir="ltr">{{ $supportTicket->tags }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-gray-500">تاریخ ایجاد</span>
                    <span class="font-medium">{{ Jalali::format($supportTicket->created_at) }}</span>
                </div>
                @if($supportTicket->resolved_at)
                <div class="flex justify-between">
                    <span class="text-gray-500">زمان حل</span>

        {{-- Assign Control --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                تخصیص به کارشناس
            </h3>
            <form method="post" action="{{ route('admin.support-tickets.assign', $supportTicket) }}">
                @csrf
                @method('PATCH')
                <div class="flex gap-2">
                    <select name="assigned_to" class="form-select flex-1 text-sm">
                        <option value="">— انتخاب کنید —</option>
                        @foreach(\App\Models\User::orderBy('name')->get(['id','name','mobile']) as $u)
                            <option value="{{ $u->id }}" {{ (int)$supportTicket->assigned_to === (int)$u->id ? 'selected' : '' }}>{{ $u->name ?: $u->mobile }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-secondary !py-2 !px-4 !text-sm">تخصیص</button>
                </div>
            </form>
            @if($supportTicket->assignee)
                <div class="mt-2 text-xs text-green-600 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    تخصیص یافته به {{ $supportTicket->assignee->name }}
                </div>
            @endif
        </div>

        {{-- Quick Actions --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                اقدامات سریع
            </h3>
            <div class="space-y-2">
                <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-outline-secondary w-full !text-sm !py-2">
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    بازگشت به لیست
                </a>
                @if($supportTicket->status !== 'closed')
                <form method="post" action="{{ route('admin.support-tickets.update-status', $supportTicket) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="closed">
                    <button type="submit" class="btn btn-outline-secondary w-full !text-sm !py-2 !text-red-600 !border-red-300 hover:!bg-red-50"
                            onclick="return confirm('آیا از بستن این تیکت اطمینان دارید؟')">
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        بستن تیکت
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
                    <span class="font-medium">{{ Jalali::format($supportTicket->resolved_at) }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Status Control --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                تغییر وضعیت
            </h3>
            <form method="post" action="{{ route('admin.support-tickets.update-status', $supportTicket) }}">
                @csrf
                @method('PATCH')
                <div class="flex gap-2">
                    <select name="status" class="form-select flex-1 text-sm">
                        <option value="open" {{ $supportTicket->status === 'open' ? 'selected' : '' }}>باز</option>
                        <option value="in_progress" {{ $supportTicket->status === 'in_progress' ? 'selected' : '' }}>در حال بررسی</option>
                        <option value="closed" {{ $supportTicket->status === 'closed' ? 'selected' : '' }}>بسته</option>
                    </select>
                    <button type="submit" class="btn btn-primary !py-2 !px-4 !text-sm">ذخیره</button>
                </div>
            </form>
        </div>
                <div class="border-t border-gray-200 bg-white p-3">
                    <form method="post" action="{{ route('admin.support-tickets.replies.store', $supportTicket) }}"
                          @submit="isSending = true; setTimeout(() => { isSending = false; }, 3000);"
                          class="flex items-end gap-2">
                        @csrf
                        <div class="flex-1 relative">
                            <textarea name="message"
                                      x-model="message"
                                      class="form-control w-full !rounded-2xl !border-gray-300 !resize-none !py-3 !px-4 !text-sm focus:!border-green-400 focus:!ring-2 focus:!ring-green-100 transition-all"
                                      rows="2" required maxlength="5000"
                                      placeholder="پاسخ خود را به عنوان پشتیبان بنویسید..."
                                      @keydown.enter.prevent="if(!$event.shiftKey) $el.closest('form').submit()"></textarea>
                            <small class="text-[10px] text-gray-400 mt-1 block mr-2">اینتر = ارسال | Shift+Enter = خط جدید</small>
                        </div>
                        <button type="submit"
                                :disabled="isSending || !message.trim()"
                                class="btn !rounded-full !p-3 !min-w-[48px] !h-[48px] flex items-center justify-center transition-all"
                                :class="isSending || !message.trim() ? '!bg-gray-200 !text-gray-600 !cursor-not-allowed' : '!bg-green-600 !text-white hover:!bg-green-700'">
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
                    <span class="text-sm text-gray-500">این تیکت بسته شده است.</span>
                </div>
            @endif
        </div>
    </div>
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

                <div x-show="showScrollBtn"
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
                                <p class="text-xs font-semibold mb-1 opacity-80">{{ $supportTicket->customer_name ?? 'مشتری' }}</p>
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
                            اولویت بالا
                        </span>
                    @elseif($supportTicket->priority === 'low')
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200">اولویت کم</span>
                    @else
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-600 border border-blue-200">اولویت معمولی</span>
                    @endif
                    @if($sla->hasAnyBreach($supportTicket))
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            SLA نقض شده
                        </span>
                    @endif
                </div>
            </div>
            <div class="mt-3">
                <h2 class="text-lg font-bold text-gray-900">{{ $supportTicket->subject }}</h2>
            </div>
        </div>
