@extends('layouts.app')

@php
    /*
     * `page/{slug}` (routes/web.php) is a genuinely public, indexable tenant
     * surface, so it resolves its metadata as TENANT_PUBLIC — not with the
     * layout's PLATFORM_ADMIN default. Everything this page owns (title,
     * description, canonical, og:type) is resolved here, once, through the
     * same MetaEngine the layout uses, and handed to the layout as $metaData;
     * the layout is the only place these tags are written out.
     *
     * It used to publish its own <meta name="description"> and
     * <link rel="canonical"> into a `@section('head')` that the layout never
     * yielded, so the page's own values were silently discarded while the
     * layout rendered its platform-admin fallback for the same tags — and a
     * public route shipped `noindex, nofollow`.
     *
     * Only `og_type` is passed as an override: title, description, canonical
     * and robots are all read off $page by MetaEngine itself, and the
     * robots directive comes from the context (indexable) unless the page
     * carries an explicit one.
     */
    $metaData = $metaData ?? app(\App\Services\Seo\SeoService::class)->resolveForTenantPublic($page, [
        'og_type' => 'article',
    ]);
@endphp

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
