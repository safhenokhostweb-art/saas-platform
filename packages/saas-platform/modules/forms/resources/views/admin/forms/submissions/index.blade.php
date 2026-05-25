@extends(config('forms.layouts.admin'))

@section('header', 'گزارش ارسال فرم‌ها')

@section('content')
<div class="mb-6 flex flex-wrap items-end gap-3">
    <form method="GET" action="{{ route('admin.forms.submissions.index') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">جستجو</label>
            <input type="text" name="q" class="border rounded-xl px-4 py-2 w-40 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                   placeholder="فرم، سایت، IP" value="{{ request('q') }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">فرم</label>
            <select name="form_id" class="border rounded-xl px-4 py-2 w-48 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <option value="">همه</option>
                @foreach($forms as $f)
                    <option value="{{ $f->id }}" {{ request('form_id') == $f->id ? 'selected' : '' }}>{{ $f->title }}</option>
                @endforeach
            </select>
        </div>
        @platform
        @if(isset($sites) && $sites->count())
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">سایت</label>
            <select name="site_id" class="border rounded-xl px-4 py-2 w-48 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <option value="">همه</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}" {{ request('site_id') == $s->id ? 'selected' : '' }}>{{ $s->title ?: $s->domain }}</option>
                @endforeach
            </select>
        </div>
        @endif
        @endplatform

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">از تاریخ (شمسی)</label>
            <x-persian-date-input name="from" placeholder="۱۴۰۳/۰۱/۰۱" :value="request('from')"
                                  class="border rounded-xl px-4 py-2 w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">تا تاریخ (شمسی)</label>
            <x-persian-date-input name="to" placeholder="۱۴۰۳/۱۲/۲۹" :value="request('to')"
                                  class="border rounded-xl px-4 py-2 w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" />
        </div>
        <button type="submit"
                class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-xl hover:opacity-90 transition">
            فیلتر
        </button>
        <a href="{{ route('admin.forms.submissions.index') }}"
           class="border border-gray-300 text-gray-700 px-4 py-2 rounded-xl hover:bg-gray-50 transition">
            پاک کردن
        </a>
    </form>
    <form method="GET" action="{{ route('admin.forms.submissions.export') }}" class="mr-auto flex items-end gap-2">
        <input type="hidden" name="form_id" value="{{ request('form_id') }}">
        <input type="hidden" name="site_id" value="{{ request('site_id') }}">
        <input type="hidden" name="from" value="{{ request('from') }}">
        <input type="hidden" name="to" value="{{ request('to') }}">
        <input type="hidden" name="q" value="{{ request('q') }}">
        <button type="submit"
                class="border border-indigo-600 text-indigo-600 px-4 py-2 rounded-xl hover:bg-indigo-50 transition inline-flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
            خروجی اکسل (CSV)
        </button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
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
                            <th class="p-3 font-bold cursor-grab active:cursor-grabbing border-l border-gray-200 select-none hover:bg-gray-100 transition-colors"
                                data-column-id="{{ $col }}" title="کشیدن برای تغییر ترتیب">
                                <span class="text-gray-400 align-middle ml-1" aria-hidden="true">⋮⋮</span>
                                @if($col === 'id') #
                                @elseif($col === 'form') فرم
                                @elseif($col === 'site') سایت
                                @elseif($col === 'date') تاریخ
                                @elseif($col === 'ip') IP
                                @else {{ $col }}
                                @endif
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
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
