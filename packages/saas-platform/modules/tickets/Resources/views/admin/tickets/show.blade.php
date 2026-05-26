@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'تیکت ' . $ticket->ticket_number)

@push('head')
<style>
.glass-card-strong{background:rgba(255,255,255,0.82);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.3);border-radius:1.25rem;box-shadow:0 8px 32px rgba(15,23,42,0.06)}
.btn-gradient{display:inline-flex;align-items:center;gap:0.5rem;padding:0.7rem 1.75rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.25s ease;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.25);text-decoration:none}
.btn-gradient:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,0.35)}
.btn-ghost{display:inline-flex;align-items:center;gap:0.5rem;padding:0.7rem 1.75rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.2s ease;background:transparent;color:#64748b;border:1.5px solid #e2e8f0;cursor:pointer;text-decoration:none}
.btn-ghost:hover{background:#f8fafc;border-color:#cbd5e1;color:#475569}
.ticket-status-badge{display:inline-flex;align-items:center;gap:0.4rem;padding:0.3rem 0.85rem;border-radius:9999px;font-size:0.75rem;font-weight:600;white-space:nowrap;border:1px solid transparent}
.form-input{width:100%!important;padding:0.65rem 0.875rem!important;border:1px solid #e2e8f0!important;border-radius:0.75rem!important;font-size:0.875rem!important;transition:all 0.2s ease;background:rgba(255,255,255,0.7)}
.form-input:focus{outline:none!important;border-color:#a5b4fc!important;box-shadow:0 0 0 3px rgba(99,102,241,0.12)!important}
</style>
@endpush

@section('content')
<div class="max-w-6xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('tickets.admin.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/70 hover:bg-white border border-gray-200 transition-all hover:shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت به لیست
        </a>
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">جزئیات تیکت</h2>
            <p class="text-sm text-gray-500 mt-1">{{ $ticket->ticket_number }} — {{ Str::limit($ticket->subject, 50) }}</p>
        </div>
    </div>

    @foreach(['success' => 'green', 'sms_success' => 'green', 'sms_error' => 'red'] as $flashKey => $flashColor)
    @if(session($flashKey))
    <div class="glass-card-strong mb-4 p-4 border-r-4 border-r-{{ $flashColor }}-400 flex items-center gap-3 rounded-2xl"
         x-data="{ show: true }" x-show="show" x-transition>
        @if($flashColor === 'green')
        <svg class="w-5 h-5 text-{{ $flashColor }}-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @else
        <svg class="w-5 h-5 text-{{ $flashColor }}-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @endif
        <span class="text-sm text-{{ $flashColor }}-800 font-medium">{{ session($flashKey) }}</span>
        <button @click="show = false" class="mr-auto text-{{ $flashColor }}-400 hover:text-{{ $flashColor }}-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif
    @endforeach

    @if($errors->any())
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl">
        <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- ─── Chat Panel (2/3 width) ───────────────────────────── --}}
        <div class="lg:col-span-2">

            {{-- Chat Header --}}
            <div class="bg-white rounded-t-2xl shadow-sm border border-gray-200 border-b-0 overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            @if($ticket->status === 'open')
                                <span class="ticket-status-badge bg-yellow-50 text-yellow-700 border-yellow-200">
                                    <span class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></span>
                                    باز
                                </span>
                            @elseif($ticket->status === 'in_progress')
                                <span class="ticket-status-badge bg-blue-50 text-blue-700 border-blue-200">
                                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                                    در حال بررسی
                                </span>
                            @else
                                <span class="ticket-status-badge bg-gray-50 text-gray-600 border-gray-200">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    بسته شده
                                </span>
                            @endif
                            @if($ticket->priority === 'high')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-600 border border-red-200">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                    اولویت بالا
                                </span>
                            @elseif($ticket->priority === 'low')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200">اولویت کم</span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-600 border border-blue-200">اولویت معمولی</span>
                            @endif
                        </div>
                    </div>
                    <h2 class="text-lg font-bold text-gray-900 mt-3">{{ $ticket->subject }}</h2>
                </div>
            </div>

            {{-- Chat Messages --}}
            <div class="bg-white shadow-sm border border-gray-200 border-t-0"
                 x-data="supportChat()"
                 style="min-height: 400px; display: flex; flex-direction: column;">

                <div x-ref="chatMessages"
                     @scroll="handleScroll"
                     class="flex-1 overflow-y-auto p-4 space-y-1"
                     style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); max-height: 480px; scroll-behavior: smooth;">

                    {{-- پیام اولیه مشتری --}}
                    @php $customerInitial = $ticket->customer_name ? mb_substr($ticket->customer_name, 0, 1) : 'م'; @endphp
                    <div class="flex items-end gap-2 max-w-[85%] sm:max-w-[75%] mb-4" style="align-self: flex-start;">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm" style="background-color: #6366f1;">
                            {{ $customerInitial }}
                        </div>
                        <div>
                            <div class="rounded-2xl rounded-br-sm px-4 py-2.5 shadow-sm" style="background-color: #e3f2fd; color: #1a1a2e;">
                                <p class="text-xs font-semibold mb-1 opacity-80">{{ $ticket->customer_name ?? $ticket->customer_email ?? 'مشتری' }}</p>
                                <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $ticket->description }}</p>
                                @if($ticket->attachments && is_array($ticket->attachments))
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach($ticket->attachments as $file)
                                        @if(isset($file['path']))
                                        <a href="{{ asset('storage/' . $file['path']) }}" target="_blank"
                                           class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
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

                    {{-- پاسخ‌ها --}}
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
                                            <span class="inline-flex items-center gap-0.5 text-[9px] bg-green-200/50 px-1.5 py-0.5 rounded-full mr-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
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
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
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

                    {{-- دکمه اسکرول به پایین --}}
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
                    <form method="POST" action="{{ route('tickets.admin.replies.store', $ticket->id) }}"
                          @submit="isSending = true; setTimeout(() => { isSending = false }, 4000)"
                          enctype="{{ $attachmentsEnabled ? 'multipart/form-data' : 'application/x-www-form-urlencoded' }}">
                        @csrf
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <textarea name="message"
                                          x-model="message"
                                          class="w-full rounded-2xl border border-gray-300 resize-none py-3 px-4 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"
                                          rows="2" required maxlength="5000"
                                          placeholder="پاسخ خود را بنویسید..."
                                          style="background:rgba(255,255,255,0.8)"
                                          @keydown.enter.prevent="if(!$event.shiftKey) $el.closest('form').submit()"></textarea>
                                <small class="text-[10px] text-gray-400 mt-1 block mr-2">اینتر = ارسال | Shift+Enter = خط جدید</small>
                                @if($attachmentsEnabled)
                                <input type="file" name="attachments[]" multiple class="mt-2 text-xs text-gray-500">
                                @endif
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
                    <span class="text-sm text-gray-500">این تیکت بسته شده است.</span>
                </div>
                @endif
            </div>
        </div>

        {{-- ─── Sidebar (1/3 width) ──────────────────────────────── --}}
        <div class="space-y-4">

            {{-- اطلاعات تیکت --}}
            <div class="glass-card-strong p-4">
                <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    اطلاعات تیکت
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">شماره</span><span class="font-mono font-medium text-xs">{{ $ticket->ticket_number }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">سایت</span><span class="font-medium">{{ $ticket->site?->name ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">مسئول</span><span class="font-medium">{{ $ticket->assignee?->name ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">ایمیل</span><span class="font-medium text-left text-xs" dir="ltr">{{ $ticket->customer_email ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">نام</span><span class="font-medium">{{ $ticket->customer_name ?? '—' }}</span></div>
                    @if($ticket->category)
                    <div class="flex justify-between"><span class="text-gray-500">دسته‌بندی</span><span class="font-medium">{{ $ticket->category }}</span></div>
                    @endif
                    @if($ticket->tags && is_array($ticket->tags) && count($ticket->tags))
                    <div class="flex justify-between">
                        <span class="text-gray-500">برچسب‌ها</span>
                        <span class="font-medium text-xs">{{ implode('، ', $ticket->tags) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between"><span class="text-gray-500">تاریخ ایجاد</span><span class="font-medium text-xs">{{ \App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d H:i') }}</span></div>
                    @if($slaEnabled && !empty($slaMetrics))
                    <div class="flex justify-between"><span class="text-gray-500">نقض SLA</span><span class="font-medium text-red-500">{{ $slaMetrics['breach_rate'] ?? 0 }}%</span></div>
                    @endif
                </div>
            </div>

            {{-- تغییر وضعیت --}}
            @if($ticket->status !== 'closed')
            <div class="glass-card-strong p-4">
                <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    تغییر وضعیت
                </h3>
                <div class="flex flex-col gap-2">
                    @if($ticket->status === 'open')
                    <form method="POST" action="{{ route('tickets.admin.status.update', $ticket->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="in_progress">
                        <button class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm py-2.5 rounded-xl transition font-medium border border-blue-200">
                            شروع بررسی
                        </button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('tickets.admin.status.update', $ticket->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="closed">
                        <button class="w-full bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm py-2.5 rounded-xl transition font-medium border border-gray-200">
                            بستن تیکت
                        </button>
                    </form>
                </div>
            </div>
            @else
            <div class="glass-card-strong p-4">
                <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    وضعیت
                </h3>
                <form method="POST" action="{{ route('tickets.admin.status.update', $ticket->id) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="open">
                    <button class="w-full bg-yellow-50 hover:bg-yellow-100 text-yellow-700 text-sm py-2.5 rounded-xl transition font-medium border border-yellow-200">
                        بازگشایی تیکت
                    </button>
                </form>
            </div>
            @endif

            {{-- تخصیص به کارشناس --}}
            <div class="glass-card-strong p-4">
                <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    تخصیص به کارشناس
                </h3>
                <form method="POST" action="{{ route('tickets.admin.assign', $ticket->id) }}">
                    @csrf @method('PATCH')
                    <select name="assigned_to" class="form-input mb-2 w-full">
                        <option value="">بدون مسئول</option>
                        @foreach($adminUsers as $admin)
                            <option value="{{ $admin->id }}" {{ $ticket->assigned_to == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm py-2.5 rounded-xl transition font-medium border border-indigo-200">
                        تخصیص
                    </button>
                </form>
            </div>

            {{-- تخصیص به سایت --}}
            @if($sites->count())
            <div class="glass-card-strong p-4">
                <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                    تخصیص به سایت
                </h3>
                <form method="POST" action="{{ route('tickets.admin.site.update', $ticket->id) }}">
                    @csrf @method('PATCH')
                    <select name="site_id" class="form-input mb-2 w-full">
                        <option value="">بدون سایت</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}" {{ $ticket->site_id == $site->id ? 'selected' : '' }}>
                                {{ $site->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="w-full bg-teal-50 hover:bg-teal-100 text-teal-700 text-sm py-2.5 rounded-xl transition font-medium border border-teal-200">
                        ثبت تغییر سایت
                    </button>
                </form>
            </div>
            @endif

            {{-- ارسال پیامک دستی --}}
            @if($smsEnabled)
            <div class="glass-card-strong p-4"
                 x-data="{
                    open: false,
                    phone: '',
                    message: '',
                    loading: false,
                    templates: [],
                    async fetchTemplates() {
                        const r = await fetch('{{ route('tickets.admin.sms.templates', $ticket->id) }}');
                        const d = await r.json();
                        this.templates = d.templates || [];
                        if (d.customer_phone) this.phone = d.customer_phone;
                    },
                    useTemplate(tpl) { this.message = tpl.message; }
                 }"
                 @open-sms.window="open = true; fetchTemplates()">

                <button @click="open = true; fetchTemplates()"
                        class="w-full flex items-center justify-center gap-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-sm py-2.5 rounded-xl transition font-medium border border-purple-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    ارسال پیامک
                </button>

                {{-- SMS Modal --}}
                <div x-show="open" x-transition
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                     @keydown.escape.window="open = false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop>
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-bold text-gray-900">ارسال پیامک</h4>
                            <button @click="open = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Template buttons --}}
                        <div x-show="templates.length" class="mb-3">
                            <p class="text-xs text-gray-500 mb-2">قالب‌های آماده:</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="tpl in templates" :key="tpl.label">
                                    <button @click="useTemplate(tpl)"
                                            class="text-xs px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg border border-indigo-200 transition"
                                            x-text="tpl.label"></button>
                                </template>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('tickets.admin.sms.send', $ticket->id) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1">شماره موبایل</label>
                                <input type="text" name="phone" x-model="phone" required maxlength="20"
                                       placeholder="09xxxxxxxxx"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition" dir="ltr">
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-medium text-gray-700 mb-1">متن پیامک</label>
                                <textarea name="message" x-model="message" required maxlength="500" rows="4"
                                          placeholder="متن پیامک را بنویسید..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm resize-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition"></textarea>
                                <p class="text-[10px] text-gray-400 mt-1" x-text="`${message.length}/500 کاراکتر`"></p>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                        :disabled="loading || !phone.trim() || !message.trim()"
                                        class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white transition"
                                        :class="loading || !phone.trim() || !message.trim() ? 'bg-gray-300 cursor-not-allowed' : 'bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 shadow-md'">
                                    ارسال پیامک
                                </button>
                                <button type="button" @click="open = false"
                                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition">
                                    انصراف
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            {{-- بازگشت --}}
            <a href="{{ route('tickets.admin.index') }}" class="btn-ghost w-full !justify-center !text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                بازگشت به لیست
            </a>

        </div>
    </div>

</div>
@endsection
