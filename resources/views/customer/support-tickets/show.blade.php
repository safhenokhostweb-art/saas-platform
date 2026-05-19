@extends('layouts.admin')

@section('header', ($supportTicket->ticket_number ?? '') . ' — ' . Str::limit($supportTicket->subject, 50))

@section('content')
@if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger mb-4">{{ session('error') }}</div>
@endif

<div class="card p-4 max-w-2xl">
    <div class="flex flex-wrap gap-4 mb-4 text-sm">
        <div><strong>وضعیت:</strong>
            @if($supportTicket->status === 'open')
                <span class="badge bg-yellow-100 text-yellow-800">باز</span>
            @elseif($supportTicket->status === 'closed')
                <span class="badge bg-gray-100 text-gray-600">بسته</span>
            @else
                <span class="badge bg-blue-100 text-blue-800">در حال بررسی</span>
            @endif
        </div>
        <div><strong>اولویت:</strong>
            @if($supportTicket->priority === 'high')
                <span class="text-red-600 font-bold">بالا</span>
            @elseif($supportTicket->priority === 'low')
                <span class="text-gray-500">کم</span>
            @else
                عادی
            @endif
        </div>
        <div><strong>سایت:</strong> {{ $supportTicket->site->title ?? '—' }}</div>
        <div><strong>تاریخ:</strong> {{ \App\Helpers\Jalali::format($supportTicket->created_at) }}</div>
        @if($supportTicket->assignee)
            <div><strong>کارشناس:</strong> {{ $supportTicket->assignee->name }}</div>
        @endif
        @if($supportTicket->resolved_at)
            <div><strong>زمان حل:</strong> {{ \App\Helpers\Jalali::format($supportTicket->resolved_at) }}</div>
        @endif
    </div>

    <hr class="my-4">
    <h3 class="font-bold mb-2">پیام شما</h3>
    <div class="bg-gray-50 rounded p-3 mb-4">
        <p class="whitespace-pre-wrap">{{ $supportTicket->message }}</p>
    </div>

    @if($supportTicket->replies->isNotEmpty())
        <hr class="my-4">
        <h3 class="font-bold mb-2">پاسخ‌ها</h3>
        @foreach($supportTicket->replies as $reply)
            <div class="border rounded p-3 mb-2 {{ $reply->isFromStaff() ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200' }}">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium">
                        {{ $reply->isFromStaff() ? 'پشتیبانی' : $reply->customer_name ?? 'شما' }}
                    </span>
                    <span class="text-xs text-gray-500">{{ \App\Helpers\Jalali::format($reply->created_at) }}</span>
                </div>
                <p class="whitespace-pre-wrap text-sm">{{ $reply->message }}</p>
            </div>
        @endforeach
    @endif

    @if($supportTicket->status !== 'closed')
        <hr class="my-4">
        <h3 class="font-bold mb-2">ارسال پاسخ</h3>
        <form method="post" action="{{ route('customer.support-tickets.replies.store', $supportTicket) }}" class="mb-4">
            @csrf
            <textarea name="message" class="form-control" rows="4" required maxlength="5000" placeholder="پاسخ خود را بنویسید..."></textarea>
            <button type="submit" class="btn btn-primary mt-2">ارسال پاسخ</button>
        </form>
    @else
        <hr class="my-4">
        <div class="bg-gray-100 rounded p-3 text-center text-gray-600">
            این تیکت بسته شده است. برای پیگیری مجدد لطفاً یک تیکت جدید ثبت کنید.
        </div>
    @endif
</div>

<div class="mt-4">
    <a href="{{ route('customer.support-tickets.index') }}" class="btn btn-secondary">بازگشت به لیست تیکت‌ها</a>
</div>
@endsection
