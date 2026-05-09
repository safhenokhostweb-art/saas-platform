@extends('layouts.admin')

@section('header', 'گزارش ارسال فرم‌ها')

@section('content')

<div class="mb-6 flex flex-wrap items-end gap-3">
    <form method="GET" action="{{ route('admin.forms.submissions.index') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="form-label text-xs">جستجو</label>
            <input type="text" name="q" class="form-control w-40" placeholder="فرم، سایت، IP" value="{{ request('q') }}">
        </div>
        <div>
            <label class="form-label text-xs">فرم</label>
            <select name="form_id" class="form-select w-48">
                <option value="">همه</option>
                @foreach($forms as $f)
                    <option value="{{ $f->id }}" {{ request('form_id') == $f->id ? 'selected' : '' }}>{{ $f->title }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label text-xs">سایت</label>
            <select name="site_id" class="form-select w-48">
                <option value="">همه</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}" {{ request('site_id') == $s->id ? 'selected' : '' }}>{{ $s->title ?: $s->domain }}</option>
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
        <a href="{{ route('admin.forms.submissions.index') }}" class="btn btn-secondary">پاک کردن</a>
    </form>
    <form method="GET" action="{{ route('admin.forms.submissions.export') }}" class="mr-auto flex items-end gap-2">
        <input type="hidden" name="form_id" value="{{ request('form_id') }}">
        <input type="hidden" name="site_id" value="{{ request('site_id') }}">
        <input type="hidden" name="from" value="{{ request('from') }}">
        <input type="hidden" name="to" value="{{ request('to') }}">
        <input type="hidden" name="q" value="{{ request('q') }}">
        <button type="submit" class="btn btn-outline-primary">خروجی اکسل (CSV)</button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <p class="text-gray-600 text-sm p-3 border-b bg-amber-50 border-amber-100">
        <span class="inline-flex items-center gap-1 ml-1" aria-hidden="true">⋮⋮</span>
        <strong>درگ اند دراپ:</strong> روی هر سرستون زیر کلیک کرده و بکشید تا ترتیب ستون‌ها را عوض کنید. ترتیب جدید در جدول و خروجی CSV اعمال می‌شود.
    </p>
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200" id="submission-header-row">
                    @foreach($columnOrder as $col)
                        @if($col === 'id' || $col === 'form' || $col === 'site' || $col === 'date' || $col === 'ip' || in_array($col, $dataKeys, true))
                            <th class="p-3 font-bold cursor-grab active:cursor-grabbing border-l border-gray-200 select-none hover:bg-gray-100 transition-colors" data-column-id="{{ $col }}" title="کشیدن برای تغییر ترتیب">
                                <span class="text-gray-400 align-middle ml-1" aria-hidden="true">⋮⋮</span>
                                @if($col === 'id') # @elseif($col === 'form') فرم @elseif($col === 'site') سایت @elseif($col === 'date') تاریخ @elseif($col === 'ip') IP @else {{ $col }} @endif
                            </th>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($submissions as $item)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        @foreach($columnOrder as $col)
                            @if($col === 'id')
                                <td class="p-3">{{ $item->id }}</td>
                            @elseif($col === 'form')
                                <td class="p-3">{{ $item->form?->title ?? '—' }}</td>
                            @elseif($col === 'site')
                                <td class="p-3">{{ $item->site?->title ?? $item->site?->domain ?? '—' }}</td>
                            @elseif($col === 'date')
                                <td class="p-3 text-sm">{{ \App\Helpers\Jalali::formatFa($item->created_at, 'Y/m/d H:i') }}</td>
                            @elseif($col === 'ip')
                                <td class="p-3">{{ $item->ip ?? '—' }}</td>
                            @elseif(in_array($col, $dataKeys, true))
                                <td class="p-3 text-sm">{{ is_array($item->data[$col] ?? null) ? json_encode($item->data[$col]) : ($item->data[$col] ?? '—') }}</td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columnOrder) }}" class="p-6 text-center text-gray-500">ارسالی یافت نشد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-gray-200">
        {{ $submissions->links() }}
    </div>
</div>

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/persian-date/persian-date.min.js') }}"></script>
<script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
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
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var row = document.getElementById('submission-header-row');
    if (row && typeof Sortable !== 'undefined') {
        new Sortable(row, {
            animation: 150,
            handle: 'th',
            onEnd: function(ev) {
                var order = [];
                row.querySelectorAll('th[data-column-id]').forEach(function(th) {
                    order.push(th.getAttribute('data-column-id'));
                });
                fetch('{{ route("admin.forms.submissions.column-order") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ order: order })
                }).then(function() { window.location.reload(); });
            }
        });
    }
});
</script>
@endpush
@endsection
