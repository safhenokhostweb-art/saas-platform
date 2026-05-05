@if($seo)
    <title>{{ $seo->title }}</title>
    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical }}">

    @if($seo->noindex)
        <meta name="robots" content="noindex,nofollow">
    @endif

    @foreach(($seo->og ?? []) as $key => $value)
        <meta property="og:{{ $key }}" content="{{ $value }}">
    @endforeach

    @foreach(($seo->twitter ?? []) as $key => $value)
        <meta name="twitter:{{ $key }}" content="{{ $value }}">
    @endforeach

    @if(!empty($seo->schema))
        <script type="application/ld+json">
{!! json_encode($seo->schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>
    @endif
@endif
