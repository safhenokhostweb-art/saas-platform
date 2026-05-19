@extends('layouts.admin')

@section('header', 'تیکت‌های پشتیبانی')

@section('content')
@include('admin.support-tickets.guide')
<x-module-features module="support" />
@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif

<p class="mb-4"><a href="{{ route('admin.support-tickets.create') }}" class="btn btn-primary">ثبت تیکت جدید</a></p>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">شماره</th>
                <th class="p-3 text-right">سایت</th>
                <th class="p-3 text-right">موضوع</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">تاریخ</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $t)
                <tr class="border-t {{ app(\App\Services\SupportSlaService::class)->hasAnyBreach($t) ? 'bg-red-50' : '' }}">
                    <td class="p-3">{{ $t->ticket_number ?? '—' }}</td>
                    <td class="p-3">{{ $t->site->title ?? '—' }}</td>
                    <td class="p-3">{{ Str::limit($t->subject, 40) }}</td>
                    <td class="p-3">{{ $t->status === 'open' ? 'باز' : ($t->status === 'closed' ? 'بسته' : 'در حال بررسی') }}</td>
                    <td class="p-3">{{ \App\Helpers\Jalali::format($t->created_at) }}</td>
                    <td class="p-3"><a href="{{ route('admin.support-tickets.show', $t) }}" class="text-blue-600 hover:underline">مشاهده</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-center text-gray-500">تیکتی وجود ندارد.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $tickets->links() }}
@endsection
