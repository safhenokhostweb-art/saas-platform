@extends('layouts.admin')

@section('header', ($supportTicket->ticket_number ?? '') . ' — ' . Str::limit($supportTicket->subject, 50))

@section('content')
@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif

<div class="card p-4 max-w-2xl">
    <p><strong>شماره تیکت:</strong> {{ $supportTicket->ticket_number ?? '—' }}</p>
    <p><strong>سایت:</strong> {{ $supportTicket->site->title ?? '—' }}</p>
    <p><strong>سطح پشتیبانی:</strong> {{ $supportTicket->support_level ?? 'basic' }}</p>
    <p><strong>ایمیل:</strong> {{ $supportTicket->customer_email }}</p>
    <p><strong>نام:</strong> {{ $supportTicket->customer_name ?? '—' }}</p>
    <p><strong>وضعیت:</strong> {{ $supportTicket->status }}</p>
    <p><strong>تخصیص:</strong> {{ $supportTicket->assignee->name ?? '—' }}</p>
    <p><strong>تاریخ:</strong> {{ \App\Helpers\Jalali::format($supportTicket->created_at) }}</p>
    @php $sla = app(\App\Services\SupportSlaService::class); @endphp
    @if($sla->hasAnyBreach($supportTicket))
        <p class="text-red-600"><strong>SLA:</strong> نقض شده</p>
    @endif
    <hr class="my-4">
    <h3 class="font-bold mb-2">پیام</h3>
    <p class="whitespace-pre-wrap">{{ $supportTicket->message }}</p>

    @if($supportTicket->replies->isNotEmpty())
        <hr class="my-4">
        <h3 class="font-bold mb-2">پاسخ‌ها</h3>
        @foreach($supportTicket->replies as $reply)
            <div class="border rounded p-3 mb-2 {{ $reply->isFromStaff() ? 'bg-blue-50' : '' }}">
                <p class="text-sm text-gray-600">{{ $reply->user->name ?? 'پشتیبانی' }} — {{ \App\Helpers\Jalali::format($reply->created_at) }}</p>
                <p class="whitespace-pre-wrap">{{ $reply->message }}</p>
            </div>
        @endforeach
    @endif

    <hr class="my-4">
    <form method="post" action="{{ route('admin.support-tickets.replies.store', $supportTicket) }}" class="mb-4">
        @csrf
        <label>پاسخ (پشتیبانی):</label>
        <textarea name="message" class="form-control" rows="3" required></textarea>
        <button type="submit" class="btn btn-primary mt-2">ثبت پاسخ</button>
    </form>

    <form method="post" action="{{ route('admin.support-tickets.update-status', $supportTicket) }}" class="mb-4">
        @csrf
        @method('PATCH')
        <label>تغییر وضعیت:</label>
        <select name="status" class="form-control w-auto inline-block">
            <option value="open" {{ $supportTicket->status === 'open' ? 'selected' : '' }}>باز</option>
            <option value="in_progress" {{ $supportTicket->status === 'in_progress' ? 'selected' : '' }}>در حال بررسی</option>
            <option value="closed" {{ $supportTicket->status === 'closed' ? 'selected' : '' }}>بسته</option>
        </select>
        <button type="submit" class="btn btn-primary">ذخیره</button>
    </form>

    <form method="post" action="{{ route('admin.support-tickets.assign', $supportTicket) }}">
        @csrf
        @method('PATCH')
        <label>تخصیص به:</label>
        <select name="assigned_to" class="form-control w-auto inline-block">
            <option value="">—</option>
            @foreach(\App\Models\User::orderBy('name')->get(['id','name']) as $u)
                <option value="{{ $u->id }}" {{ (int)$supportTicket->assigned_to === (int)$u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-secondary">تخصیص</button>
    </form>
</div>
<a href="{{ route('admin.support-tickets.index') }}" class="btn btn-secondary mt-4">بازگشت</a>
@endsection
