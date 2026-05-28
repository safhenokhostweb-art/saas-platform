@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'تیکت ' . $ticket->ticket_number)

{{-- Phase 10.1 — Module styles migrated to resources/css/app.css (canonical bundle) --}}

@section('content')
{{-- Phase 10.0: Inject canonical design tokens once per page --}}
<x-core-ui::design-tokens />

<div class="max-w-7xl mx-auto" role="main" aria-label="جزئیات تیکت {{ $ticket->ticket_number }}"
     x-data="{
        activeTab: 'chat',
        noteMode: false,
        message: '',
        noteText: '',
        isSending: false,
        showScrollBtn: false,
        watchers: {{ json_encode($watchers ?? []) }},
        newWatcher: '',
        addWatcher() {
            if(this.newWatcher.trim() && !this.watchers.includes(this.newWatcher.trim())) {
                this.watchers.push(this.newWatcher.trim());
                this.newWatcher = '';
            }
        },
        removeWatcher(w) { this.watchers = this.watchers.filter(x => x !== w); },
        scrollDown() {
            let el = this.$refs.chatMessages;
            if(el) el.scrollTop = el.scrollHeight;
            this.showScrollBtn = false;
        },
        handleScroll() {
            let el = this.$refs.chatMessages;
            if(!el) return;
            this.showScrollBtn = el.scrollHeight - el.scrollTop - el.clientHeight > 80;
        },
        init() { this.$nextTick(() => this.scrollDown()); }
     }">

    {{-- ─── Page Header ────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 mb-6 flex-wrap">
        <a href="{{ route('tickets.admin.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/80 hover:bg-white border border-gray-200 transition-all hover:shadow-sm shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت به لیست
        </a>
        <div class="flex-1 min-w-0">
            <h2 class="text-xl font-bold text-gray-900 truncate">{{ $ticket->subject }}</h2>
            <div class="flex items-center gap-3 mt-1 flex-wrap">
                <span class="text-xs text-gray-500 font-mono">{{ $ticket->ticket_number }}</span>
                <span class="text-gray-300">·</span>
                @if($ticket->status === 'open')
                    <span class="tkt-badge bg-yellow-50 text-yellow-700 border-yellow-200"><span class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></span>باز</span>
                @elseif($ticket->status === 'in_progress')
                    <span class="tkt-badge bg-blue-50 text-blue-700 border-blue-200"><span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>در حال بررسی</span>
                @else
                    <span class="tkt-badge bg-green-50 text-green-700 border-green-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>بسته</span>
                @endif
                @if($ticket->priority === 'high')
                    <span class="tkt-badge bg-red-50 text-red-600 border-red-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>اولویت بالا</span>
                @elseif($ticket->priority === 'low')
                    <span class="tkt-badge bg-gray-50 text-gray-500 border-gray-200">اولویت کم</span>
                @else
                    <span class="tkt-badge bg-blue-50 text-blue-600 border-blue-200">اولویت عادی</span>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── Flash Messages ─────────────────────────────────────────── --}}
    @foreach(['success' => 'green', 'sms_success' => 'green', 'sms_error' => 'red', 'error' => 'red'] as $flashKey => $fc)
    @if(session($flashKey))
    <div class="glass-card mb-4 p-4 border-r-4 border-r-{{ $fc }}-400 flex items-center gap-3"
         x-data="{ show: true }" x-show="show" x-transition>
        @if($fc === 'green')
        <svg class="w-5 h-5 text-{{ $fc }}-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @else
        <svg class="w-5 h-5 text-{{ $fc }}-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @endif
        <span class="text-sm text-{{ $fc }}-800 font-medium flex-1">{{ session($flashKey) }}</span>
        <button @click="show=false" class="text-{{ $fc }}-400 hover:text-{{ $fc }}-600 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif
    @endforeach

    @if($errors->any())
    <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-2xl">
        <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- ─── Main Grid ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ═══ LEFT: Chat + Notes Panel ══════════════════════════════ --}}
        <div class="xl:col-span-2 flex flex-col">

            {{-- Tabs --}}
            <div class="flex items-end gap-1 border-b border-gray-200 overflow-x-auto pb-0">
                <button class="tkt-tab" :class="activeTab==='chat' && 'active'" @click="activeTab='chat'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    گفتگو
                    <span class="tkt-tab-badge">{{ $ticket->replies->count() + 1 }}</span>
                </button>
                <button class="tkt-tab" :class="activeTab==='notes' && 'active'" @click="activeTab='notes'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                    یادداشت داخلی
                    @if(isset($internalNotes) && count($internalNotes))
                    <span class="tkt-tab-badge bg-amber-500">{{ count($internalNotes) }}</span>
                    @endif
                </button>
                <button class="tkt-tab" :class="activeTab==='timeline' && 'active'" @click="activeTab='timeline'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    تاریخچه
                </button>
            </div>

            {{-- ── TAB: Chat ──────────────────────────────────────── --}}
            <div x-show="activeTab==='chat'" class="flex-1 flex flex-col" style="min-height:500px">

                {{-- Chat Header --}}
                <div class="bg-white border border-gray-200 border-b-0 p-4 rounded-none">
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                {{ mb_substr($ticket->customer_name ?? $ticket->customer_email ?? 'م', 0, 1) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $ticket->customer_name ?? 'مشتری' }}</p>
                                @if($ticket->customer_email)
                                <p class="text-xs text-gray-400" dir="ltr">{{ $ticket->customer_email }}</p>
                                @endif
                            </div>
                        </div>
                        {{-- Auto-refresh indicator --}}
                        <div class="mr-auto flex items-center gap-1.5 text-xs text-gray-400"
                             x-data="{ fresh: false }"
                             x-init="setInterval(() => { fresh = true; setTimeout(() => fresh = false, 1500) }, 30000)">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                            <span x-show="!fresh">پشتیبانی آنلاین</span>
                            <span x-show="fresh" x-transition class="text-green-600 font-medium">به‌روز شد ✓</span>
                        </div>
                    </div>
                </div>

                {{-- Messages --}}
                <div class="bg-white border border-gray-200 border-t-0 flex-1 flex flex-col">
                    <div x-ref="chatMessages"
                         @scroll="handleScroll"
                         class="flex-1 overflow-y-auto p-4 space-y-1 chat-bg flex flex-col"
                         style="max-height:460px; scroll-behavior:smooth">

                        @php $customerInitial = mb_substr($ticket->customer_name ?? $ticket->customer_email ?? 'م', 0, 1); @endphp

                        {{-- Initial message --}}
                        <div class="flex items-end gap-2 max-w-[85%] sm:max-w-[75%] mb-4" style="align-self:flex-start">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm bg-indigo-500">
                                {{ $customerInitial }}
                            </div>
                            <div>
                                <div class="bubble-customer px-4 py-2.5 shadow-sm">
                                    <p class="text-xs font-bold mb-1 opacity-70">{{ $ticket->customer_name ?? $ticket->customer_email ?? 'مشتری' }}</p>
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $ticket->description }}</p>
                                    @if($ticket->attachments && is_array($ticket->attachments))
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @foreach($ticket->attachments as $file)
                                            @if(isset($file['path']))
                                            <a href="{{ asset('storage/' . $file['path']) }}" target="_blank"
                                               class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                                <span class="truncate max-w-[100px]">{{ $file['name'] ?? 'فایل' }}</span>
                                            </a>
                                            @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                <span class="text-[10px] text-gray-400 mt-1 px-1">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d H:i') }}</span>
                            </div>
                        </div>

                        @php $lastDate = null; @endphp
                        @foreach($ticket->replies as $reply)
                            @php
                                $replyDate = $reply->created_at->toDateString();
                                $isStaff   = $reply->author_type === 'staff';
                                $authorName = $isStaff
                                    ? ($reply->user?->name ?? 'پشتیبان')
                                    : ($reply->customer_name ?? $ticket->customer_name ?? 'مشتری');
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

                            <div class="flex items-end gap-2 max-w-[85%] sm:max-w-[75%] mb-4 {{ $isStaff ? 'flex-row-reverse' : '' }}"
                                 style="align-self: {{ $isStaff ? 'flex-end' : 'flex-start' }}">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm"
                                     style="background-color: {{ $isStaff ? 'var(--ui-success)' : 'var(--ui-indigo)' }}">
                                    {{ mb_substr($authorName, 0, 1) }}
                                </div>
                                <div>
                                    <div class="{{ $isStaff ? 'bubble-staff' : 'bubble-customer' }} px-4 py-2.5 shadow-sm">
                                        <p class="text-xs font-bold mb-1 opacity-70">
                                            {{ $authorName }}
                                            @if($isStaff)
                                                <span class="inline-flex items-center gap-0.5 text-[9px] bg-green-200/60 px-1.5 py-0.5 rounded-full mr-1">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                    پشتیبان
                                                </span>
                                            @endif
                                        </p>
                                        <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $reply->message }}</p>
                                        @if($reply->attachments && is_array($reply->attachments))
                                        <div class="flex flex-wrap gap-1.5 mt-2">
                                            @foreach($reply->attachments as $file)
                                                @if(isset($file['path']))
                                                <a href="{{ asset('storage/' . $file['path']) }}" target="_blank"
                                                   class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                                    <span class="truncate max-w-[100px]">{{ $file['name'] ?? 'فایل' }}</span>
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

                        {{-- Scroll button --}}
                        <div x-show="showScrollBtn" x-transition
                             class="sticky bottom-2 flex justify-center w-full">
                            <button @click="scrollDown"
                                    class="bg-white text-gray-600 rounded-full px-4 py-2 shadow-lg border border-gray-200 hover:bg-gray-50 transition-all text-xs font-medium flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                پیام‌های جدید
                            </button>
                        </div>
                    </div>

                    {{-- Reply form --}}
                    @if($ticket->status !== 'closed')
                    <div class="border-t border-gray-200 bg-white/90 p-3">
                        {{-- Note / Reply toggle --}}
                        <div class="flex items-center gap-2 mb-2.5">
                            <button @click="noteMode=false"
                                    :class="!noteMode ? 'bg-indigo-100 text-indigo-700 border-indigo-300' : 'bg-white text-gray-500 border-gray-200'"
                                    class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                پاسخ به مشتری
                            </button>
                            <button @click="noteMode=true"
                                    :class="noteMode ? 'bg-amber-100 text-amber-700 border-amber-300' : 'bg-white text-gray-500 border-gray-200'"
                                    class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                یادداشت داخلی
                            </button>
                            <span x-show="noteMode" class="text-[10px] text-amber-600 font-medium">فقط کارشناسان می‌بینند</span>
                        </div>

                        <form method="POST"
                              action="{{ route('tickets.admin.replies.store', $ticket->id) }}"
                              @submit="isSending=true; setTimeout(()=>{isSending=false},5000)"
                              enctype="{{ $attachmentsEnabled ? 'multipart/form-data' : 'application/x-www-form-urlencoded' }}">
                            @csrf
                            <input type="hidden" name="is_internal" :value="noteMode ? '1' : '0'">
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <textarea name="message"
                                              x-model="noteMode ? noteText : message"
                                              :class="noteMode ? 'note-area' : ''"
                                              class="form-input w-full rounded-2xl border resize-none py-3 px-4 text-sm transition-all"
                                              style="background:rgba(255,255,255,.85)"
                                              rows="2" required maxlength="5000"
                                              :placeholder="noteMode ? 'یادداشت داخلی (فقط کارشناسان می‌بینند)...' : 'پاسخ خود را بنویسید...'"
                                              @keydown.enter.prevent="if(!$event.shiftKey) $el.closest('form').submit()"></textarea>
                                    <small class="text-[10px] text-gray-400 mt-1 block mr-2">اینتر = ارسال | Shift+Enter = خط جدید</small>
                                    @if($attachmentsEnabled)
                                    <input type="file" name="attachments[]" multiple class="mt-2 text-xs text-gray-500">
                                    @endif
                                </div>
                                <button type="submit"
                                        :disabled="isSending || (!noteMode && !message.trim()) || (noteMode && !noteText.trim())"
                                        class="flex items-center justify-center rounded-full p-3 min-w-[48px] h-[48px] transition-all shrink-0"
                                        :class="(isSending || (!noteMode && !message.trim()) || (noteMode && !noteText.trim())) ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : (noteMode ? 'bg-amber-400 text-white shadow-md hover:bg-amber-500' : 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md hover:shadow-lg')">
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
                        <span class="text-sm text-gray-500">این تیکت بسته شده است.</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── TAB: Internal Notes ─────────────────────────────── --}}
            <div x-show="activeTab==='notes'" class="flex-1" style="min-height:500px">
                <div class="glass-card p-5 h-full flex flex-col gap-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l .8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-800">یادداشت‌های داخلی</h3>
                            <p class="text-xs text-gray-400">فقط کارشناسان پشتیبانی این یادداشت‌ها را می‌بینند</p>
                        </div>
                    </div>
                    @php $internalNotes = $ticket->replies->where('author_type', 'internal'); @endphp
                    @if($internalNotes->isEmpty())
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-400 py-8">
                        <svg class="w-10 h-10 mb-3 opacity-30" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        <p class="text-sm">هیچ یادداشت داخلی ثبت نشده</p>
                        <button @click="activeTab='chat'; noteMode=true"
                                class="mt-3 text-xs text-amber-600 hover:text-amber-700 font-medium underline underline-offset-2">
                            + افزودن یادداشت داخلی
                        </button>
                    </div>
                    @else
                    <div class="flex-1 space-y-3 overflow-y-auto">
                        @foreach($internalNotes as $note)
                        <div class="bubble-internal px-4 py-3">
                            <div class="flex items-center justify-between mb-1.5">
                                <p class="text-xs font-bold text-amber-800">{{ $note->user?->name ?? 'کارشناس' }}</p>
                                <span class="text-[10px] text-amber-600">{{ \App\Helpers\Jalali::formatFa($note->created_at, 'Y/m/d H:i') }}</span>
                            </div>
                            <p class="text-sm leading-relaxed whitespace-pre-wrap text-amber-900">{{ $note->message }}</p>
                        </div>
                        @endforeach
                    </div>
                    <button @click="activeTab='chat'; noteMode=true"
                            class="text-xs text-amber-600 hover:text-amber-700 font-medium text-center underline underline-offset-2">
                        + افزودن یادداشت داخلی
                    </button>
                    @endif
                </div>
            </div>

            {{-- ── TAB: Timeline (core-ui::activity-timeline) ─────── --}}
            <div x-show="activeTab==='timeline'" class="flex-1" style="min-height:500px">
                <div class="glass-card p-5 h-full">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            تاریخچه فعالیت
                        </h3>
                        @if(isset($activities) && $activities->count())
                        <span class="text-xs font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                            {{ $activities->count() }} رویداد
                        </span>
                        @endif
                    </div>

                    @if(isset($activities) && $activities->isNotEmpty())
                        <x-core-ui::activity-timeline :activities="$activities" />
                    @else
                        {{-- Fallback: synthetic timeline from ticket data --}}
                        <div class="space-y-2">
                            <div class="timeline-item">
                                <div class="timeline-dot bg-indigo-500"></div>
                                <div class="pr-3">
                                    <p class="text-xs font-semibold text-gray-700">تیکت ایجاد شد</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d H:i') }}</p>
                                    <p class="text-[10px] text-gray-500 mt-0.5">توسط: {{ $ticket->customer_name ?? $ticket->customer_email ?? 'مشتری' }}</p>
                                </div>
                            </div>
                            @if($ticket->assignee)
                            <div class="timeline-item">
                                <div class="timeline-dot bg-purple-500"></div>
                                <div class="pr-3">
                                    <p class="text-xs font-semibold text-gray-700">تخصیص به کارشناس</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ $ticket->assignee->name }}</p>
                                </div>
                            </div>
                            @endif
                            @foreach($ticket->replies as $reply)
                            <div class="timeline-item">
                                <div class="timeline-dot {{ $reply->is_internal ? 'bg-amber-400' : ($reply->author_type === 'staff' ? 'bg-green-500' : 'bg-blue-400') }}"></div>
                                <div class="pr-3">
                                    <p class="text-xs font-semibold text-gray-700">
                                        @if($reply->is_internal) یادداشت داخلی
                                        @elseif($reply->author_type === 'staff') پاسخ پشتیبانی
                                        @else پاسخ مشتری
                                        @endif
                                    </p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ \App\Helpers\Jalali::formatFa($reply->created_at, 'Y/m/d H:i') }} — {{ $reply->user?->name ?? $reply->customer_name ?? 'کاربر' }}</p>
                                </div>
                            </div>
                            @endforeach
                            @if($ticket->status === 'closed')
                            <div class="timeline-item">
                                <div class="timeline-dot bg-emerald-500"></div>
                                <div class="pr-3">
                                    <p class="text-xs font-semibold text-gray-700">تیکت بسته شد</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ \App\Helpers\Jalali::formatFa($ticket->updated_at, 'Y/m/d H:i') }}</p>
                                </div>
                            </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ═══ RIGHT: Sidebar ═════════════════════════════════════════ --}}
        <div class="space-y-4">

            {{-- ── Ticket Info ─────────────────────────────────────── --}}
            <div class="glass-card sidebar-section">
                <h3 class="sidebar-title">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                    اطلاعات تیکت
                </h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">شماره</dt>
                        <dd class="font-mono font-bold text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">{{ $ticket->ticket_number }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">سایت</dt>
                        <dd class="font-semibold text-xs text-gray-700">{{ $ticket->site?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">مسئول</dt>
                        <dd class="font-semibold text-xs text-gray-700">{{ $ticket->assignee?->name ?? 'تخصیص نیافته' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">ایمیل</dt>
                        <dd class="font-semibold text-xs text-gray-700 truncate" dir="ltr">{{ $ticket->customer_email ?? '—' }}</dd>
                    </div>
                    @if($ticket->category)
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">دسته‌بندی</dt>
                        <dd class="font-semibold text-xs text-gray-700">{{ $ticket->category }}</dd>
                    </div>
                    @endif
                    @if($ticket->tags && is_array($ticket->tags) && count($ticket->tags))
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">برچسب‌ها</dt>
                        <dd class="flex flex-wrap gap-1 justify-end">
                            @foreach($ticket->tags as $tag)
                            <span class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $tag }}</span>
                            @endforeach
                        </dd>
                    </div>
                    @endif
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">تاریخ ایجاد</dt>
                        <dd class="font-semibold text-xs text-gray-700">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d') }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">پیام‌ها</dt>
                        <dd class="font-bold text-xs text-indigo-700">{{ $ticket->replies->count() + 1 }}</dd>
                    </div>
                    @if($slaEnabled && !empty($slaMetrics))
                    <div class="pt-2 border-t border-gray-100">
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-400 text-xs">نرخ نقض SLA</dt>
                            <dd class="font-bold text-xs {{ ($slaMetrics['breach_rate'] ?? 0) > 0 ? 'text-red-500' : 'text-green-600' }}">{{ $slaMetrics['breach_rate'] ?? 0 }}%</dd>
                        </div>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- ── Status Control ──────────────────────────────────── --}}
            <div class="glass-card sidebar-section">
                <h3 class="sidebar-title">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    وضعیت تیکت
                </h3>
                <div class="space-y-2">
                    @if($ticket->status === 'open')
                    <form method="POST" action="{{ route('tickets.admin.status.update', $ticket->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="in_progress">
                        <button class="w-full flex items-center justify-center gap-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs py-2.5 rounded-xl transition font-semibold border border-blue-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/></svg>
                            شروع بررسی
                        </button>
                    </form>
                    @endif
                    @if($ticket->status !== 'closed')
                    <form method="POST" action="{{ route('tickets.admin.status.update', $ticket->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="closed">
                        <button class="w-full flex items-center justify-center gap-2 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs py-2.5 rounded-xl transition font-semibold border border-gray-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            بستن تیکت
                        </button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('tickets.admin.status.update', $ticket->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="open">
                        <button class="w-full flex items-center justify-center gap-2 bg-yellow-50 hover:bg-yellow-100 text-yellow-700 text-xs py-2.5 rounded-xl transition font-semibold border border-yellow-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                            بازگشایی مجدد
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            {{-- ── Priority Control ────────────────────────────────── --}}
            <div class="glass-card sidebar-section">
                <h3 class="sidebar-title">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/></svg>
                    اولویت
                </h3>
                <form method="POST" action="{{ route('tickets.admin.priority.update', $ticket->id) }}">
                    @csrf @method('PATCH')
                    <select name="priority" class="form-input mb-2 w-full text-xs">
                        <option value="high"   @selected(($ticket->priority ?? '') === 'high')>🔴 بالا</option>
                        <option value="normal" @selected(($ticket->priority ?? 'normal') === 'normal')>🔵 عادی</option>
                        <option value="low"    @selected(($ticket->priority ?? '') === 'low')>⚪ کم</option>
                    </select>
                    <button class="w-full text-xs py-2 rounded-xl bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 font-semibold transition">
                        ثبت اولویت
                    </button>
                </form>
            </div>

            {{-- ── Agent Assignment ────────────────────────────────── --}}
            <div class="glass-card sidebar-section">
                <h3 class="sidebar-title">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    تخصیص کارشناس
                </h3>
                @if($ticket->assignee)
                <div class="flex items-center gap-2 mb-3 p-2 bg-indigo-50 rounded-xl border border-indigo-100">
                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                        {{ mb_substr($ticket->assignee->name, 0, 1) }}
                    </div>
                    <span class="text-xs font-semibold text-indigo-700 flex-1">{{ $ticket->assignee->name }}</span>
                    <span class="text-[10px] text-indigo-400">مسئول فعلی</span>
                </div>
                @endif
                <form method="POST" action="{{ route('tickets.admin.assign', $ticket->id) }}">
                    @csrf @method('PATCH')
                    <select name="assigned_to" class="form-input mb-2 w-full text-xs">
                        <option value="">بدون مسئول</option>
                        @foreach($adminUsers as $admin)
                            <option value="{{ $admin->id }}" {{ $ticket->assigned_to == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="w-full text-xs py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-semibold transition">
                        ثبت تخصیص
                    </button>
                </form>
            </div>

            {{-- ── Site Assignment ─────────────────────────────────── --}}
            @if($sites->count())
            <div class="glass-card sidebar-section">
                <h3 class="sidebar-title">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                    تخصیص سایت
                </h3>
                <form method="POST" action="{{ route('tickets.admin.site.update', $ticket->id) }}">
                    @csrf @method('PATCH')
                    <select name="site_id" class="form-input mb-2 w-full text-xs">
                        <option value="">بدون سایت</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}" {{ $ticket->site_id == $site->id ? 'selected' : '' }}>
                                {{ $site->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="w-full text-xs py-2 rounded-xl bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 font-semibold transition">
                        ثبت سایت
                    </button>
                </form>
            </div>
            @endif

            {{-- ── Watchers (CC) ───────────────────────────────────── --}}
            <div class="glass-card sidebar-section">
                <h3 class="sidebar-title">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    ناظران (CC)
                </h3>
                <div class="flex flex-wrap gap-1.5 mb-3 min-h-[28px]">
                    <template x-for="w in watchers" :key="w">
                        <span class="watcher-chip">
                            <span x-text="w"></span>
                            <button @click="removeWatcher(w)" class="opacity-60 hover:opacity-100 transition-opacity text-red-400">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </span>
                    </template>
                    <span x-show="watchers.length===0" class="text-xs text-gray-400">هیچ ناظری اضافه نشده</span>
                </div>
                <div class="flex gap-2">
                    <input type="text" x-model="newWatcher" @keydown.enter.prevent="addWatcher"
                           placeholder="ایمیل ناظر..."
                           class="form-input flex-1 text-xs" dir="ltr">
                    <button @click="addWatcher"
                            class="px-3 py-2 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 text-xs font-semibold transition">
                        +
                    </button>
                </div>
            </div>

            {{-- ── SMS ────────────────────────────────────────────── --}}
            @if($smsEnabled)
            <div class="glass-card sidebar-section"
                 x-data="{
                    open: false,
                    phone: '',
                    smsMessage: '',
                    loading: false,
                    templates: [],
                    async fetchTemplates() {
                        try {
                            const r = await fetch('{{ route('tickets.admin.sms.templates', $ticket->id) }}');
                            const d = await r.json();
                            this.templates = d.templates || [];
                            if(d.customer_phone) this.phone = d.customer_phone;
                        } catch(e) {}
                    },
                    useTemplate(tpl) { this.smsMessage = tpl.message; }
                 }"
                 @open-sms.window="open=true; fetchTemplates()">

                <button @click="open=true; fetchTemplates()"
                        class="w-full flex items-center justify-center gap-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs py-2.5 rounded-xl transition font-semibold border border-purple-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    ارسال پیامک
                </button>

                {{-- SMS Modal --}}
                <div x-show="open" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="open=false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-purple-100 flex items-center justify-center text-purple-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                </div>
                                <h4 class="font-bold text-gray-900">ارسال پیامک</h4>
                            </div>
                            <button @click="open=false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div x-show="templates.length" class="mb-3">
                            <p class="text-xs text-gray-500 mb-2 font-medium">قالب‌های آماده:</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="tpl in templates" :key="tpl.label">
                                    <button @click="useTemplate(tpl)"
                                            class="text-xs px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg border border-indigo-200 transition font-medium"
                                            x-text="tpl.label"></button>
                                </template>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('tickets.admin.sms.send', $ticket->id) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">شماره موبایل</label>
                                <input type="text" name="phone" x-model="phone" required maxlength="20"
                                       placeholder="09xxxxxxxxx"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition" dir="ltr">
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">متن پیامک</label>
                                <textarea name="message" x-model="smsMessage" required maxlength="500" rows="4"
                                          placeholder="متن پیامک..."
                                          class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm resize-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition"></textarea>
                                <p class="text-[10px] text-gray-400 mt-1" x-text="`${smsMessage.length}/500 کاراکتر`"></p>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                        :disabled="loading || !phone.trim() || !smsMessage.trim()"
                                        class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white transition"
                                        :class="loading || !phone.trim() || !smsMessage.trim() ? 'bg-gray-300 cursor-not-allowed' : 'bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 shadow-md'">
                                    ارسال
                                </button>
                                <button type="button" @click="open=false"
                                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition">
                                    انصراف
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            {{-- ── Back ────────────────────────────────────────────── --}}
            <a href="{{ route('tickets.admin.index') }}" class="btn-ghost w-full !justify-center !text-xs !py-2.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                بازگشت به لیست
            </a>

        </div>
    </div>

</div>
@endsection
