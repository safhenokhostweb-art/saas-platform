@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', $ticket->subject)

@section('content')
<x-core-ui::design-tokens />

<div class="max-w-7xl mx-auto" role="main"
     data-poll-url="{{ route('tickets.site.poll', $ticket->id) }}"
     data-poll-last-id="{{ $ticket->publicReplies->last()?->id ?? 0 }}"
     x-data="{
        activeTab: 'chat',
        message: '',
        isSending: false,
        showScrollBtn: false,
        pollLastId: 0,
        pollTimer: null,
        showToast: false,
        toastText: '',
        notifPerm: ('Notification' in window) ? Notification.permission : 'unsupported',
        _polling: false,
        scrollDown() {
            let el = this.$refs.chatMessages;
            if (el) el.scrollTop = el.scrollHeight;
            this.showScrollBtn = false;
        },
        handleScroll() {
            let el = this.$refs.chatMessages;
            if (!el) return;
            this.showScrollBtn = el.scrollHeight - el.scrollTop - el.clientHeight > 80;
        },
        enableNotifications() {
            if ('Notification' in window) {
                Notification.requestPermission().then(p => { this.notifPerm = p; });
            }
        },
        init() {
            this.$nextTick(() => this.scrollDown());
            this.pollLastId = parseInt(this.$el.dataset.pollLastId || '0', 10);
            this.pollTimer = setInterval(() => this.doPoll(), 4000);
            window.addEventListener('beforeunload', () => clearInterval(this.pollTimer));
        },
        async doPoll() {
            if (this._polling) return;
            this._polling = true;
            const url = this.$el.dataset.pollUrl;
            if (!url) { this._polling = false; return; }
            try {
                const res = await fetch(url + '?last_id=' + this.pollLastId, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data.replies && data.replies.length > 0) {
                    data.replies.forEach(r => this.appendReply(r));
                }
            } catch(e) {}
            finally { this._polling = false; }
        },
        async sendReply(form) {
            if (this.isSending) return;
            if (!this.message.trim()) return;
            if (this.notifPerm === 'default') { this.enableNotifications(); }
            this.isSending = true;
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body: new FormData(form)
                });
                if (!res.ok) { const e = await res.json().catch(()=>({})); alert(e.message || 'خطا در ارسال'); return; }
                const data = await res.json();
                if (data.reply) {
                    this.appendReply(Object.assign({}, data.reply, {author_name: 'شما'}));
                }
                this.message = '';
                const fi = form.querySelector('input[type=file]'); if (fi) fi.value = '';
            } catch(e) { console.error(e); }
            finally { this.isSending = false; }
        },
        appendReply(r) {
            if (r.id && r.id <= this.pollLastId) return;
            if (r.id && r.id > this.pollLastId) this.pollLastId = r.id;
            const container = this.$refs.chatMessages;
            if (!container) return;
            const isStaff = r.author_type === 'staff';
            const name = (r.author_name || (isStaff ? 'پشتیبان' : 'شما')).replace(/</g,'&lt;').replace(/>/g,'&gt;');
            const initial = name.charAt(0);
            const msg = (r.message || '').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
            const d = new Date(r.created_at);
            const time = d.toLocaleTimeString('fa-IR', {hour:'2-digit', minute:'2-digit'});
            const bubbleCls = isStaff ? 'bubble-staff' : 'bubble-customer';
            const color = isStaff ? 'var(--ui-success)' : 'var(--ui-indigo)';
            let attHtml = '';
            if (r.attachments && r.attachments.length > 0) {
                attHtml = '<div class="flex flex-wrap gap-1.5 mt-2">' + r.attachments.map(att => {
                    const aUrl = att.url || '#';
                    const aName = (att.name || 'فایل').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    return `<a href="${aUrl}" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">${aName}</a>`;
                }).join('') + '</div>';
            }
            const wrapper = document.createElement('div');
            wrapper.className = 'flex items-end gap-2 max-w-[85%] sm:max-w-[75%] mb-4' + (isStaff ? ' flex-row-reverse' : '');
            wrapper.style.cssText = 'align-self:' + (isStaff ? 'flex-end' : 'flex-start') + ';opacity:0;transform:translateY(8px)';
            wrapper.innerHTML = `<div class='w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm' style='background-color:${color}'>${initial}</div><div><div class='${bubbleCls} px-4 py-2.5 shadow-sm'><p class='text-xs font-bold mb-1 opacity-70'>${name}${isStaff ? ' <span class=\'inline-flex items-center gap-0.5 text-[9px] bg-green-200/60 px-1.5 py-0.5 rounded-full mr-1\'>پشتیبان</span>' : ''}</p><p class='text-sm leading-relaxed whitespace-pre-wrap'>${msg}</p>${attHtml}</div><span class='text-[10px] text-gray-400 mt-1 px-1'>${time}</span></div>`;
            container.appendChild(wrapper);
            requestAnimationFrame(() => {
                wrapper.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                wrapper.style.opacity = '1';
                wrapper.style.transform = 'translateY(0)';
            });
            container.scrollTop = container.scrollHeight;
            if (r.author_type === 'staff') { this.notify(name, r.message); }
        },
        playSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const o = ctx.createOscillator(); const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'sine'; o.frequency.value = 520;
                g.gain.setValueAtTime(0, ctx.currentTime);
                g.gain.linearRampToValueAtTime(0.3, ctx.currentTime + 0.05);
                g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
                o.start(ctx.currentTime); o.stop(ctx.currentTime + 0.5);
            } catch(e) {}
        },
        notify(from, body) {
            this.playSound();
            this.toastText = 'پاسخ جدید از پشتیبانی';
            this.showToast = true;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.showToast = false; }, 6000);
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('پشتیبانی پاسخ داد', { body: (body || '').substring(0, 120), icon: '/favicon.ico', dir: 'rtl', tag: 'ticket-reply' });
            }
        }
     }">

    {{-- Real-time toast notification --}}
    <div x-show="showToast" x-transition
         class="fixed top-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-green-600 text-white px-5 py-3 rounded-2xl shadow-xl text-sm font-medium"
         style="display:none">
        <svg class="w-4 h-4 shrink-0 animate-bounce" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span x-text="toastText"></span>
        <button @click="showToast=false" class="mr-2 opacity-70 hover:opacity-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- ─── Page Header ────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 mb-6 flex-wrap">
        <a href="{{ route('tickets.site.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/80 hover:bg-white border border-gray-200 transition-all hover:shadow-sm shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت به لیست
        </a>
        <div class="flex-1 min-w-0">
            <h2 class="text-xl font-bold text-gray-900 truncate">{{ $ticket->subject }}</h2>
            <div class="flex items-center gap-3 mt-1 flex-wrap">
                <span class="text-xs text-gray-500 font-mono">{{ $ticket->ticket_number }}</span>
                <span class="text-gray-300">·</span>
                {{-- Status --}}
                @if($ticket->status === 'open')
                    <span class="tkt-badge bg-yellow-50 text-yellow-700 border-yellow-200"><span class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></span>باز</span>
                @elseif($ticket->status === 'in_progress')
                    <span class="tkt-badge bg-blue-50 text-blue-700 border-blue-200"><span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>در حال بررسی</span>
                @else
                    <span class="tkt-badge bg-green-50 text-green-700 border-green-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>بسته</span>
                @endif
                {{-- Priority --}}
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

    {{-- ─── Flash Messages ─────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="glass-card mb-4 p-4 border-r-4 border-r-green-400 flex items-center gap-3"
         x-data="{ show: true }" x-show="show" x-transition>
        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm text-green-800 font-medium flex-1">{{ session('success') }}</span>
        <button @click="show=false" class="text-green-400 hover:text-green-600 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    @if($errors->any())
    <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-2xl">
        <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- ─── Main Grid ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ═══ LEFT: Chat + Timeline Tabs ════════════════════════════════ --}}
        <div class="xl:col-span-2 flex flex-col">

            {{-- Tabs --}}
            <div class="flex items-end gap-1 border-b border-gray-200 overflow-x-auto pb-0">
                <button class="tkt-tab" :class="activeTab==='chat' && 'active'" @click="activeTab='chat'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    گفتگو
                    <span class="tkt-tab-badge">{{ \App\Helpers\NumberHelper::formatFa($ticket->publicReplies->count() + 1) }}</span>
                </button>
                <button class="tkt-tab" :class="activeTab==='timeline' && 'active'" @click="activeTab='timeline'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    تاریخچه
                    @if($activities->count())
                    <span class="tkt-tab-badge bg-indigo-500">{{ \App\Helpers\NumberHelper::formatFa($activities->count()) }}</span>
                    @endif
                </button>
            </div>

            {{-- ── TAB: Chat ───────────────────────────────────────────── --}}
            <div x-show="activeTab==='chat'" class="flex-1 flex flex-col" style="min-height:600px">

                {{-- Chat Header --}}
                <div class="bg-white border border-gray-200 border-b-0 p-4">
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-linear-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                {{ mb_substr(auth()->user()?->name ?? 'م', 0, 1) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ auth()->user()?->name ?? $ticket->customer_name ?? 'شما' }}</p>
                                @if(auth()->user()?->email)
                                <p class="text-xs text-gray-400" dir="ltr">{{ auth()->user()->email }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="mr-auto flex items-center gap-2 flex-wrap">
                            <div class="flex items-center gap-1.5 text-xs text-gray-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                                <span>به‌روزرسانی خودکار</span>
                            </div>
                            <button x-show="notifPerm === 'default'" @click="enableNotifications()"
                                    class="text-[10px] text-indigo-600 hover:text-indigo-800 border border-indigo-200 rounded-lg px-2 py-1 transition-all">
                                🔔 فعال‌سازی اعلان
                            </button>
                            <span x-show="notifPerm === 'granted'" class="text-[10px] text-green-600">🔔 اعلان فعال</span>
                        </div>
                    </div>
                </div>

                {{-- Messages --}}
                <div class="bg-white border border-gray-200 border-t-0 flex-1 flex flex-col">
                    <div x-ref="chatMessages"
                         @scroll="handleScroll"
                         class="flex-1 overflow-y-auto p-4 space-y-1 chat-bg flex flex-col"
                         style="max-height:520px; scroll-behavior:smooth">

                        @php $userInitial = mb_substr(auth()->user()?->name ?? $ticket->customer_name ?? 'م', 0, 1); @endphp

                        {{-- Initial message (customer bubble) --}}
                        <div class="flex items-end gap-2 max-w-[85%] sm:max-w-[75%] mb-4" style="align-self:flex-start">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm bg-indigo-500">
                                {{ $userInitial }}
                            </div>
                            <div>
                                <div class="bubble-customer px-4 py-2.5 shadow-sm">
                                    <p class="text-xs font-bold mb-1 opacity-70">{{ $ticket->customer_name ?? auth()->user()?->name ?? 'شما' }}</p>
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $ticket->description }}</p>
                                    @if($ticket->attachments && is_array($ticket->attachments))
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @foreach($ticket->attachments as $file)
                                            @if(isset($file['path']) || isset($file['url']))
                                            <a href="{{ $file['url'] ?? asset('storage/' . $file['path']) }}" target="_blank"
                                               class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                                <span class="truncate max-w-25">{{ $file['name'] ?? 'فایل' }}</span>
                                            </a>
                                            @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                <span class="text-[10px] text-gray-400 mt-1 px-1">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d H:i') }}</span>
                            </div>
                        </div>

                        {{-- Replies (public only — internal notes excluded) --}}
                        @php $lastDate = null; @endphp
                        @foreach($ticket->publicReplies as $reply)
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
                                                @if(isset($file['path']) || isset($file['url']))
                                                <a href="{{ $file['url'] ?? asset('storage/' . $file['path']) }}" target="_blank"
                                                   class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                                    <span class="truncate max-w-25">{{ $file['name'] ?? 'فایل' }}</span>
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
                        <div x-show="showScrollBtn" x-transition class="sticky bottom-2 flex justify-center w-full">
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
                        <form method="POST"
                              action="{{ route('tickets.site.replies.store', $ticket->id) }}"
                              @submit.prevent="sendReply($event.target)"
                              enctype="{{ $attachmentsEnabled ? 'multipart/form-data' : 'application/x-www-form-urlencoded' }}">
                            @csrf
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <textarea name="message"
                                              x-model="message"
                                              class="form-input w-full rounded-2xl border resize-none py-3 px-4 text-sm transition-all"
                                              style="background:rgba(255,255,255,.85)"
                                              rows="2" required maxlength="5000"
                                              placeholder="پیام خود را بنویسید..."
                                              @keydown.enter.prevent="if(!$event.shiftKey) sendReply($el.closest('form'))"></textarea>
                                    <small class="text-[10px] text-gray-400 mt-1 block mr-2">اینتر = ارسال | Shift+Enter = خط جدید</small>
                                    @if($attachmentsEnabled)
                                    <input type="file" name="attachments[]" multiple class="mt-2 text-xs text-gray-500">
                                    @endif
                                </div>
                                <button type="submit"
                                        :disabled="isSending || !message.trim()"
                                        class="flex items-center justify-center rounded-full p-3 min-w-12 h-12 transition-all shrink-0"
                                        :class="(isSending || !message.trim()) ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-linear-to-r from-indigo-600 to-purple-600 text-white shadow-md hover:shadow-lg'">
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
                        <a href="{{ route('tickets.site.create') }}" class="text-indigo-600 text-sm mr-2 underline underline-offset-2">ثبت تیکت جدید</a>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── TAB: Timeline ───────────────────────────────────────── --}}
            <div x-show="activeTab==='timeline'" class="flex-1" style="min-height:500px">
                <div class="glass-card p-5 h-full">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            تاریخچه تیکت
                        </h3>
                        @if($activities->count())
                        <span class="text-xs font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                            {{ \App\Helpers\NumberHelper::formatFa($activities->count()) }} رویداد
                        </span>
                        @endif
                    </div>

                    @if($activities->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($activities as $activity)
                        @php
                            $color = $activity->iconColor();
                            $dotColors = [
                                'indigo' => 'bg-indigo-500',
                                'blue'   => 'bg-blue-500',
                                'green'  => 'bg-green-500',
                                'orange' => 'bg-orange-500',
                                'emerald'=> 'bg-emerald-500',
                                'yellow' => 'bg-yellow-500',
                                'gray'   => 'bg-gray-400',
                            ];
                            $dotClass = $dotColors[$color] ?? 'bg-gray-400';
                        @endphp
                        <div class="timeline-item">
                            <div class="timeline-dot {{ $dotClass }}"></div>
                            <div class="pr-3">
                                <p class="text-xs font-semibold text-gray-700">{{ $activity->label() }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ \App\Helpers\Jalali::formatFa($activity->created_at, 'Y/m/d H:i') }}</p>
                                @if($activity->old_value && $activity->new_value)
                                <p class="text-[10px] text-gray-500 mt-0.5">
                                    {{ $activity->old_value }} ← {{ $activity->new_value }}
                                </p>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    {{-- Fallback timeline from ticket fields --}}
                    <div class="space-y-2">
                        <div class="timeline-item">
                            <div class="timeline-dot bg-indigo-500"></div>
                            <div class="pr-3">
                                <p class="text-xs font-semibold text-gray-700">تیکت ایجاد شد</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d H:i') }}</p>
                            </div>
                        </div>
                        @foreach($ticket->publicReplies as $reply)
                        <div class="timeline-item">
                            <div class="timeline-dot {{ $reply->author_type === 'staff' ? 'bg-green-500' : 'bg-blue-400' }}"></div>
                            <div class="pr-3">
                                <p class="text-xs font-semibold text-gray-700">{{ $reply->author_type === 'staff' ? 'پاسخ پشتیبانی' : 'پاسخ شما' }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ \App\Helpers\Jalali::formatFa($reply->created_at, 'Y/m/d H:i') }}</p>
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

        {{-- ═══ RIGHT: Info Sidebar (read-only) ═══════════════════════════ --}}
        <div class="space-y-4">

            {{-- ── Ticket Info ─────────────────────────────────────────── --}}
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
                        <dt class="text-gray-400 text-xs">وضعیت</dt>
                        <dd class="font-semibold text-xs">
                            @if($ticket->status === 'open')
                                <span class="tkt-badge bg-yellow-50 text-yellow-700 border-yellow-200"><span class="pdot bg-yellow-500"></span>باز</span>
                            @elseif($ticket->status === 'in_progress')
                                <span class="tkt-badge bg-blue-50 text-blue-700 border-blue-200"><span class="pdot bg-blue-500"></span>در حال بررسی</span>
                            @else
                                <span class="tkt-badge bg-green-50 text-green-700 border-green-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>بسته</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">اولویت</dt>
                        <dd class="font-semibold text-xs">
                            @if($ticket->priority === 'high')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-red-50 text-red-600 border border-red-200"><span class="pdot bg-red-500"></span>بالا</span>
                            @elseif($ticket->priority === 'low')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-gray-50 text-gray-500 border border-gray-200"><span class="pdot bg-gray-400"></span>کم</span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-600 border border-blue-200"><span class="pdot bg-blue-500"></span>عادی</span>
                            @endif
                        </dd>
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
                        <dt class="text-gray-400 text-xs">تاریخ ثبت</dt>
                        <dd class="font-semibold text-xs text-gray-700">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d') }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">تعداد پیام‌ها</dt>
                        <dd class="font-bold text-xs text-indigo-700">{{ \App\Helpers\NumberHelper::formatFa($ticket->publicReplies->count() + 1) }}</dd>
                    </div>
                    @if($ticket->assignee)
                    <div class="pt-2 border-t border-gray-100 flex justify-between gap-2">
                        <dt class="text-gray-400 text-xs">کارشناس</dt>
                        <dd class="flex items-center gap-1.5">
                            <div class="w-5 h-5 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-[9px] font-bold shrink-0">
                                {{ mb_substr($ticket->assignee->name, 0, 1) }}
                            </div>
                            <span class="text-xs font-semibold text-gray-700">{{ $ticket->assignee->name }}</span>
                        </dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- ── Quick Actions ────────────────────────────────────────── --}}
            <div class="glass-card sidebar-section">
                <h3 class="sidebar-title">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    عملیات
                </h3>
                <div class="space-y-2">
                    @if($ticket->status !== 'closed')
                    <button @click="activeTab='chat'; $nextTick(() => { let ta = document.querySelector('textarea[name=message]'); if(ta) ta.focus(); })"
                            class="w-full flex items-center justify-center gap-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs py-2.5 rounded-xl transition font-semibold border border-indigo-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        ارسال پیام
                    </button>
                    @endif
                    <a href="{{ route('tickets.site.create') }}"
                       class="w-full flex items-center justify-center gap-2 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs py-2.5 rounded-xl transition font-semibold border border-gray-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        تیکت جدید
                    </a>
                    <a href="{{ route('tickets.site.index') }}"
                       class="w-full flex items-center justify-center gap-2 text-gray-500 text-xs py-2 rounded-xl hover:text-indigo-600 transition font-medium">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        بازگشت به لیست
                    </a>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
