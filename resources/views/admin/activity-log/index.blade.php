@extends('layouts.admin')

@section('header', 'لاگ فعالیت')

@section('content')
@include('admin.activity-log.guide')
@if(session('error'))
<div class="alert alert-danger mb-6">{{ session('error') }}</div>
@endif

<div class="mb-6 flex flex-wrap items-end gap-3">
    <form method="GET" action="{{ route('admin.activity-log.index') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="form-label text-xs">کاربر</label>
            <select name="user_id" class="form-select w-40">
                <option value="">همه</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->mobile }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label text-xs">نوع عملیات</label>
            <select name="action" class="form-select w-48">
                <option value="">همه</option>
                @foreach($actionOptions ?? [] as $key => $label)
                <option value="{{ $key }}" {{ request('action') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label text-xs">از تاریخ (شمسی)</label>
            <x-persian-date-input name="from" placeholder="۱۴۰۳/۰۱/۰۱" :value="request('from')" class="form-control w-44" />
        </div>
        <div>
            <label class="form-label text-xs">تا تاریخ (شمسی)</label>
            <x-persian-date-input name="to" placeholder="۱۴۰۳/۱۲/۲۹" :value="request('to')" class="form-control w-44" />
        </div>
        <button type="submit" class="btn btn-primary">فیلتر</button>
        <a href="{{ route('admin.activity-log.index') }}" class="btn btn-secondary">پاک کردن فیلتر</a>
    </form>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="p-3 font-bold">#</th>
                    <th class="p-3 font-bold">زمان</th>
                    <th class="p-3 font-bold">کاربر</th>
                    <th class="p-3 font-bold">عملیات</th>
                    <th class="p-3 font-bold">توضیح</th>
                    <th class="p-3 font-bold">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="p-3">{{ $log->id }}</td>
                    <td class="p-3 text-sm text-gray-600" title="{{ \App\Helpers\Jalali::formatFa($log->created_at, 'Y/m/d H:i:s') }}">{{ \App\Helpers\Jalali::formatFa($log->created_at, 'Y/m/d H:i') }}</td>
                    <td class="p-3">
                        @if($log->user)
                        <span class="font-medium">{{ $log->user->mobile }}</span>
                        @if($log->user->profile && $log->user->profile->full_name)
                        <span class="text-gray-500 text-xs">({{ $log->user->profile->full_name }})</span>
                        @endif
                        @else
                        <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="p-3 text-sm">{{ \App\Models\ActivityLog::actionLabel($log->action) }}</td>
                    <td class="p-3 text-sm">{{ $log->description ?? '—' }}</td>
                    <td class="p-3 text-xs text-gray-500">{{ $log->ip_address ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="p-6 text-center text-gray-500">رکوردی یافت نشد.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($logs) && method_exists($logs, 'links'))
    <div class="p-3 border-t border-gray-200">
        {{ $logs->links() }}
    </div>
    @endif
</div>

@push('head')
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
@endpush
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({
            format: 'YYYY/MM/DD',
            persianDigit: true,
            initialValueType: 'persian'
        });
    }
});
</script>
@endpush
@endsection
