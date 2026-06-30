@extends('layouts.admin')

@section('header', 'تاریخچه نصب‌ها')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">تاریخچه نصب‌ها</h1>
        <p class="text-sm text-gray-500">تمام نصب‌های انجام‌شده، در حال اجرا و ناموفق</p>
    </div>
    <a href="{{ route('admin.infrastructure.customer-deployment.wizard') }}" class="btn btn-primary text-sm">
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        نصب جدید
    </a>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="form-label text-xs">جستجو</label>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="شرکت، دامنه، ایمیل..." class="form-control text-sm" style="min-width:200px">
    </div>
    <div>
        <label class="form-label text-xs">وضعیت</label>
        <select name="status" class="form-select text-sm">
            <option value="">همه</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>در صف</option>
            <option value="running" {{ request('status') === 'running' ? 'selected' : '' }}>در حال اجرا</option>
            <option value="complete" {{ request('status') === 'complete' ? 'selected' : '' }}>موفق</option>
            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>ناموفق</option>
            <option value="rolled_back" {{ request('status') === 'rolled_back' ? 'selected' : '' }}>بازگشت‌خورده</option>
        </select>
    </div>
    <div>
        <label class="form-label text-xs">سرور</label>
        <select name="server_id" class="form-select text-sm">
            <option value="">همه سرورها</option>
            @foreach($servers as $s)
                <option value="{{ $s->id }}" {{ request('server_id') == $s->id ? 'selected' : '' }}>{{ $s->label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label text-xs">از تاریخ</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control text-sm">
    </div>
    <div>
        <label class="form-label text-xs">تا تاریخ</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control text-sm">
    </div>
    <button type="submit" class="btn btn-primary text-sm">اعمال</button>
    @if(request()->hasAny(['search', 'status', 'server_id', 'date_from', 'date_to']))
        <a href="{{ route('admin.infrastructure.customer-deployment.history') }}" class="btn btn-outline-secondary text-sm">پاک‌کردن</a>
    @endif
</form>

{{-- Table --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    @if($installations->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <p class="font-medium">هیچ نصبی یافت نشد</p>
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">#</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">مشتری</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">دامنه</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">سرور</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">حالت</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">وضعیت</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">مرحله جاری</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">تاریخ</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">مدت</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($installations as $inst)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $inst->id }}</td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $inst->customer_company }}</div>
                        <div class="text-xs text-gray-400">{{ $inst->customer_email }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-600 max-w-[140px]">
                        <span class="truncate block">{{ $inst->customer_domain }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $inst->remoteServer?->label ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-mono">{{ str_replace('MODE_', '', $inst->install_mode) }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @php $statusMap = ['pending'=>['در صف','status-pending'], 'running'=>['در حال اجرا','status-running'], 'complete'=>['موفق','status-complete'], 'failed'=>['ناموفق','status-failed'], 'rolling_back'=>['بازگشت','status-rolling_back'], 'rolled_back'=>['بازگشت‌خورد','status-rolled_back'], 'rollback_failed'=>['خطای بازگشت','status-rollback_failed']]; @endphp
                        <span class="status-badge {{ $statusMap[$inst->status][1] ?? 'status-pending' }}">{{ $statusMap[$inst->status][0] ?? $inst->status }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $inst->current_stage ? str_replace('_', ' ', $inst->current_stage) : '—' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $inst->created_at->format('Y/m/d') }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        @if($inst->completed_at && $inst->started_at)
                            {{ gmdate('H:i:s', $inst->completed_at->diffInSeconds($inst->started_at)) }}
                        @elseif($inst->started_at && $inst->isRunning())
                            <span class="text-indigo-600">{{ $inst->started_at->diffForHumans() }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 flex items-center gap-3">
                        <a href="{{ route('admin.infrastructure.customer-deployment.installations.show', $inst) }}" class="text-indigo-600 hover:underline text-xs font-medium">جزئیات</a>
                        @if(in_array($inst->status, ['rolled_back', 'failed', 'rollback_failed']))
                        <form method="POST"
                              action="{{ route('admin.infrastructure.customer-deployment.installations.force-destroy', $inst) }}"
                              onsubmit="return confirm('حذف دستی «{{ $inst->customer_domain }}»\n\nرکوردهای دیتابیس بدون اتصال SSH حذف می‌شوند.\nادامه می‌دهید؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="حذف دستی"
                                    style="display:inline-flex;align-items:center;padding:.2rem .5rem;background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db;border-radius:.4rem;font-size:.72rem;font-weight:600;cursor:pointer;gap:.3rem"
                                    onmouseover="this.style.background='#fee2e2';this.style.color='#991b1b';this.style.borderColor='#fca5a5'"
                                    onmouseout="this.style.background='#f3f4f6';this.style.color='#6b7280';this.style.borderColor='#d1d5db'">
                                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                حذف
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="px-4 py-3 border-t border-gray-100">
            {{ $installations->links() }}
        </div>
    @endif
</div>

@push('head')
<style>
.status-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .65rem;border-radius:9999px;font-size:.75rem;font-weight:600}
.status-pending{background:#fef9c3;color:#92400e}
.status-running{background:#dbeafe;color:#1e40af}
.status-rolling_back{background:#ede9fe;color:#5b21b6}
.status-complete{background:#d1fae5;color:#065f46}
.status-failed{background:#fee2e2;color:#991b1b}
.status-rollback_failed{background:#fce7f3;color:#9d174d}
.status-rolled_back{background:#f3f4f6;color:#374151}
</style>
@endpush

@endsection
