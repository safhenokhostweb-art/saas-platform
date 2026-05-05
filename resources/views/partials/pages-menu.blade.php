@php
    $slot = $position ?? 'top';
    try {
        $globalPosition = \App\Models\Setting::get('page_menu_position', 'top');
        if ($globalPosition !== $slot) {
            $pages = collect();
        } else {
            $pages = \App\Models\Page::where('is_active', true)->orderBy('menu_order')->orderBy('title')->get();
        }
    } catch (\Throwable $e) {
        $pages = collect();
    }
@endphp
@if($pages->isNotEmpty())
    <nav class="pages-menu pages-menu-{{ $slot }} flex {{ in_array($slot, ['left','right'], true) ? 'flex-col gap-2' : 'flex-wrap items-center gap-2 md:gap-4' }}" aria-label="صفحات ثابت">
        @foreach($pages as $p)
            @php $isActive = request()->routeIs('page.show') && request()->route('slug') == $p->slug; @endphp
            <a href="{{ route('page.show', $p->slug) }}" class="pages-menu-link inline-flex items-center gap-1.5 text-sm font-medium {{ $isActive ? 'text-indigo-600' : 'text-gray-600 hover:text-indigo-600' }}">
                @if($p->icon_url)
                    <img src="{{ $p->icon_url }}" alt="" class="w-5 h-5 object-contain shrink-0" width="20" height="20">
                @else
                    @include('admin.pages.partials.icon-svg', ['name' => $p->icon_default ?? 'document', 'class' => 'w-5 h-5 shrink-0 text-current'])
                @endif
                <span>{{ $p->title }}</span>
            </a>
        @endforeach
    </nav>
@endif
