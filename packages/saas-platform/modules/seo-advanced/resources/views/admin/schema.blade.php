@extends(config('seo-advanced.layouts.admin'))

@section('title', 'Schema.org')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">Schema.org — سایت #{{ $siteId }}</h1>

    <pre class="bg-light p-3 border rounded" style="direction: ltr; text-align: left; white-space: pre-wrap;">{{ json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
</div>
@endsection
