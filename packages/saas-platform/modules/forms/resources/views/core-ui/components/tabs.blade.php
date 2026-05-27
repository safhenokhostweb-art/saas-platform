{{--
  Core-UI Tabs — Phase 9.2
  Usage:
    <x-core-ui::tabs :tabs="[['id' => 'general', 'label' => 'عمومی'], ['id' => 'advanced', 'label' => 'پیشرفته']]" active-tab="activeTab" />
  Emits: tab-changed event via $dispatch('tab-changed', tabId)
  Props: tabs (array of {id, label, :badge?}), activeTab (Alpine model name)
--}}
@props([
    'tabs'      => [],
    'activeTab' => 'activeTab',
])

<div class="flex items-center gap-1 border-b border-slate-200 mb-5" role="tablist">
  @foreach($tabs as $tab)
    <button
      type="button"
      role="tab"
      :aria-selected="{{ $activeTab }} === '{{ $tab['id'] }}'"
      @click="{{ $activeTab }} = '{{ $tab['id'] }}'; $dispatch('tab-changed', '{{ $tab['id'] }}')"
      :class="{{ $activeTab }} === '{{ $tab['id'] }}'
        ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold'
        : 'text-slate-500 hover:text-slate-700'"
      class="flex items-center gap-1.5 px-4 py-2.5 text-sm transition focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-400 focus-visible:outline-none"
    >
      {{ $tab['label'] }}
      @if(isset($tab['badge']))
        <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-slate-100 text-slate-600"
              :class="{{ $activeTab }} === '{{ $tab['id'] }}' ? 'bg-indigo-100 text-indigo-700' : ''">
          {{ $tab['badge'] }}
        </span>
      @endif
    </button>
  @endforeach
</div>
