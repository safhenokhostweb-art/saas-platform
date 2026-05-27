@extends(config('forms.layouts.admin'))

@section('header', 'مدیریت ارسال‌ها')

@section('content')
<x-core-ui::toast />
<div x-data="submissionsManager()" x-cloak>

  {{-- ═══ PAGE HEADER ═══ --}}
  <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
      <p class="text-slate-500 text-sm">مشاهده و مدیریت تمام ارسال‌های فرم‌ها</p>
    </div>
    <div class="flex items-center gap-2">
      {{-- Bulk delete (visible when rows selected) --}}
      <template x-if="selectedIds.length > 0">
        <div class="flex items-center gap-2 transition-all duration-200">
          <span class="text-xs text-indigo-600 font-medium" x-text="`${selectedIds.length} ارسال انتخاب‌شده`"></span>
        </div>
      </template>

      <form method="GET" action="{{ route('admin.forms.submissions.export') }}" id="export-form">
        <input type="hidden" name="form_id" value="{{ request('form_id') }}">
        <input type="hidden" name="site_id"  value="{{ request('site_id') }}">
        <input type="hidden" name="from"     value="{{ request('from') }}">
        <input type="hidden" name="to"       value="{{ request('to') }}">
        <input type="hidden" name="q"        value="{{ request('q') }}">
        <button type="submit"
                class="flex items-center gap-2 px-4 py-2 rounded-xl border border-indigo-200 text-indigo-600 text-sm hover:bg-indigo-50 transition focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
          </svg>
          خروجی CSV
        </button>
      </form>
    </div>
  </div>

  {{-- ═══ FILTER BAR ═══ --}}
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
    <form method="GET" action="{{ route('admin.forms.submissions.index') }}"
          class="flex flex-wrap items-end gap-3">

      <div class="min-w-0">
        <label class="block text-xs font-medium text-slate-500 mb-1">جستجو</label>
        <div class="relative">
          <svg class="w-4 h-4 text-slate-400 absolute right-3 top-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z"/></svg>
          <input type="text" name="q" value="{{ request('q') }}"
                 placeholder="IP، نام، ایمیل..."
                 class="border border-slate-200 rounded-xl pr-9 pl-4 py-2 text-sm w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 focus-visible:outline-none outline-none transition">
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">فرم</label>
        <select name="form_id"
                class="border border-slate-200 rounded-xl px-3 py-2 text-sm w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 focus-visible:outline-none outline-none transition">
          <option value="">همه فرم‌ها</option>
          @foreach($forms as $f)
            <option value="{{ $f->id }}" {{ request('form_id') == $f->id ? 'selected' : '' }}>{{ $f->title }}</option>
          @endforeach
        </select>
      </div>

      @platform
      @if(isset($sites) && $sites->count())
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">سایت</label>
        <select name="site_id"
                class="border border-slate-200 rounded-xl px-3 py-2 text-sm w-44 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 focus-visible:outline-none outline-none transition">
          <option value="">همه سایت‌ها</option>
          @foreach($sites as $s)
            <option value="{{ $s->id }}" {{ request('site_id') == $s->id ? 'selected' : '' }}>{{ $s->title ?: $s->domain }}</option>
          @endforeach
        </select>
      </div>
      @endif
      @endplatform

      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">از تاریخ</label>
        <x-persian-date-input name="from" placeholder="۱۴۰۳/۰۱/۰۱" :value="request('from')"
                              class="border border-slate-200 rounded-xl px-3 py-2 text-sm w-40 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 focus-visible:outline-none outline-none transition"/>
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">تا تاریخ</label>
        <x-persian-date-input name="to" placeholder="۱۴۰۳/۱۲/۲۹" :value="request('to')"
                              class="border border-slate-200 rounded-xl px-3 py-2 text-sm w-40 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 focus-visible:outline-none outline-none transition"/>
      </div>

      <div class="flex items-center gap-2">
        <button type="submit"
                class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-xl hover:bg-indigo-700 active:scale-95 transition focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none">
          فیلتر
        </button>
        <a href="{{ route('admin.forms.submissions.index') }}"
           class="px-4 py-2 border border-slate-200 text-slate-600 text-sm rounded-xl hover:bg-slate-50 transition focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:outline-none">
          پاک کردن
        </a>
      </div>
    </form>
  </div>

  {{-- ═══ STATS STRIP ═══ --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <x-core-ui::analytics-card label="ارسال فیلترشده" :value="number_format($submissions->total())" color="indigo" />
    <x-core-ui::analytics-card label="تعداد فرم‌ها"   :value="$forms->count()"                      color="slate" />
    <x-core-ui::analytics-card label="در هر صفحه"     :value="number_format($submissions->perPage())" color="emerald" />
    <x-core-ui::analytics-card label="تعداد صفحات"    :value="$submissions->lastPage()"              color="slate" />
  </div>

  {{-- ═══ TABLE ═══ --}}
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

    <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs text-slate-500">
      <x-core-ui::draggable-handle />
      برای تغییر ترتیب ستون‌ها، هدر جدول را بکشید.
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm text-right border-collapse">
        <thead class="sticky top-0 z-10">
          <tr class="bg-slate-50 border-b border-slate-200" id="submission-header-row">
            <th class="p-3 font-semibold text-slate-500 w-8">
              <input type="checkbox" class="rounded border-slate-300 focus:ring-indigo-400" @change="toggleSelectAll">
            </th>
            @foreach($columnOrder as $col)
              @if($col === 'id' || $col === 'form' || $col === 'site' || $col === 'date' || $col === 'ip' || in_array($col, $dataKeys, true))
              <th class="p-3 font-semibold text-slate-600 cursor-grab active:cursor-grabbing border-l border-slate-100 select-none hover:bg-slate-100 transition whitespace-nowrap"
                  data-column-id="{{ $col }}">
                <div class="flex items-center gap-1.5">
                  <span class="text-slate-300" aria-hidden="true">⠿</span>
                  @if($col === 'id')    #
                  @elseif($col === 'form')  فرم
                  @elseif($col === 'site')  سایت
                  @elseif($col === 'date')  تاریخ
                  @elseif($col === 'ip')    IP
                  @else {{ $col }}
                  @endif
                </div>
              </th>
              @endif
            @endforeach
            <th class="p-3 font-semibold text-slate-500 text-center w-16">عملیات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($submissions as $item)
          <tr class="hover:bg-slate-50 transition group"
              :class="{'bg-indigo-50': selectedIds.includes({{ $item->id }})}">
            <td class="p-3">
              <input type="checkbox" class="rounded border-slate-300 focus:ring-indigo-400"
                     value="{{ $item->id }}"
                     @change="toggleSelect({{ $item->id }})"
                     :checked="selectedIds.includes({{ $item->id }})">
            </td>
            @foreach($columnOrder as $col)
              @if($col === 'id')
              <td class="p-3 text-slate-500 font-mono text-xs">{{ $item->id }}</td>
              @elseif($col === 'form')
              <td class="p-3">
                <span class="font-medium text-slate-800">{{ $item->form?->title ?? '—' }}</span>
              </td>
              @elseif($col === 'site')
              <td class="p-3 text-slate-500 text-xs">{{ $item->site?->title ?? $item->site?->domain ?? '—' }}</td>
              @elseif($col === 'date')
              <td class="p-3 text-slate-500 text-xs whitespace-nowrap">
                @php
                    $formatter = config('forms.date_formatter');
                    echo $formatter
                        ? call_user_func($formatter, $item->created_at, 'Y/m/d H:i')
                        : \Illuminate\Support\Carbon::parse($item->created_at)->format('Y/m/d H:i');
                @endphp
              </td>
              @elseif($col === 'ip')
              <td class="p-3 text-slate-400 font-mono text-xs">{{ $item->ip ?? '—' }}</td>
              @elseif(in_array($col, $dataKeys, true))
              <td class="p-3 text-slate-700 max-w-[12.5rem]">
                <span class="truncate block">{{ is_array($item->data[$col] ?? null) ? implode(', ', $item->data[$col]) : ($item->data[$col] ?? '—') }}</span>
              </td>
              @endif
            @endforeach
            <td class="p-3 text-center">
              <button @click="openDetail({{ $item->id }})"
                      class="p-1.5 rounded-lg hover:bg-indigo-100 text-slate-400 hover:text-indigo-600 transition opacity-0 group-hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none"
                      title="مشاهده جزئیات">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                </svg>
              </button>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="{{ count($columnOrder) + 2 }}" class="p-12 text-center">
              <svg class="w-12 h-12 mx-auto text-slate-200 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75z"/></svg>
              <p class="text-slate-400 text-sm">هیچ ارسالی یافت نشد.</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
      <p class="text-xs text-slate-400">
        نمایش {{ $submissions->firstItem() ?? 0 }}–{{ $submissions->lastItem() ?? 0 }} از {{ $submissions->total() }} ارسال
      </p>
      {{ $submissions->links() }}
    </div>
  </div>

  {{-- ═══ DETAIL DRAWER ═══ --}}
  <div x-show="detailOpen"
       x-transition:enter="transition ease-out duration-250"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-end"
       @click.self="closeDetail()"
       @keydown.escape.window="closeDetail()"
       role="dialog" aria-modal="true" aria-label="جزئیات ارسال">
    <div x-show="detailOpen"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="bg-white w-full max-w-md h-full shadow-2xl flex flex-col overflow-hidden"
         dir="rtl">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-800">جزئیات ارسال</h3>
        <button @click="closeDetail()"
                class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:outline-none"
                aria-label="بستن">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-5">
        <template x-if="detailData">
          <div class="space-y-3">
            <template x-for="[key, value] in Object.entries(detailData.data || {})" :key="key">
              <div class="bg-slate-50 rounded-xl p-3">
                <p class="text-xs font-medium text-slate-500 mb-1" x-text="key"></p>
                <p class="text-sm text-slate-800 break-words"
                   x-text="Array.isArray(value) ? value.join(', ') : (value || '—')"></p>
              </div>
            </template>
            <div class="border-t border-slate-100 pt-3 mt-3 space-y-2 text-xs text-slate-400">
              <p>فرم: <span class="text-slate-600" x-text="detailData.form_title"></span></p>
              <p>تاریخ: <span class="text-slate-600" x-text="detailData.created_at"></span></p>
              <p>IP: <span class="font-mono text-slate-600" x-text="detailData.ip || '—'"></span></p>
            </div>
          </div>
        </template>
        <template x-if="!detailData">
          <div class="flex items-center justify-center h-32">
            <svg class="w-6 h-6 text-slate-300 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
          </div>
        </template>
      </div>
    </div>
  </div>

</div>
@endsection

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
        jQuery('.persian-date-input').persianDatepicker({ format: 'YYYY/MM/DD', persianDigit: true, initialValueType: 'persian', autoClose: true });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const row = document.getElementById('submission-header-row');
    if (row && typeof Sortable !== 'undefined') {
        new Sortable(row, {
            animation: 150,
            handle: 'th[data-column-id]',
            filter: 'th:not([data-column-id])',
            onEnd() {
                const order = [];
                row.querySelectorAll('th[data-column-id]').forEach(th => order.push(th.dataset.columnId));
                fetch('{{ route("admin.forms.submissions.column-order") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ order }),
                }).then(() => window.location.reload());
            },
        });
    }
});
</script>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('submissionsManager', () => ({
    selectedIds: [],
    detailOpen:  false,
    detailData:  null,

    toggleSelectAll(e) {
      this.selectedIds = e.target.checked
        ? @json($submissions->pluck('id'))
        : [];
    },

    toggleSelect(id) {
      if (this.selectedIds.includes(id)) {
        this.selectedIds = this.selectedIds.filter(i => i !== id);
      } else {
        this.selectedIds.push(id);
      }
    },

    async openDetail(id) {
      this.detailOpen = true;
      this.detailData = null;
      const submission = @json($submissions->items()).find(s => s.id === id);
      if (submission) {
        this.detailData = {
          data:       submission.data || {},
          form_title: submission.form ? submission.form.title : '—',
          created_at: submission.created_at,
          ip:         submission.ip,
        };
      }
    },

    closeDetail() {
      this.detailOpen = false;
      setTimeout(() => { this.detailData = null; }, 300);
    },
  }));
});
</script>
@endpush
