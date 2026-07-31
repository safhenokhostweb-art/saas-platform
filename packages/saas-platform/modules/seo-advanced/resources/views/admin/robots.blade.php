@extends(config('seo-advanced.layouts.admin'))

@section('title', 'Robots.txt')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">Robots.txt — سایت #{{ $siteId }}</h1>

    <p>
        <a href="{{ route('seo-advanced.runtime.robots', $siteId) }}" target="_blank" class="btn btn-outline-primary btn-sm">مشاهده robots.txt زنده</a>
    </p>

    <pre class="bg-light p-3 border rounded" style="direction: ltr; text-align: left;">{{ $content }}</pre>

    <p class="text-muted small">این محتوا بر اساس فیلد Robots در تنظیمات متا ساخته می‌شود.</p>
</div>
@endsection
