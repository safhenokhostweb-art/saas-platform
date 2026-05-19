@extends('layouts.admin')

@section('header', 'تیکت‌های پشتیبانی من')

@section('content')
@if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif

<div class="flex flex-wrap gap-4 items-center justify-between mb-4">
    <div>
        <a href="{{ route('customer.support-tickets.create') }}" class="btn btn-primary">تیکت جدید</a>
    </div>

    <form method="GET" class="flex gap-2 items-center">
        <label for="status" class="text-sm">فیلتر وضعیت:</label>
        <select name="status" id="status" class="form-select w-auto" onchange="this.form.submit()">
            <option value="">همه</option>
            <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>باز</option>
            <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>در حال بررسی</option>
            <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>بسته</option>
        </select>
    </form>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">شماره</th>
                <th class="p-3 text-right">سایت</th>
                <th class="p-3 text-right">موضوع</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">اولویت</th>
                <th class="p-3 text-right">تاریخ</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $t)
                <tr class="border-t {{ $t->status === 'closed' ? 'text-gray-400' : '' }}">
                    <td class="p-3">{{ $t->ticket_number ?? '—' }}</td>
                    <td class="p-3">{{ $t->site->title ?? '—' }}</td>
                    <td class="p-3">{{ Str::limit($t->subject, 40) }}</td>
                    <td class="p-3">
                        @if($t->status === 'open')
                            <span class="badge bg-yellow-100 text-yellow-800">باز</span>
                        @elseif($t->status === 'closed')
                            <span class="badge bg-gray-100 text-gray-600">بسته</span>
                        @else
                            <span class="badge bg-blue-100 text-blue-800">در حال بررسی</span>
                        @endif
                    </td>
                    <td class="p-3">
                        @if($t->priority === 'high')
                            <span class="text-red-600 font-bold">بالا</span>
                        @elseif($t->priority === 'low')
                            <span class="text-gray-500">کم</span>
                        @else
                            عادی
                        @endif
                    </td>
                    <td class="p-3">{{ \App\Helpers\Jalali::format($t->created_at) }}</td>
                    <td class="p-3">
                        <a href="{{ route('customer.support-tickets.show', $t) }}" class="text-blue-600 hover:underline">مشاهده</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-4 text-center text-gray-500">تیکتی وجود ندارد. برای ثبت تیکت جدید <a href="{{ route('customer.support-tickets.create') }}" class="text-blue-600 hover:underline">اینجا</a> کلیک کنید.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $tickets->links() }}
@endsection
