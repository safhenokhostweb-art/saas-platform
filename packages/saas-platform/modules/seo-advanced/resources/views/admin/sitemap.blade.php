@extends(config('seo-advanced.layouts.admin'))

@section('title', 'Sitemap')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">Sitemap — سایت #{{ $siteId }}</h1>

    <p>
        <a href="{{ route('seo-advanced.runtime.sitemap', $siteId) }}" target="_blank" class="btn btn-outline-primary btn-sm">مشاهده sitemap.xml زنده</a>
    </p>

    <p class="text-muted">تعداد URL: {{ count($urls) }}</p>

    <pre class="bg-light p-3 border rounded" style="direction: ltr; text-align: left; white-space: pre-wrap;">{{ $xml }}</pre>
</div>
@endsection
