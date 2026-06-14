@props([
    'paginator' => null,   // LengthAwarePaginator instance
    'summary'   => true,   // show "X - Y of Z records"
])
@if($paginator && $paginator->hasPages())
<div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 bg-white border-t border-gray-100">
    @if($summary)
        <p class="text-xs text-gray-500">
            نمایش
            <span class="font-medium text-gray-700">{{ $paginator->firstItem() }}</span>
            تا
            <span class="font-medium text-gray-700">{{ $paginator->lastItem() }}</span>
            از
            <span class="font-medium text-gray-700">{{ $paginator->total() }}</span>
            مورد
        </p>
    @endif
    <div class="flex items-center gap-1">
        {{-- Previous --}}
        @if($paginator->onFirstPage())
            <span class="px-3 py-1.5 text-xs text-gray-500 border border-gray-200 rounded-lg cursor-not-allowed opacity-60">قبلی</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                قبلی
            </a>
        @endif

        {{-- Page numbers (up to 5) --}}
        @foreach($paginator->getUrlRange(max(1, $paginator->currentPage()-2), min($paginator->lastPage(), $paginator->currentPage()+2)) as $page => $url)
            @if($page == $paginator->currentPage())
                <span class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg">{{ $page }}</span>
            @else
                <a href="{{ $url }}"
                   class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    {{ $page }}
                </a>
            @endif
        @endforeach

        {{-- Next --}}
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                بعدی
            </a>
        @else
            <span class="px-3 py-1.5 text-xs text-gray-500 border border-gray-200 rounded-lg cursor-not-allowed opacity-60">بعدی</span>
        @endif
    </div>
</div>
@endif
