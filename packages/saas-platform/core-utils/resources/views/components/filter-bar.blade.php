@props([
    'action'      => '',
    'method'      => 'GET',
    'placeholder' => 'جستجو...',
    'searchName'  => 'search',
    'searchValue' => '',
    'compact'     => false,
])
<form method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}"
      action="{{ $action }}"
      class="{{ $compact ? 'flex flex-wrap gap-2 items-center' : 'bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3 items-center' }}">
    @if(strtoupper($method) !== 'GET')
        @csrf
    @endif

    {{-- Search input --}}
    <div class="relative flex-1 min-w-48">
        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text"
               name="{{ $searchName }}"
               value="{{ $searchValue }}"
               placeholder="{{ $placeholder }}"
               class="w-full border border-gray-200 rounded-lg pr-9 pl-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
    </div>

    {{-- Extra filter slots --}}
    {{ $slot }}

    {{-- Submit --}}
    <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition shrink-0">
        جستجو
    </button>

    {{-- Reset --}}
    @if(request()->hasAny([$searchName]))
        <a href="{{ $action }}"
           class="bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg transition shrink-0">
            پاک کردن
        </a>
    @endif
</form>
