@push('head')
@asset_module('tickets', 'css/tickets.css')
@endpush

@extends('admin.layout')
@section('title', 'تیکت ' . $ticket->ticket_number)
@section('header', 'تیکت ' . $ticket->ticket_number)

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

    {{-- Ticket Details --}}
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">{{ $ticket->subject }}</h2>
                <p class="text-sm text-gray-500 mt-1">
                    <span class="font-mono text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-lg text-xs">{{ $ticket->ticket_number }}</span>
                    &mdash; {{ $ticket->created_at->format('Y/m/d H:i') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($ticket->status === 'open')
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">
                    <span class="w-2 h-2 rounded-full bg-yellow-500"></span> باز
                </span>
                @elseif($ticket->status === 'in_progress')
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> در حال بررسی
                </span>
                @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg> بسته
                </span>
                @endif
                @php
                $p = $ticket->priority ?? 'normal';
                $pLabel = $p === 'high' ? 'بالا' : ($p === 'low' ? 'کم' : 'عادی');
                $pBg = $p === 'high' ? 'bg-red-50 text-red-700 border-red-200' : ($p === 'low' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-600 border-blue-200');
                @endphp
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium {{ $pBg }}">
                    <span class="w-2 h-2 rounded-full"></span> {{ $pLabel }}
                </span>
            </div>
        </div>

        <div class="prose prose-sm max-w-none text-gray-700 bg-gray-50 p-4 rounded-xl">
            {{ nl2br(e($ticket->description)) }}
        </div>

        @if($ticket->attachments)
        <div class="mt-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">فایل‌های ضمیمه</h4>
            <div class="flex flex-wrap gap-2">
                @foreach($ticket->attachments as $file)
                <a href="{{ Storage::url($file['path']) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                    </svg>
                    {{ $file['name'] }}
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Ticket Info --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4 pt-4 border-t border-gray-100">
            @if($ticket->site)
            <div>
                <span class="text-xs text-gray-500">سایت</span>
                <p class="text-sm font-medium text-gray-800">{{ $ticket->site->title }}</p>
            </div>
            @endif
            @if($ticket->assignee)
            <div>
                <span class="text-xs text-gray-500">کارشناس</span>
                <p class="text-sm font-medium text-gray-800">{{ $ticket->assignee->name }}</p>
            </div>
            @endif
            @if($ticket->category)
            <div>
                <span class="text-xs text-gray-500">دسته‌بندی</span>
                <p class="text-sm font-medium text-gray-800">{{ $ticket->category }}</p>
            </div>
            @endif
            @if($ticket->customer_name)
            <div>
                <span class="text-xs text-gray-500">مشتری</span>
                <p class="text-sm font-medium text-gray-800">{{ $ticket->customer_name }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Status Actions --}}
    @if($ticket->status !== 'closed')
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
        <h3 class="text-sm font-medium text-gray-700 mb-3">عملیات</h3>
        <div class="flex flex-wrap gap-2">
            @if($ticket->status === 'open')
            <form action="{{ route('admin.tickets.update-status', $ticket->id) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="in_progress">
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    شروع بررسی
                </button>
            </form>
            @endif
            <form action="{{ route('admin.tickets.update-status', $ticket->id) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="closed">
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-medium bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    بستن تیکت
                </button>
            </form>
        </div>
    </div>
    @endif

    {{-- Replies --}}
    <div class="space-y-4">
        <h3 class="text-lg font-bold text-gray-900">پاسخ‌ها</h3>

        @forelse($ticket->replies as $reply)
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 {{ $reply->isFromStaff() ? 'border-r-4 border-r-indigo-400' : '' }}">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-800">{{ $reply->user?->name ?? $reply->customer_name ?? 'کاربر' }}</span>
                    @if($reply->isFromStaff())
                    <span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full font-medium">کارشناس</span>
                    @endif
                </div>
                <span class="text-xs text-gray-500">{{ $reply->created_at->format('Y/m/d H:i') }}</span>
            </div>
            <div class="prose prose-sm max-w-none text-gray-700">
                {{ nl2br(e($reply->message)) }}
            </div>
            @if($reply->attachments)
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach($reply->attachments as $file)
                <a href="{{ Storage::url($file['path']) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                    </svg>
                    {{ $file['name'] }}
                </a>
                @endforeach
            </div>
            @endif
        </div>
        @empty
        <div class="text-center py-8">
            <span class="text-gray-400">پاسخی ثبت نشده است.</span>
        </div>
        @endforelse
    </div>

    {{-- Reply Form --}}
    @if($ticket->status !== 'closed')
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
        <h3 class="text-sm font-medium text-gray-700 mb-3">ثبت پاسخ</h3>
        <form action="{{ route('admin.tickets.replies.store', $ticket->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="space-y-4">
                <textarea name="message" rows="4" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all" placeholder="پاسخ خود را وارد کنید..." required></textarea>
                <div class="flex items-center gap-3">
                    <input type="file" name="attachments[]" multiple class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 shadow-sm transition-all">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                        </svg>
                        ارسال پاسخ
                    </button>
                </div>
            </div>
        </form>
    </div>
    @endif

</div>
@endsection
