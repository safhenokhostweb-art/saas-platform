{{-- @deprecated PHASE 7.15 — Use tickets::admin.tickets.index (canonical package view). Route to: tickets.admin.index --}}
@extends('layouts.admin')

@section('header', 'تیکت‌های پشتیبانی')

@push('head')
<style>
.ticket-stat-card{background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(255,255,255,0.65));backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.3);border-radius:1rem;padding:1.25rem;transition:all 0.3s ease;box-shadow:0 4px 16px rgba(15,23,42,0.05)}
.ticket-stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(99,102,241,0.1)}
.ticket-stat-card__icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem}
.glass-filter-bar{background:rgba(255,255,255,0.75);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.2);border-radius:1rem;padding:1rem 1.5rem;box-shadow:0 8px 24px rgba(15,23,42,0.04)}
.ticket-row{transition:all 0.2s ease}
.ticket-row:hover{background:rgba(99,102,241,0.03)}
.ticket-row-sla-breach{background:rgba(239,68,68,0.04);border-right:3px solid #ef4444}
.ticket-row-sla-breach:hover{background:rgba(239,68,68,0.07)}
.status-badge{display:inline-flex;align-items:center;gap:0.4rem;padding:0.3rem 0.85rem;border-radius:9999px;font-size:0.75rem;font-weight:600;white-space:nowrap;border:1px solid transparent;transition:all 0.2s ease}
.status-badge:hover{transform:scale(1.05)}
.priority-dot{display:inline-block;width:8px;height:8px;border-radius:50%;animation:pulse-priority 2s infinite}
@keyframes pulse-priority{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.5;transform:scale(1.3)}}
.btn-gradient{display:inline-flex;align-items:center;gap:0.5rem;padding:0.65rem 1.5rem;border-radius:0.75rem;font-weight:600;font-size:0.875rem;transition:all 0.25s ease;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.25)}
.btn-gradient:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,0.35)}
</style>
@endpush

@section('content')

<!-- SUPPORT-REAL-FILE: c:\laragon\www\saas-platform\resources\views\admin\support-tickets\index.blade.php -->


@php
$openCount = $tickets->filter(fn($t) => $t->status === 'open')->count();
$inProgressCount = $tickets->filter(fn($t) => $t->status === 'in_progress')->count();
$closedCount = $tickets->filter(fn($t) => $t->status === 'closed')->count();
$breachCount = $tickets->filter(fn($t) => app(\App\Services\SupportSlaService::class)->hasAnyBreach($t))->count();
@endphp

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-8">
    <div class="ticket-stat-card"><div class="flex items-center gap-3"><div class="ticket-stat-card__icon bg-indigo-100 text-indigo-700">📫</div><div><div class="text-2xl font-bold text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($tickets->count()) }}</div><div class="text-xs text-gray-500 mt-0.5">کل تیکت‌ها</div></div></div></div>
    <div class="ticket-stat-card"><div class="flex items-center gap-3"><div class="ticket-stat-card__icon bg-yellow-100 text-yellow-700">🟡</div><div><div class="text-2xl font-bold text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($openCount) }}</div><div class="text-xs text-gray-500 mt-0.5">باز</div></div></div></div>
    <div class="ticket-stat-card"><div class="flex items-center gap-3"><div class="ticket-stat-card__icon bg-blue-100 text-blue-700">🔵</div><div><div class="text-2xl font-bold text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($inProgressCount) }}</div><div class="text-xs text-gray-500 mt-0.5">در حال بررسی</div></div></div></div>
    <div class="ticket-stat-card"><div class="flex items-center gap-3"><div class="ticket-stat-card__icon bg-green-100 text-green-700">✅</div><div><div class="text-2xl font-bold text-gray-900">{{ \App\Helpers\NumberHelper::formatFa($closedCount) }}</div><div class="text-xs text-gray-500 mt-0.5">بسته شده</div></div></div></div>
    <div class="ticket-stat-card {{ $breachCount > 0 ? 'ring-2 ring-red-300' : '' }}"><div class="flex items-center gap-3"><div class="ticket-stat-card__icon {{ $breachCount > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">⚠️</div><div><div class="text-2xl font-bold {{ $breachCount > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ \App\Helpers\NumberHelper::formatFa($breachCount) }}</div><div class="text-xs {{ $breachCount > 0 ? 'text-red-600' : 'text-gray-500' }} mt-0.5">SLA نقض شده</div></div></div></div>
</div>

{{-- Actions --}}
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <a href="{{ route('admin.support-tickets.create') }}" class="btn-gradient">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        ثبت تیکت جدید
    </a>
</div>

{{-- Filter Bar --}}
<div class="glass-filter-bar mb-6" x-data="{ search: '', status: '', priority: '' }">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
        <div class="relative flex-1 w-full sm:w-auto">
            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" placeholder="جستجوی تیکت‌ها..." class="w-full pr-10 pl-4 py-2.5 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
        </div>
        <select x-model="status" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
            <option value="">همه وضعیت‌ها</option>
            <option value="open">باز</option>
            <option value="in_progress">در حال بررسی</option>
            <option value="closed">بسته</option>
        </select>
        <select x-model="priority" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
            <option value="">همه اولویت‌ها</option>
            <option value="high">بالا</option>
            <option value="normal">عادی</option>
            <option value="low">کم</option>
        </select>
    </div>
</div>

@php
$slaService = app(\App\Services\SupportSlaService::class);
@endphp

{{-- Tickets Table --}}
<div class="glass-card-strong overflow-hidden rounded-2xl">
<div class="overflow-x-auto scrollbar-thin">
<table class="w-full text-right border-collapse glass-table">
<thead><tr><th class="p-3 pr-5">شماره</th><th class="p-3">سایت</th><th class="p-3">موضوع</th><th class="p-3">وضعیت</th><th class="p-3">اولویت</th><th class="p-3">تاریخ</th><th class="p-3 pl-5">عملیات</th></tr></thead>
<tbody>
@forelse($tickets as $t)
@php $isBreach = $slaService->hasAnyBreach($t); @endphp
<tr class="ticket-row border-b border-gray-100 {{ $isBreach ? 'ticket-row-sla-breach' : '' }}">
<td class="p-3 pr-5"><span class="font-mono font-bold text-indigo-700 text-xs bg-indigo-50 px-2 py-1 rounded-lg">{{ $t->ticket_number ?? '—' }}</span></td>
<td class="p-3"><div class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span><span class="text-sm text-gray-700 font-medium">{{ $t->site->title ?? '—' }}</span></div></td>
<td class="p-3"><span class="text-sm font-medium text-gray-800">{{ Str::limit($t->subject, 50) }}</span>@if($t->assigned_to) <span class="block text-[10px] text-gray-400 mt-0.5">👤 {{ $t->assignee->name ?? '—' }}</span> @endif</td>
<td class="p-3">
@if($t->status === 'open')
<span class="status-badge bg-yellow-50 text-yellow-700 border-yellow-200"><span class="w-1.5 h-1.5 rounded-full bg-yellow-500 animate-pulse"></span> باز</span>
@elseif($t->status === 'in_progress')
<span class="status-badge bg-blue-50 text-blue-700 border-blue-200"><span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span> در حال بررسی</span>
@else
<span class="status-badge bg-gray-50 text-gray-600 border-gray-200"><svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> بسته</span>
@endif
</td>
<td class="p-3">
@if($t->priority === 'high')
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200"><span class="priority-dot bg-red-500"></span> بالا</span>
@elseif($t->priority === 'low')
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> کم</span>
@else
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-600 border border-blue-200"><span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span> عادی</span>
@endif
@if($isBreach) <span class="block text-[10px] text-red-600 font-bold mt-1">⛔ SLA نقض شده</span> @endif
</td>
<td class="p-3 text-sm text-gray-500">{{ \App\Helpers\Jalali::formatFa($t->created_at) }}</td>
<td class="p-3 pl-5">
<a href="{{ route('admin.support-tickets.show', $t) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-all hover:shadow-sm">
<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
مشاهده
</a>
</td>
</tr>
@empty
<tr><td colspan="7" class="text-center py-12"><div class="flex flex-col items-center gap-3"><span class="text-5xl">📭</span><span class="text-gray-400 font-medium">هیچ تیکتی یافت نشد</span><p class="text-gray-400 text-sm">هنوز تیکتی ثبت نشده است.</p><a href="{{ route('admin.support-tickets.create') }}" class="btn-gradient !text-sm !py-2 !px-4 mt-2"><svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg> ثبت تیکت جدید</a></div></td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

{{-- Pagination --}}
<div class="mt-6 flex justify-center">
{{ $tickets->links() }}
</div>

<x-module-features module="support" />
@include('admin.support-tickets.guide')
@endsection
