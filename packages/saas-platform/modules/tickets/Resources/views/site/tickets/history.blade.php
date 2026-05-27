@extends(config('tickets.layouts.site', 'admin.layout'))

@section('title', 'تاریخچه تیکت‌ها')

@push('head')
<style>
.htk-card{background:rgba(255,255,255,0.82);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.3);border-radius:1.25rem;box-shadow:0 8px 32px rgba(15,23,42,0.06);padding:1.25rem;transition:all 0.3s ease}
.htk-card:hover{box-shadow:0 12px 40px rgba(15,23,42,0.1)}
.htk-badge{display:inline-flex;align-items:center;gap:0.35rem;padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.75rem;font-weight:600;border:1px solid transparent}
.htk-number{font-family:monospace;font-weight:700;color:#4338ca;font-size:0.75rem;background:#eef2ff;padding:0.25rem 0.5rem;border-radius:0.5rem}
.htk-reply{font-size:0.8125rem;background:#eff6ff;padding:0.625rem;border-radius:0.75rem;border:1px solid #dbeafe}
.htk-reply-customer{font-size:0.8125rem;background:#f9fafb;padding:0.5rem 0.75rem;border-radius:0.75rem}
.form-input{width:100%!important;padding:0.75rem 1rem!important;border:1px solid #e2e8f0!important;border-radius:0.75rem!important;font-size:0.875rem!important;transition:all 0.2s ease;background:rgba(255,255,255,0.6);box-sizing:border-box}
.form-input:focus{outline:none!important;border-color:#a5b4fc!important;box-shadow:0 0 0 3px rgba(99,102,241,0.12)!important}
.btn-primary{display:inline-flex;align-items:center;gap:0.4rem;padding:0.5rem 1.25rem;border-radius:0.75rem;font-weight:600;font-size:0.8125rem;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.25);transition:all 0.25s}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(99,102,241,0.35)}
</style>
@endpush

@section('content')
<div style="max-width:48rem;margin:0 auto">

    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem">
        <a href="{{ route('tickets.site.index') }}"
           style="display:inline-flex;align-items:center;gap:0.375rem;padding:0.5rem 0.875rem;border-radius:0.75rem;font-size:0.75rem;font-weight:500;color:#4b5563;background:rgba(255,255,255,0.7);border:1px solid #e5e7eb;text-decoration:none;transition:all 0.2s">
            <svg style="width:1rem;height:1rem;flex-shrink:0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت به پشتیبانی
        </a>
        <div>
            <h2 style="font-size:1.25rem;font-weight:700;color:#111827;margin:0">تاریخچه تیکت‌ها</h2>
            <p style="font-size:0.875rem;color:#6b7280;margin:0.25rem 0 0">مشاهده و پیگیری تمام تیکت‌های ثبت شده</p>
        </div>
    </div>

    {{-- Alerts --}}
    @if(!empty($error))
    <div style="background:rgba(254,242,242,0.85);border:1px solid rgba(239,68,68,0.2);border-radius:1rem;padding:1rem 1.25rem;display:flex;align-items:flex-start;gap:0.75rem;margin-bottom:1rem">
        <svg style="width:1.25rem;height:1.25rem;color:#ef4444;flex-shrink:0;margin-top:0.125rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p style="font-size:0.875rem;color:#b91c1c;margin:0">{{ $error }}</p>
    </div>
    @endif

    @if(session('success'))
    <div style="background:rgba(236,253,245,0.8);border:1px solid #a7f3d0;border-radius:1rem;padding:1rem 1.25rem;display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem"
         x-data="{ show: true }" x-show="show" x-transition>
        <svg style="width:1.25rem;height:1.25rem;color:#059669;flex-shrink:0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span style="font-size:0.875rem;color:#065f46;font-weight:500">{{ session('success') }}</span>
        <button @click="show = false" style="margin-right:auto;color:#34d399;background:none;border:none;cursor:pointer">
            <svg style="width:1rem;height:1rem" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    {{-- Ticket list --}}
    @if($tickets->isEmpty())
    <div class="htk-card" style="text-align:center;padding:3rem 1.5rem">
        <div style="font-size:3rem;margin-bottom:1rem">📭</div>
        <h3 style="font-weight:700;color:#111827;margin:0 0 0.5rem;font-size:1rem">هیچ تیکتی ثبت نشده</h3>
        <p style="font-size:0.875rem;color:#6b7280;margin:0 0 1.5rem">
            هنوز تیکتی ارسال نکرده‌اید.
        </p>
        <a href="{{ route('tickets.site.create') }}"
           style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1.5rem;border-radius:0.75rem;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;font-size:0.875rem;font-weight:600;text-decoration:none">
            ➕ ثبت تیکت جدید
        </a>
    </div>
    @else
    <div style="display:flex;flex-direction:column;gap:1rem">
        @foreach($tickets as $ticket)
        <div class="htk-card" style="{{ $ticket->status === 'closed' ? 'border-color:#e5e7eb;background:rgba(249,250,251,0.8)' : '' }}">

            {{-- Ticket header --}}
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.75rem">
                <div style="display:flex;align-items:center;gap:0.5rem;min-width:0">
                    <span class="htk-number">{{ $ticket->ticket_number ?? '—' }}</span>
                    <a href="{{ route('tickets.site.show', $ticket->id) }}"
                       style="font-weight:600;font-size:0.9375rem;color:#111827;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;hover:color:#4f46e5">
                        {{ $ticket->subject }}
                    </a>
                </div>
                <div style="display:flex;gap:0.5rem;flex-shrink:0">
                    @if($ticket->status === 'open')
                    <span class="htk-badge" style="background:#fefce8;color:#a16207;border-color:#fde68a">
                        <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#eab308;animation:pulse 2s infinite;display:inline-block"></span> باز
                    </span>
                    @elseif($ticket->status === 'in_progress')
                    <span class="htk-badge" style="background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe">
                        <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#3b82f6;animation:pulse 2s infinite;display:inline-block"></span> در حال بررسی
                    </span>
                    @else
                    <span class="htk-badge" style="background:#f9fafb;color:#4b5563;border-color:#e5e7eb">
                        <svg style="width:0.9rem;height:0.9rem" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> بسته
                    </span>
                    @endif

                    @if($ticket->priority)
                    <span class="htk-badge" style="
                        background:{{ $ticket->priority === 'high' ? '#fef2f2' : ($ticket->priority === 'low' ? '#ecfdf5' : '#eef2ff') }};
                        color:{{ $ticket->priority === 'high' ? '#dc2626' : ($ticket->priority === 'low' ? '#059669' : '#6366f1') }}">
                        {{ $ticket->priority === 'high' ? '🔴 بالا' : ($ticket->priority === 'low' ? '🟢 کم' : '🔵 عادی') }}
                    </span>
                    @endif
                </div>
            </div>

            {{-- Description --}}
            <div style="background:#f9fafb;border-radius:0.75rem;padding:0.75rem 1rem;margin-bottom:0.75rem">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.375rem">
                    <span style="font-size:0.75rem;font-weight:600;color:#374151">پیام اصلی</span>
                    <span style="font-size:0.6875rem;color:#9ca3af">{{ $ticket->created_at->format('Y/m/d H:i') }}</span>
                </div>
                <p style="font-size:0.875rem;color:#4b5563;white-space:pre-wrap;margin:0">{{ $ticket->description }}</p>
            </div>

            {{-- Replies --}}
            @if($ticket->replies->isNotEmpty())
            <div style="margin-bottom:0.75rem">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem">
                    <span style="width:0.25rem;height:1rem;background:linear-gradient(135deg,#6366f1,#a855f7);border-radius:9999px;display:inline-block"></span>
                    <span style="font-size:0.8125rem;font-weight:600;color:#374151">پاسخ‌ها ({{ $ticket->replies->count() }})</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:0.5rem;padding-right:0.75rem;border-right:2px solid #e5e7eb">
                    @foreach($ticket->replies as $reply)
                    @if(!($reply->author_type === 'internal'))
                    <div class="{{ $reply->author_type === 'staff' ? 'htk-reply' : 'htk-reply-customer' }}">
                        <div style="display:flex;justify-content:space-between;color:#6b7280;margin-bottom:0.25rem">
                            <span style="font-weight:600;color:#374151">
                                @if($reply->author_type === 'staff')
                                <span style="display:inline-flex;align-items:center;gap:0.25rem">
                                    <svg style="width:1rem;height:1rem;color:#4f46e5;flex-shrink:0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                    پشتیبانی
                                </span>
                                @else
                                {{ $reply->customer_name ?? 'شما' }}
                                @endif
                            </span>
                            <span style="font-size:0.6875rem">{{ $reply->created_at->format('Y/m/d H:i') }}</span>
                        </div>
                        <p style="white-space:pre-wrap;color:#374151;margin:0;font-size:0.8125rem">{{ $reply->message }}</p>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Quick reply form --}}
            @if($ticket->status !== 'closed')
            <div style="border-top:1px solid #e5e7eb;padding-top:0.75rem;margin-top:0.5rem">
                <form method="POST" action="{{ route('tickets.site.replies.store', $ticket->id) }}"
                      x-data="{ msg: '' }">
                    @csrf
                    <div style="display:flex;flex-direction:column;gap:0.5rem">
                        <textarea name="message" x-model="msg" class="form-input"
                                  style="resize:vertical;min-height:80px" rows="3" required
                                  placeholder="پاسخ خود را بنویسید..." maxlength="5000"></textarea>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <span style="font-size:0.75rem;color:#9ca3af" x-text="msg.length + ' / 5000 کاراکتر'"></span>
                            <button type="submit" class="btn-primary">
                                <svg style="width:1rem;height:1rem;flex-shrink:0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                                ارسال پاسخ
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @else
            <div style="border-top:1px solid #e5e7eb;padding-top:0.75rem;margin-top:0.5rem;text-align:center">
                <span style="font-size:0.8125rem;color:#9ca3af">این تیکت بسته شده است.</span>
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
