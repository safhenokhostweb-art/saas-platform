@extends(config('seo-advanced.layouts.admin'))

@section('title', 'Google Search Console')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">Google Search Console — سایت #{{ $siteId }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($connection && $connection->property_url)
        <div class="alert alert-success">
            متصل — Property: <strong style="direction: ltr; display: inline-block;">{{ $connection->property_url }}</strong>
        </div>
        <a href="{{ route('seo-advanced.admin.search-console.performance', $siteId) }}" class="btn btn-primary">مشاهده عملکرد</a>
        <form method="POST" action="{{ route('seo-advanced.admin.search-console.disconnect', $siteId) }}" class="d-inline" onsubmit="return confirm('اتصال قطع شود؟');">
            @csrf
            <button class="btn btn-outline-danger">قطع اتصال</button>
        </form>
    @elseif ($connection)
        <div class="alert alert-warning">به گوگل متصل شدید — یک Property انتخاب کنید.</div>
        <a href="{{ route('seo-advanced.admin.search-console.properties', $siteId) }}" class="btn btn-primary">انتخاب Property</a>
    @else
        <p class="text-muted">هنوز حساب گوگل این سایت متصل نشده است.</p>
        <a href="{{ route('seo-advanced.admin.search-console.connect', $siteId) }}" class="btn btn-primary">اتصال حساب گوگل</a>
    @endif
</div>
@endsection
