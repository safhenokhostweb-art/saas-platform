@extends('layouts.admin')

@section('title', 'امتیازدهی قالب‌ها')

@section('content')
<div class="space-y-6" dir="rtl">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">داشبورد امتیازدهی</h1>
      <p class="text-sm text-gray-500 mt-1">کیفیت قالب‌های مارکت‌پلیس — ۵ بُعد × ۱۰۰ امتیاز</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ route('admin.theme-marketplace.index') }}"
         class="inline-flex items-center gap-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg hover:bg-gray-50 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        بازگشت
      </a>
    </div>
  </div>

  {{-- Legend --}}
  <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
    <div class="flex flex-wrap items-center gap-4 text-xs">
      <span class="font-semibold text-gray-600">درجه کیفی:</span>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-medium">● عالی (۹۰+)</span>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 font-medium">● خوب (۷۵–۸۹)</span>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-medium">● متوسط (۶۰–۷۴)</span>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-100 text-red-700 font-medium">● نیاز به بهبود (۰–۵۹)</span>
    </div>
  </div>

  {{-- Scoring Table --}}
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="scoringDashboard()">

    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-gray-700">قالب‌های مارکت‌پلیس ({{ $themes->count() }})</h2>
      <button @click="scoreAll()"
              :disabled="scoring"
              class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 disabled:opacity-60 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
        <svg class="w-4 h-4" :class="scoring ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <span x-text="scoring ? 'در حال امتیازدهی...' : 'امتیازدهی همه'"></span>
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-100">
            <th class="text-right text-xs font-semibold text-gray-500 px-4 py-3">قالب</th>
            <th class="text-center text-xs font-semibold text-gray-500 px-3 py-3">طراحی</th>
            <th class="text-center text-xs font-semibold text-gray-500 px-3 py-3">تجاری</th>
            <th class="text-center text-xs font-semibold text-gray-500 px-3 py-3">عملکرد</th>
            <th class="text-center text-xs font-semibold text-gray-500 px-3 py-3">کامل‌بودن</th>
            <th class="text-center text-xs font-semibold text-gray-500 px-3 py-3">بازار</th>
            <th class="text-center text-xs font-semibold text-gray-500 px-3 py-3 w-24">کل</th>
            <th class="text-center text-xs font-semibold text-gray-500 px-3 py-3">درجه</th>
            <th class="text-center text-xs font-semibold text-gray-500 px-3 py-3">عملیات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @forelse($themes as $theme)
            @php
              $meta    = $theme->marketplaceMeta;
              $overall = $meta?->getOverallScore() ?? 0;
              $badge   = $meta?->getScoreBadge() ?? 'needs-work';
              $badgeColors = [
                'excellent'   => 'bg-emerald-100 text-emerald-700',
                'good'        => 'bg-blue-100 text-blue-700',
                'fair'        => 'bg-amber-100 text-amber-700',
                'needs-work'  => 'bg-red-100 text-red-700',
              ];
              $badgeLabels = [
                'excellent'   => 'عالی',
                'good'        => 'خوب',
                'fair'        => 'متوسط',
                'needs-work'  => 'نیاز به بهبود',
              ];
              $scoreColor = fn($s) => $s >= 75 ? 'text-emerald-600 font-semibold' : ($s >= 60 ? 'text-amber-600' : 'text-red-500');
            @endphp
            <tr class="hover:bg-gray-50 transition" x-data="{ open: false, row: @js(['id' => $theme->id, 'name' => $theme->name, 'overall' => $overall, 'badge' => $badge]) }">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ $theme->name }}</div>
                <div class="text-xs text-gray-400">{{ $theme->slug }}</div>
                @if($theme->category)
                  <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-xs bg-indigo-50 text-indigo-600">{{ \App\Models\Theme::categoryLabel($theme->category) }}</span>
                @endif
              </td>
              <td class="px-3 py-3 text-center">
                <span class="{{ $scoreColor($meta?->design_score ?? 0) }}">{{ $meta?->design_score ?? '—' }}</span>
              </td>
              <td class="px-3 py-3 text-center">
                <span class="{{ $scoreColor($meta?->commerce_score ?? 0) }}">{{ $meta?->commerce_score ?? '—' }}</span>
              </td>
              <td class="px-3 py-3 text-center">
                <span class="{{ $scoreColor($meta?->performance_score ?? 0) }}">{{ $meta?->performance_score ?? '—' }}</span>
              </td>
              <td class="px-3 py-3 text-center">
                <span class="{{ $scoreColor($meta?->completeness_score ?? 0) }}">{{ $meta?->completeness_score ?? '—' }}</span>
              </td>
              <td class="px-3 py-3 text-center">
                <span class="{{ $scoreColor($meta?->marketplace_score ?? 0) }}">{{ $meta?->marketplace_score ?? '—' }}</span>
              </td>
              <td class="px-3 py-3 text-center">
                <div class="flex flex-col items-center gap-1">
                  <span class="text-lg font-bold {{ $scoreColor($overall) }}" x-text="row.overall || '{{ $overall }}'">{{ $overall }}</span>
                  <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="h-1.5 rounded-full {{ $overall >= 75 ? 'bg-emerald-500' : ($overall >= 60 ? 'bg-amber-400' : 'bg-red-400') }}"
                         style="width: {{ $overall }}%"></div>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3 text-center">
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeColors[$badge] }}"
                      x-text="row.badge === 'excellent' ? 'عالی' : (row.badge === 'good' ? 'خوب' : (row.badge === 'fair' ? 'متوسط' : 'نیاز به بهبود'))">
                  {{ $badgeLabels[$badge] }}
                </span>
              </td>
              <td class="px-3 py-3 text-center">
                <button @click="scoreOne({{ $theme->id }}, row)"
                        :disabled="scoring"
                        class="text-xs text-violet-600 hover:text-violet-800 font-medium disabled:opacity-40">
                  ارزیابی
                </button>
                @if($meta && count($meta->quality_issues ?? []) > 0)
                  <button @click="open = !open" class="text-xs text-gray-400 hover:text-gray-600 mr-2">
                    ({{ count($meta->quality_issues) }} مشکل)
                  </button>
                @endif
              </td>
            </tr>
            @if($meta && count($meta->quality_issues ?? []) > 0)
            <tr x-show="open" x-collapse>
              <td colspan="9" class="px-6 py-3 bg-red-50">
                <p class="text-xs font-semibold text-red-700 mb-2">مشکلات کیفی:</p>
                <ul class="space-y-1">
                  @foreach($meta->quality_issues as $issue)
                    <li class="text-xs text-red-600 flex items-start gap-2">
                      <svg class="w-3 h-3 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                      {{ is_array($issue) ? ($issue['message'] ?? json_encode($issue)) : $issue }}
                    </li>
                  @endforeach
                </ul>
              </td>
            </tr>
            @endif
          @empty
            <tr>
              <td colspan="9" class="px-6 py-8 text-center text-gray-400 text-sm">
                هیچ قالب مارکت‌پلیسی یافت نشد.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

@push('scripts')
<script>
function scoringDashboard() {
  return {
    scoring: false,

    async scoreOne(themeId, row) {
      this.scoring = true;
      try {
        const res = await fetch(`/admin/theme-marketplace/${themeId}/score`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
        const data = await res.json();
        if (data.success) {
          row.overall = data.overall;
          row.badge   = data.badge;
        }
      } catch(e) { console.error(e); }
      this.scoring = false;
    },

    async scoreAll() {
      const buttons = document.querySelectorAll('[x-data] button[\\@click^="scoreOne"]');
      this.scoring = true;
      const rows = document.querySelectorAll('tbody tr[x-data]');
      for (const row of rows) {
        const btn = row.querySelector('button');
        if (btn) btn.click();
        await new Promise(r => setTimeout(r, 200));
      }
      this.scoring = false;
    }
  };
}
</script>
@endpush
@endsection
