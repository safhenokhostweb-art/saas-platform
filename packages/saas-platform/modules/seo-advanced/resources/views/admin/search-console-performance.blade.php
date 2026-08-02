@extends(config('seo-advanced.layouts.admin'))

@section('title', 'عملکرد در گوگل')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">عملکرد در گوگل (۳۰ روز اخیر) — سایت #{{ $siteId }}</h1>

    @if (!$performance)
        <div class="alert alert-warning">این سایت به Google Search Console متصل نیست.</div>
        <a href="{{ route('seo-advanced.admin.search-console', $siteId) }}" class="btn btn-primary">اتصال</a>
    @else
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Clicks</div><div class="fw-bold fs-4">{{ number_format($performance['clicks']) }}</div></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Impressions</div><div class="fw-bold fs-4">{{ number_format($performance['impressions']) }}</div></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">CTR</div><div class="fw-bold fs-4">{{ $performance['ctr'] }}%</div></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Average Position</div><div class="fw-bold fs-4">{{ $performance['average_position'] }}</div></div></div></div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Top Queries</div>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Query</th><th>Clicks</th><th>Position</th></tr></thead>
                        <tbody>
                            @forelse ($performance['top_queries'] as $row)
                                <tr><td style="direction: ltr;">{{ $row['query'] }}</td><td>{{ $row['clicks'] }}</td><td>{{ $row['position'] }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">داده‌ای موجود نیست.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Top Pages</div>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Page</th><th>Clicks</th><th>Position</th></tr></thead>
                        <tbody>
                            @forelse ($performance['top_pages'] as $row)
                                <tr><td style="direction: ltr;">{{ $row['page'] }}</td><td>{{ $row['clicks'] }}</td><td>{{ $row['position'] }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">داده‌ای موجود نیست.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
