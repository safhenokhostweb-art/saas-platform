@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', $ticket->subject)

@push('head')
<style>
.glass-card-strong{background:rgba(255,255,255,0.82);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.3);border-radius:1.25rem;box-shadow:0 8px 32px rgba(15,23,42,0.06)}
.ticket-status-badge{display:inline-flex;align-items:center;gap:0.4rem;padding:0.3rem 0.85rem;border-radius:9999px;font-size:0.75rem;font-weight:600;white-space:nowrap;border:1px solid transparent}
</style>
@endpush

@section('content')
@tenant
<div class="max-w-3xl mx-auto p-6">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('tickets.site.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/70 hover:bg-white border border-gray-200 transition-all hover:shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت
        </a>
        <div>
            <h2 class="text-lg font-bold text-gray-900">{{ $ticket->subject }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $ticket->ticket_number }}</p>
        </div>
        <div class="mr-auto flex gap-2">
            @if($ticket->status === 'open')
                <span class="ticket-status-badge bg-yellow-50 text-yellow-700 border-yellow-200">
                    <span class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></span>باز
                </span>
            @elseif($ticket->status === 'in_progress')
                <span class="ticket-status-badge bg-blue-50 text-blue-700 border-blue-200">
                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>در جریان
                </span>
            @else
                <span class="ticket-status-badge bg-gray-50 text-gray-500 border-gray-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>بسته
                </span>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="glass-card-strong mb-6 p-4 border-r-4 border-r-green-400 flex items-center gap-3 rounded-2xl"
         x-data="{ show: true }" x-show="show" x-transition>
        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm text-green-800 font-medium">{{ session('success') }}</span>
        <button @click="show = false" class="mr-auto text-green-400 hover:text-green-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    {{-- Chat UI --}}
    <div class="bg-white rounded-t-2xl shadow-sm border border-gray-200 border-b-0 p-4 border-b border-gray-100">
        <div class="flex items-center gap-2 text-sm text-gray-600">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            {{ $ticket->replies->count() + 1 }} پیام
        </div>
    </div>

    <div class="bg-white shadow-sm border border-gray-200 border-t-0"
         x-data="supportChat()"
         style="min-height: 380px; display: flex; flex-direction: column;">

        <div x-ref="chatMessages"
             @scroll="handleScroll"
             class="flex-1 overflow-y-auto p-4 space-y-1"
             style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); max-height: 450px; scroll-behavior: smooth;">

            {{-- پیام اولیه --}}
            @php $userInitial = mb_substr(auth()->user()?->name ?? 'م', 0, 1); @endphp
            <div class="flex items-end gap-2 max-w-[85%] mb-4" style="align-self: flex-start;">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm" style="background-color: #6366f1;">
                    {{ $userInitial }}
                </div>
                <div>
                    <div class="rounded-2xl rounded-br-sm px-4 py-2.5 shadow-sm" style="background-color: #e3f2fd; color: #1a1a2e;">
                        <p class="text-xs font-semibold mb-1 opacity-80">{{ $ticket->customer_name ?? auth()->user()?->name ?? 'شما' }}</p>
                        <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $ticket->description }}</p>
                        @if($ticket->attachments && is_array($ticket->attachments))
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            @foreach($ticket->attachments as $file)
                                @if(isset($file['path']))
                                <a href="{{ asset('storage/' . $file['path']) }}" target="_blank"
                                   class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                                    📎 <span class="truncate max-w-[100px]">{{ $file['name'] ?? 'فایل' }}</span>
                                </a>
                                @endif
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 px-1">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d H:i') }}</span>
                </div>
            </div>

            {{-- پاسخ‌ها --}}
            @php $lastDate = null; @endphp
            @foreach($ticket->replies as $reply)
                @php
                    $replyDate  = $reply->created_at->toDateString();
                    $isStaff    = $reply->author_type === 'staff';
                    $authorName = $isStaff
                        ? ($reply->user?->name ?? 'پشتیبان')
                        : ($reply->customer_name ?? auth()->user()?->name ?? 'شما');
                @endphp

                @if($replyDate !== $lastDate)
                    @php $lastDate = $replyDate; @endphp
                    <div class="flex justify-center my-3 w-full">
                        <span class="text-[10px] text-gray-400 bg-white/80 px-3 py-1 rounded-full shadow-sm">
                            @if($reply->created_at->isToday()) امروز
                            @elseif($reply->created_at->isYesterday()) دیروز
                            @else {{ \App\Helpers\Jalali::formatFa($reply->created_at, 'Y/m/d') }}
                            @endif
                        </span>
                    </div>
                @endif

                <div class="flex items-end gap-2 max-w-[85%] mb-4 {{ $isStaff ? 'flex-row-reverse' : '' }}"
                     style="align-self: {{ $isStaff ? 'flex-end' : 'flex-start' }};">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm"
                         style="background-color: {{ $isStaff ? '#10b981' : '#6366f1' }};">
                        {{ mb_substr($authorName, 0, 1) }}
                    </div>
                    <div>
                        <div class="rounded-2xl px-4 py-2.5 shadow-sm {{ $isStaff ? 'rounded-bl-sm' : 'rounded-br-sm' }}"
                             style="background-color: {{ $isStaff ? '#dcf8c6' : '#e3f2fd' }}; color: #1a1a2e;">
                            <p class="text-xs font-semibold mb-1 opacity-80">
                                {{ $authorName }}
                                @if($isStaff)
                                    <span class="inline-flex items-center gap-0.5 text-[9px] bg-green-200/50 px-1.5 py-0.5 rounded-full mr-1">پشتیبان</span>
                                @endif
                            </p>
                            <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $reply->message }}</p>
                            @if($reply->attachments && is_array($reply->attachments))
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach($reply->attachments as $file)
                                    @if(isset($file['path']))
                                    <a href="{{ asset('storage/' . $file['path']) }}" target="_blank"
                                       class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                                        📎 <span class="truncate max-w-[100px]">{{ $file['name'] ?? 'فایل' }}</span>
                                    </a>
                                    @endif
                                @endforeach
                            </div>
                            @endif
                        </div>
                        <span class="text-[10px] text-gray-400 mt-1 px-1 {{ $isStaff ? 'text-left block' : '' }}">
                            {{ \App\Helpers\Jalali::formatFa($reply->created_at, 'H:i') }}
                        </span>
                    </div>
                </div>
            @endforeach

            {{-- دکمه اسکرول --}}
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

        {{-- فرم پاسخ --}}
        @if($ticket->status !== 'closed')
        <div class="border-t border-gray-200 bg-white/80 p-3">
            <form method="POST" action="{{ route('tickets.site.replies.store', $ticket->id) }}"
                  @submit="isSending = true; setTimeout(() => { isSending = false }, 4000)">
                @csrf
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <textarea name="message"
                                  x-model="message"
                                  class="w-full rounded-2xl border border-gray-300 resize-none py-3 px-4 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"
                                  rows="2" required maxlength="5000"
                                  placeholder="پیام خود را بنویسید..."
                                  style="background:rgba(255,255,255,0.8)"
                                  @keydown.enter.prevent="if(!$event.shiftKey) $el.closest('form').submit()"></textarea>
                        <small class="text-[10px] text-gray-400 mt-1 block mr-2">اینتر = ارسال | Shift+Enter = خط جدید</small>
                    </div>
                    <button type="submit"
                            :disabled="isSending || !message.trim()"
                            class="flex items-center justify-center rounded-full p-3 min-w-[48px] h-[48px] transition-all"
                            :class="isSending || !message.trim() ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md hover:shadow-lg'">
                        <template x-if="!isSending">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </template>
                        <template x-if="isSending">
                            <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </template>
                    </button>
                </div>
            </form>
        </div>
        @else
        <div class="border-t border-gray-200 bg-gray-50/80 p-4 text-center">
            <span class="text-sm text-gray-500">این تیکت بسته شده است. برای ادامه، یک تیکت جدید ثبت کنید.</span>
        </div>
        @endif
    </div>

</div>
@endtenant
@endsection
