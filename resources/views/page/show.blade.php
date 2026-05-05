@extends('layouts.app')

@section('title', $page->title . ' | ' . \App\Helpers\PlatformSettings::platformName())
@section('head')
    @php
        $metaDesc = $page->meta_description ? e($page->meta_description) : e(\Illuminate\Support\Str::limit($page->title, 160));
    @endphp
    <meta name="description" content="{{ $metaDesc }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ e($page->title) }}">
    <meta property="og:description" content="{{ $metaDesc }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="fa_IR">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ e($page->title) }}">
    <meta name="twitter:description" content="{{ $metaDesc }}">
@endsection

@section('content')
<article class="card p-6 md:p-8 max-w-4xl mx-auto">
    <header class="border-b border-gray-200 pb-4 mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">{{ $page->title }}</h1>
    </header>

    <div class="page-content prose prose-lg max-w-none">
        @php $content = $page->content; $blocks = isset($content['blocks']) && is_array($content['blocks']) ? $content['blocks'] : []; @endphp
        @foreach($blocks as $block)
            @include('page.partials.block', ['block' => $block])
        @endforeach
        @if(empty($blocks))
            <p class="text-gray-500">محتوایی برای این صفحه تعریف نشده است.</p>
        @endif
    </div>
</article>
@push('scripts')
<script>
document.querySelectorAll('.gallery-carousel-wrap').forEach(function(wrap) {
    var track = wrap.querySelector('.gallery-track');
    var prev = wrap.querySelector('.gallery-prev');
    var next = wrap.querySelector('.gallery-next');
    if (!track || !prev || !next) return;
    prev.addEventListener('click', function() { track.scrollBy({ left: -340, behavior: 'smooth' }); });
    next.addEventListener('click', function() { track.scrollBy({ left: 340, behavior: 'smooth' }); });
});
</script>
@endpush
@endsection
