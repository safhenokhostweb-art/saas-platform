@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'تیکت ' . $ticket->ticket_number)

{{-- Phase 10.1 — Module styles migrated to resources/css/app.css (canonical bundle) --}}

@section('content')
{{-- Phase 10.0: Inject canonical design tokens once per page --}}
<x-core-ui::design-tokens />

{{--
    Alpine.data() pattern: all JS with < > / regex chars lives in a <script> block,
    never inside an HTML attribute. This prevents the HTML parser from breaking the JS.
--}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('ticketChat', () => ({
        activeTab: 'chat',
        noteMode: false,
        message: '',
        noteText: '',
        isSending: false,
        showScrollBtn: false,
        watchers: @json($watchers ?? []),
        newWatcher: '',
        pollLastId: 0,
        pollTimer: null,
        showToast: false,
        toastText: '',
        notifPerm: ('Notification' in window) ? Notification.permission : 'unsupported',
        _polling: false,
        _toastTimer: null,
        convSearch: '',
        selectedFiles: [],
        _rawFiles: [],

        get imgFiles() { return this.selectedFiles.filter(f => f.isImage); },
        get docFiles() { return this.selectedFiles.filter(f => !f.isImage); },
        get mosaicCls() { const n=this.imgFiles.length; return n===1?'n1':n===2?'n2':n===3?'n3':'n4'; },

        handleFileChange(e) {
            const files = Array.from(e.target.files || []);
            this._rawFiles = files;
            this.selectedFiles = files.map((f,i) => ({
                _pos: i,
                name: f.name,
                size: f.size>1048576 ? (f.size/1048576).toFixed(1)+' MB' : Math.round(f.size/1024)+' KB',
                isImage: f.type.startsWith('image/'),
                preview: null,
            }));
            files.forEach((f,i) => {
                if (f.type.startsWith('image/')) {
                    const r = new FileReader();
                    r.onload = ev => { const item=this.selectedFiles.find(x=>x._pos===i); if(item) item.preview=ev.target.result; };
                    r.readAsDataURL(f);
                }
            });
        },
        removeFile(pos) {
            this.selectedFiles = this.selectedFiles.filter(f => f._pos !== pos);
            const inp = this.$refs.mainFileInput;
            if (inp) {
                const dt = new DataTransfer();
                this.selectedFiles.forEach(f => { if (this._rawFiles[f._pos]) dt.items.add(this._rawFiles[f._pos]); });
                inp.files = dt.files;
            }
        },

        addWatcher() {
            const w = this.newWatcher.trim();
            if (w && !this.watchers.includes(w)) {
                this.watchers.push(w);
                this.newWatcher = '';
            }
        },
        removeWatcher(w) { this.watchers = this.watchers.filter(x => x !== w); },
        scrollDown() {
            const el = this.$refs.chatMessages;
            if (el) el.scrollTop = el.scrollHeight;
            this.showScrollBtn = false;
        },
        handleScroll() {
            const el = this.$refs.chatMessages;
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
            const text = this.noteMode ? this.noteText.trim() : this.message.trim();
            if (!text) return;
            if (this.notifPerm === 'default') this.enableNotifications();
            this.isSending = true;
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body: new FormData(form)
                });
                if (!res.ok) { const e = await res.json().catch(() => ({})); alert(e.message || 'خطا در ارسال'); return; }
                const data = await res.json();
                if (data.reply) this.appendReply(data.reply);
                if (this.noteMode) { this.noteText = ''; } else { this.message = ''; }
                this.selectedFiles = [];
                if (this.$refs.mainFileInput) this.$refs.mainFileInput.value = '';
            } catch(e) { console.error(e); }
            finally { this.isSending = false; }
        },
        appendReply(r) {
            if (r.id && r.id <= this.pollLastId) return;
            if (r.id && r.id > this.pollLastId) this.pollLastId = r.id;
            const container = this.$refs.chatMessages;
            if (!container) return;

            const isStaff    = r.author_type === 'staff';
            const isInternal = !!r.is_internal;
            const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            const name    = esc(r.author_name || (isStaff ? 'پشتیبان' : 'مشتری'));
            const initial = name.charAt(0);
            const msg     = esc(r.message || '').replace(/\n/g, '<br>');
            const d    = new Date(r.created_at);
            const time = d.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });

            const bubbleCls = isInternal ? 'bubble-internal' : (isStaff ? 'bubble-staff' : 'bubble-customer');
            const color = isInternal ? 'var(--ui-warning)' : (isStaff ? 'var(--ui-success)' : 'var(--ui-indigo)');
            const badge = isInternal
                ? '<span class="text-[9px] bg-amber-200/60 px-1.5 py-0.5 rounded-full mr-1">یادداشت</span>'
                : (isStaff ? '<span class="text-[9px] bg-green-200/60 px-1.5 py-0.5 rounded-full mr-1">پشتیبان</span>' : '');

            const attBase = '{{ url("admin/tickets/attachments") }}';
            const isImgAtt = att => /^image\//i.test(att.mime||'') || /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(att.name||'');
            let attHtml = '';
            if (r.attachments && r.attachments.length > 0) {
                const atts = r.attachments.filter(a => a.url || a.path);
                const imgs = atts.filter(isImgAtt);
                const docs = atts.filter(a => !isImgAtt(a));
                if (imgs.length > 0) {
                    const n=imgs.length, mc=n===1?'n1':n===2?'n2':n===3?'n3':'n4';
                    const cells = imgs.slice(0,Math.min(n,4)).map((att,i) => {
                        const aUrl = att.url||(attBase+'/'+att.path);
                        const aName = esc(att.name||'تصویر');
                        const more = (i===3&&n>4)?'<div class="att-more">+'+(n-3)+'</div>':'';
                        return '<div class="att-cell"><a href="'+aUrl+'" target="_blank"><img src="'+aUrl+'" loading="lazy" alt="'+aName+'"></a>'+more+'</div>';
                    });
                    attHtml += '<div class="att-mosaic '+mc+'">'+cells.join('')+'</div>';
                }
                docs.forEach(att => {
                    const aUrl=att.url||(attBase+'/'+att.path), aName=esc(att.name||'فایل');
                    attHtml += '<a href="'+aUrl+'" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 mt-1 mr-1">'+aName+'</a>';
                });
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'flex items-end gap-2 max-w-[85%] sm:max-w-[75%] mb-4' + (isStaff || isInternal ? ' flex-row-reverse' : '');
            wrapper.style.cssText = 'align-self:' + ((isStaff || isInternal) ? 'flex-end' : 'flex-start') + ';opacity:0;transform:translateY(8px)';
            wrapper.innerHTML =
                '<div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm" style="background-color:' + color + '">' + initial + '</div>' +
                '<div>' +
                    '<div class="' + bubbleCls + ' px-4 py-2.5 shadow-sm">' +
                        '<p class="text-xs font-bold mb-1 opacity-70">' + name + badge + '</p>' +
                        '<p class="text-sm leading-relaxed whitespace-pre-wrap">' + msg + '</p>' +
                        attHtml +
                    '</div>' +
                    '<span class="text-[10px] text-gray-400 mt-1 px-1">' + time + '</span>' +
                '</div>';

            container.appendChild(wrapper);
            requestAnimationFrame(() => {
                wrapper.style.transition = 'opacity .3s,transform .3s';
                wrapper.style.opacity = '1';
                wrapper.style.transform = 'translateY(0)';
            });
            container.scrollTop = container.scrollHeight;
            if (r.author_type === 'customer') this.notify(r.author_name || 'مشتری', r.message);
        },
        playSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const o = ctx.createOscillator();
                const g = ctx.createGain();
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
            this.toastText = 'پیام جدید از ' + from;
            this.showToast = true;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.showToast = false; }, 6000);
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('تیکت پشتیبانی — پیام جدید', {
                    body: (body || '').substring(0, 120),
                    icon: '/favicon.ico',
                    dir: 'rtl',
                    tag: 'ticket-reply'
                });
            }
        }
    }));
});
</script>
<style>
.att-mosaic{display:flex;flex-wrap:wrap;gap:2px;border-radius:.75rem;overflow:hidden;margin-top:.5rem}
.att-mosaic .att-cell{position:relative;overflow:hidden;flex-shrink:0}
.att-mosaic .att-cell a{display:block;height:100%}
.att-mosaic .att-cell img{width:100%;height:100%;object-fit:cover;display:block;cursor:pointer;transition:opacity .18s}
.att-mosaic .att-cell img:hover{opacity:.88}
.att-more{position:absolute;inset:0;background:rgba(0,0,0,.52);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.1rem;pointer-events:none}
.att-mosaic.n1{max-width:230px}.att-mosaic.n1 .att-cell{width:100%}.att-mosaic.n1 .att-cell img{max-height:180px;border-radius:.75rem}
.att-mosaic.n2{max-width:280px}.att-mosaic.n2 .att-cell{width:calc(50% - 1px)}.att-mosaic.n2 .att-cell img{height:148px}
.att-mosaic.n3{max-width:280px}.att-mosaic.n3 .att-cell:first-child{width:100%}.att-mosaic.n3 .att-cell:first-child img{height:148px}.att-mosaic.n3 .att-cell:not(:first-child){width:calc(50% - 1px)}.att-mosaic.n3 .att-cell:not(:first-child) img{height:108px}
.att-mosaic.n4{max-width:280px}.att-mosaic.n4 .att-cell{width:calc(50% - 1px)}.att-mosaic.n4 .att-cell img{height:108px}
/* ── Messenger 3-col frame ── */
.sp-msgr{display:flex;height:calc(100vh - 4.5rem);min-height:580px;border-radius:1.125rem;overflow:hidden;border:1px solid rgba(226,232,240,.6);background:#fff;box-shadow:0 4px 32px rgba(15,23,42,.07)}
.sp-sidebar{width:248px;min-width:210px;flex-shrink:0;display:flex;flex-direction:column;border-left:1px solid #e2e8f0;background:#f8fafc}
.sp-sidebar-head{padding:.8rem .9rem;border-bottom:1px solid #e2e8f0;background:#fff;flex-shrink:0}
.sp-conv-search{width:100%;padding:.38rem .7rem .38rem 2rem;border:1px solid #e2e8f0;border-radius:.6rem;font-size:.78rem;background:#f8fafc;outline:none;transition:all .2s}
.sp-conv-search:focus{border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,.1);background:#fff}
.sp-conv-list{flex:1;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#c7d2fe transparent}
.sp-conv-item{display:flex;align-items:flex-start;gap:.6rem;padding:.65rem .85rem;border-bottom:1px solid rgba(226,232,240,.4);cursor:pointer;text-decoration:none;color:inherit;transition:background .15s;position:relative}
.sp-conv-item:hover{background:rgba(99,102,241,.04)}
.sp-conv-item.active{background:rgba(99,102,241,.1);border-right:3px solid #6366f1}
.sp-conv-av{width:2rem;height:2rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;color:#fff;flex-shrink:0}
.sp-conv-body{flex:1;min-width:0}
.sp-conv-subj{font-size:.76rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
.sp-conv-item.active .sp-conv-subj{color:#4f46e5}
.sp-conv-meta{display:flex;align-items:center;gap:.3rem;margin-top:.15rem;font-size:.67rem;color:#94a3b8}
.sp-conv-sdot{width:.33rem;height:.33rem;border-radius:50%;flex-shrink:0}
.sp-chat{flex:1;min-width:0;display:flex;flex-direction:column;background:#fff}
.sp-chat-head{padding:.7rem 1rem;border-bottom:1px solid #e2e8f0;background:rgba(255,255,255,.97);display:flex;align-items:center;gap:.7rem;flex-shrink:0}
.sp-info{width:218px;min-width:190px;flex-shrink:0;display:flex;flex-direction:column;border-right:1px solid #e2e8f0;background:#f8fafc;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#c7d2fe transparent}
.sp-info-sec{padding:.8rem .9rem;border-bottom:1px solid #e2e8f0}
.sp-info-lbl{font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.45rem}
.sp-scroll-pill{position:absolute;bottom:.75rem;left:50%;transform:translateX(-50%);display:inline-flex;align-items:center;gap:.35rem;padding:.33rem .8rem;border-radius:9999px;background:#6366f1;color:#fff;font-size:.7rem;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,.35);z-index:20;white-space:nowrap;transition:all .2s}
.sp-scroll-pill:hover{background:#4f46e5}
@media(max-width:1200px){.sp-info{display:none}}
@media(max-width:900px){.sp-sidebar{display:none}.sp-msgr{border-radius:0;height:100dvh}}
</style>

<div style="margin:-1rem" role="main" aria-label="جزئیات تیکت {{ $ticket->ticket_number }}"
     data-poll-url="{{ route('tickets.admin.poll', $ticket->id) }}"
     data-poll-last-id="{{ $ticket->replies->last()?->id ?? 0 }}"
     x-data="ticketChat">

    {{-- Real-time toast notification --}}
    <div x-show="showToast" x-transition
         class="fixed top-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-indigo-600 text-white px-5 py-3 rounded-2xl shadow-xl text-sm font-medium"
         style="display:none">
        <svg class="w-4 h-4 shrink-0 animate-bounce" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span x-text="toastText"></span>
        <button @click="showToast=false" class="mr-2 opacity-70 hover:opacity-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="sp-msgr">

        {{-- ═══ LEFT SIDEBAR: Ticket list ════════════════════════════ --}}
        <div class="sp-sidebar">
            <div class="sp-sidebar-head">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-700">گفتگوها</span>
                    <a href="{{ route('tickets.admin.create') }}"
                       class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 transition-all"
                       style="color:#fff;font-size:11px;font-weight:600;white-space:nowrap">
                        <svg style="width:12px;height:12px;stroke:#fff;stroke-width:2.5;fill:none;flex-shrink:0" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        جدید
                    </a>
                </div>
                <div class="relative">
                    <svg class="absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="convSearch" placeholder="جستجو..." class="sp-conv-search" style="padding-right:1.875rem">
                </div>
            </div>
            <div class="sp-conv-list">
                @forelse($sidebarTickets as $st)
                @php
                    $stActive = $st->id === $ticket->id;
                    $stColor  = match($st->status) { 'open'=>'#f59e0b','in_progress'=>'#3b82f6',default=>'#10b981' };
                    $stGrad   = match($st->status) { 'open'=>'linear-gradient(135deg,#f59e0b,#f97316)','in_progress'=>'linear-gradient(135deg,#6366f1,#3b82f6)',default=>'linear-gradient(135deg,#10b981,#059669)' };
                    $stLabel  = match($st->status) { 'open'=>'باز','in_progress'=>'در بررسی',default=>'بسته' };
                    $stInit   = mb_substr($st->subject ?? '#', 0, 1);
                    $stTime   = $st->updated_at?->format('H:i') ?? '';
                @endphp
                <a href="{{ route('tickets.admin.show', $st->id) }}"
                   class="sp-conv-item {{ $stActive ? 'active' : '' }}"
                   x-show="!convSearch || '{{ addslashes($st->subject) }}'.includes(convSearch) || '{{ $st->ticket_number }}'.includes(convSearch) || '{{ addslashes($st->customer_name ?? '') }}'.includes(convSearch)">
                    <div class="sp-conv-av" style="background:{{ $stGrad }}">{{ $stInit }}</div>
                    <div class="sp-conv-body">
                        <span class="sp-conv-subj">{{ $st->subject }}</span>
                        <div class="sp-conv-meta">
                            <span class="sp-conv-sdot" style="background:{{ $stColor }}"></span>
                            <span>{{ $stLabel }}</span>
                            @if($st->priority === 'high')<span class="bg-red-100 text-red-600 px-1 rounded text-[9px] font-bold">فوری</span>@endif
                            <span class="mr-auto">{{ $stTime }}</span>
                        </div>
                        @if($st->customer_name)<div class="text-[10px] text-slate-400 truncate mt-0.5">{{ $st->customer_name }}</div>@endif
                    </div>
                </a>
                @empty
                <div class="p-4 text-center text-xs text-slate-400">هیچ تیکتی نیست</div>
                @endforelse
            </div>
        </div>

        {{-- ═══ MIDDLE: Chat area ══════════════════════════════════════ --}}
        <div class="sp-chat">

            {{-- Chat header --}}
            <div class="sp-chat-head">
                <a href="{{ route('tickets.admin.index') }}"
                   class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 transition-all text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                </a>
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                     style="background:linear-gradient(135deg,#6366f1,#a855f7)">{{ mb_substr($ticket->subject,0,1) }}</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-800 truncate">{{ $ticket->subject }}</p>
                    <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                        <span class="font-mono bg-slate-100 text-slate-500 text-[10px] px-1.5 py-0.5 rounded">{{ $ticket->ticket_number }}</span>
                        @if($ticket->status==='open')<span class="tkt-badge bg-yellow-50 text-yellow-700 border-yellow-200 !text-[10px] !py-0"><span class="pdot bg-yellow-400"></span>باز</span>
                        @elseif($ticket->status==='in_progress')<span class="tkt-badge bg-blue-50 text-blue-700 border-blue-200 !text-[10px] !py-0"><span class="pdot bg-blue-400"></span>در بررسی</span>
                        @else<span class="tkt-badge bg-green-50 text-green-700 border-green-200 !text-[10px] !py-0">بسته</span>@endif
                        @if($ticket->priority==='high')<span class="tkt-badge bg-red-50 text-red-600 border-red-200 !text-[10px] !py-0">فوری</span>@endif
                        @if($ticket->customer_name)<span class="text-[10px] text-slate-500">{{ $ticket->customer_name }}</span>@endif
                    </div>
                </div>
                <span class="text-xs text-slate-400 shrink-0 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>زنده
                </span>
            </div>

            {{-- Flash messages (compact, inside chat) --}}
            @foreach(['success'=>'green','sms_success'=>'green','sms_error'=>'red','error'=>'red'] as $fk=>$fc)
            @if(session($fk))
            <div class="px-4 py-2 flex items-center gap-2 border-b text-xs font-medium {{ $fc==='green' ? 'bg-green-50 border-green-100 text-green-800' : 'bg-red-50 border-red-100 text-red-800' }}"
                 x-data="{show:true}" x-show="show">
                <span class="flex-1">{{ session($fk) }}</span>
                <button @click="show=false" class="opacity-60 hover:opacity-100">✕</button>
            </div>
            @endif
            @endforeach
            @if($errors->any())
            <div class="px-4 py-2 bg-red-50 border-b border-red-100 text-xs text-red-700">
                {{ implode(' · ', $errors->all()) }}
            </div>
            @endif

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
            <div x-show="activeTab==='chat'" class="flex-1 flex flex-col overflow-hidden">

                {{-- Chat Header --}}
                <div class="bg-white border border-gray-200 border-b-0 p-4 rounded-none shrink-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                {{ mb_substr($ticket->customer_name ?? $ticket->customer_mobile ?? 'م', 0, 1) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $ticket->customer_name ?? 'مشتری' }}</p>
                                @if($ticket->customer_mobile)
                                <p class="text-xs text-gray-400" dir="ltr">{{ $ticket->customer_mobile }}</p>
                                @endif
                            </div>
                        </div>
                        {{-- Real-time status indicator --}}
                        <div class="mr-auto flex items-center gap-2 flex-wrap">
                            <div class="flex items-center gap-1.5 text-xs text-gray-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                                <span>به‌روزرسانی خودکار هر ۴ ثانیه</span>
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
                <div class="bg-white border border-gray-200 border-t-0 flex-1 flex flex-col overflow-hidden">
                    <div x-ref="chatMessages"
                         @scroll="handleScroll"
                         class="flex-1 overflow-y-auto p-4 space-y-1 chat-bg flex flex-col"
                         style="min-height:0; scroll-behavior:smooth">

                        @php $customerInitial = mb_substr($ticket->customer_name ?? $ticket->customer_mobile ?? 'م', 0, 1); @endphp

                        {{-- Initial message --}}
                        <div class="flex items-end gap-2 max-w-[85%] sm:max-w-[75%] mb-4" style="align-self:flex-start">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm bg-indigo-500">
                                {{ $customerInitial }}
                            </div>
                            <div>
                                <div class="bubble-customer px-4 py-2.5 shadow-sm">
                                    <p class="text-xs font-bold mb-1 opacity-70">{{ $ticket->customer_name ?? $ticket->customer_mobile ?? 'مشتری' }}</p>
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $ticket->description }}</p>
                                    @if($ticket->attachments && is_array($ticket->attachments))
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @foreach($ticket->attachments as $file)
                                            @if(isset($file['path']) || isset($file['url']))
                                            @php
                                                $attUrl  = $file['url'] ?? route('tickets.admin.attachment.download', ['path' => $file['path']]);
                                                $attName = $file['name'] ?? 'فایل';
                                                $attIsImg = preg_match('/^image\//i', $file['mime'] ?? '') || preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $attName);
                                            @endphp
                                            @if($attIsImg)
                                            <a href="{{ $attUrl }}" target="_blank" class="block mt-1">
                                                <img src="{{ $attUrl }}" loading="lazy" alt="{{ $attName }}"
                                                     class="rounded-xl max-h-52 max-w-[220px] object-cover hover:opacity-90 transition-opacity shadow-sm border border-white/60 cursor-pointer">
                                            </a>
                                            @else
                                            <a href="{{ $attUrl }}" target="_blank"
                                               class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                                <span class="truncate max-w-[100px]">{{ $attName }}</span>
                                            </a>
                                            @endif
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
                                                @if(isset($file['path']) || isset($file['url']))
                                                @php
                                                    $attUrl  = $file['url'] ?? route('tickets.admin.attachment.download', ['path' => $file['path']]);
                                                    $attName = $file['name'] ?? 'فایل';
                                                    $attIsImg = preg_match('/^image\//i', $file['mime'] ?? '') || preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $attName);
                                                @endphp
                                                @if($attIsImg)
                                                <a href="{{ $attUrl }}" target="_blank" class="block mt-1">
                                                    <img src="{{ $attUrl }}" loading="lazy" alt="{{ $attName }}"
                                                         class="rounded-xl max-h-52 max-w-[220px] object-cover hover:opacity-90 transition-opacity shadow-sm border border-white/60 cursor-pointer">
                                                </a>
                                                @else
                                                <a href="{{ $attUrl }}" target="_blank"
                                                   class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                                    <span class="truncate max-w-[100px]">{{ $attName }}</span>
                                                </a>
                                                @endif
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

                        {{-- Compact scroll pill --}}
                        <div x-show="showScrollBtn" x-transition
                             @click="scrollDown()"
                             class="sp-scroll-pill">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            پیام‌های جدید
                        </div>
                    </div>

                    {{-- Reply form --}}
                    @if($ticket->status !== 'closed')
                    <div class="border-t border-gray-200 bg-white/90 p-3 shrink-0">
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
                              @submit.prevent="sendReply($event.target)"
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
                                              @keydown.enter.prevent="if(!$event.shiftKey) sendReply($el.closest('form'))"></textarea>
                                    <small class="text-[10px] text-gray-400 mt-1 block mr-2">اینتر = ارسال | Shift+Enter = خط جدید</small>
                                    @if($attachmentsEnabled)
                                    <label class="inline-flex items-center gap-1.5 mt-2 cursor-pointer text-slate-500 hover:text-indigo-600 transition-colors text-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32"/></svg>
                                        پیوست فایل
                                        <input type="file" name="attachments[]" multiple x-ref="mainFileInput"
                                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.zip,.txt"
                                               class="hidden" @change="handleFileChange($event)">
                                    </label>
                                    <div x-show="selectedFiles.length > 0" x-transition class="mt-2 space-y-1.5">
                                        <div x-show="imgFiles.length > 0" class="att-mosaic" :class="mosaicCls">
                                            <template x-for="(f,i) in imgFiles.slice(0,4)" :key="f._pos">
                                                <div class="att-cell" style="position:relative">
                                                    <template x-if="f.preview"><a :href="f.preview" target="_blank"><img :src="f.preview" :alt="f.name"></a></template>
                                                    <template x-if="!f.preview"><div style="width:100%;height:100%;min-height:60px;background:#f1f5f9;display:flex;align-items:center;justify-content:center"><span style="font-size:.6rem;color:#94a3b8">...</span></div></template>
                                                    <div x-show="i===3&&imgFiles.length>4" class="att-more" x-text="'+'+(imgFiles.length-3)"></div>
                                                    <button type="button" @click.stop="removeFile(f._pos)" style="position:absolute;top:4px;left:4px;width:18px;height:18px;border-radius:9999px;background:rgba(0,0,0,.55);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;cursor:pointer;border:none;z-index:5">×</button>
                                                </div>
                                            </template>
                                        </div>
                                        <div x-show="docFiles.length > 0" class="flex flex-wrap gap-1">
                                            <template x-for="f in docFiles" :key="f._pos">
                                                <div class="flex items-center gap-1 bg-white border border-emerald-200 rounded-lg px-2 py-1" style="max-width:160px">
                                                    <div style="min-width:0;flex:1"><div x-text="f.name" style="font-size:.65rem;font-weight:600;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div><div x-text="f.size" style="font-size:.6rem;color:#94a3b8"></div></div>
                                                    <button type="button" @click.stop="removeFile(f._pos)" style="width:14px;height:14px;border-radius:9999px;background:#fee2e2;color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:9px;cursor:pointer;border:none;flex-shrink:0">×</button>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="flex items-center gap-1" style="font-size:.65rem;color:#059669;font-weight:600">
                                            <svg style="width:11px;height:11px" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <span x-text="selectedFiles.length + ' فایل آماده ارسال'"></span>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                <button type="submit"
                                        :disabled="isSending || (!noteMode && !message.trim()) || (noteMode && !noteText.trim())"
                                        class="flex items-center justify-center rounded-full w-10 h-10 transition-all shrink-0"
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
                                    <p class="text-[10px] text-gray-500 mt-0.5">توسط: {{ $ticket->customer_name ?? $ticket->customer_mobile ?? 'مشتری' }}</p>
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

        </div>{{-- /sp-chat --}}

        {{-- ═══ RIGHT: Info panel ══════════════════════════════════════ --}}
        <div class="sp-info">

            {{-- ── Ticket Info ─────────────────────────────────────── --}}
            <div class="sp-info-sec">
                <h3 class="sp-info-lbl">
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
                        <dt class="text-gray-400 text-xs">موبایل</dt>
                        <dd class="font-semibold text-xs text-gray-700 truncate" dir="ltr">{{ $ticket->customer_mobile ?? '—' }}</dd>
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
            <div class="sp-info-sec">
                <h3 class="sp-info-lbl">
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
            <div class="sp-info-sec">
                <h3 class="sp-info-lbl">
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
            <div class="sp-info-sec">
                <h3 class="sp-info-lbl">
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
            <div class="sp-info-sec">
                <h3 class="sp-info-lbl">
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
            <div class="sp-info-sec">
                <h3 class="sp-info-lbl">
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
            <div class="sp-info-sec"
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

        </div>{{-- /sp-info --}}
    </div>{{-- /sp-msgr --}}

</div>
@endsection
