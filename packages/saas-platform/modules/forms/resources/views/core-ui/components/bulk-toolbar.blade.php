@props([
    'count'      => 0,
    'actions'    => [],  // [['label' => '...', 'value' => '...', 'color' => 'red|gray']]
    'formAction' => '',
    'formMethod' => 'POST',
    'idsName'    => 'ids',
    'actionName' => 'action',
    'countVar'   => 'bulkCount',  // Alpine variable name
])

<div x-show="{{ $countVar }} > 0"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-2"
     class="sticky top-0 z-30 flex items-center gap-3 px-4 py-2.5 bg-indigo-600 text-white rounded-xl shadow-lg mb-4"
     style="display:none"
     role="toolbar"
     aria-label="عملیات گروهی">

    <div class="flex items-center gap-2 flex-1">
        <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-white/20">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <span class="text-sm font-bold" x-text="`${{{ $countVar }}} مورد انتخاب شده`"></span>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
        @foreach($actions as $action)
        @php $color = $action['color'] ?? 'white'; @endphp
        <form method="POST" action="{{ $formAction }}" class="inline"
              x-on:submit.prevent="
                  const form = $el;
                  const selected = typeof bulkSelected !== 'undefined' ? bulkSelected : [];
                  selected.forEach(id => {
                      const input = document.createElement('input');
                      input.type = 'hidden';
                      input.name = '{{ $idsName }}[]';
                      input.value = id;
                      form.appendChild(input);
                  });
                  form.submit();
              ">
            @csrf
            @method($formMethod)
            <input type="hidden" name="{{ $actionName }}" value="{{ $action['value'] }}">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all
                           {{ $color === 'red' ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-white/20 hover:bg-white/30 text-white' }}">
                @if(isset($action['icon']))
                {!! $action['icon'] !!}
                @endif
                {{ $action['label'] }}
            </button>
        </form>
        @endforeach

        <button @click="typeof bulkSelected !== 'undefined' && (bulkSelected = [])"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-white/70 hover:text-white hover:bg-white/10 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            لغو
        </button>
    </div>

</div>
