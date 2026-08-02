@extends(config('seo-advanced.layouts.site', 'layouts.app'))

@section('title', 'گزارش SEO سایت شما')

@section('content')
<div class="container">
    <h1 class="h3 mb-4">گزارش SEO سایت شما</h1>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">آخرین امتیاز</div>
                    <div class="fw-bold fs-4">{{ $latest_audit->score ?? '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">بحرانی / هشدار / پیشنهاد</div>
                    <div class="fw-bold">{{ $issue_counts['critical'] }} / {{ $issue_counts['warning'] }} / {{ $issue_counts['recommendation'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">صفحات ارزیابی‌شده</div>
                    <div class="fw-bold">{{ $audited_page_count }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">کلمات کلیدی تحت پیگیری</div>
                    <div class="fw-bold">{{ $keyword_count }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($google_performance)
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">عملکرد در گوگل (۳۰ روز اخیر)</h2>
                <div class="row g-3">
                    <div class="col-md-3"><div class="text-muted small">Clicks</div><div class="fw-bold">{{ number_format($google_performance['clicks']) }}</div></div>
                    <div class="col-md-3"><div class="text-muted small">Impressions</div><div class="fw-bold">{{ number_format($google_performance['impressions']) }}</div></div>
                    <div class="col-md-3"><div class="text-muted small">CTR</div><div class="fw-bold">{{ $google_performance['ctr'] }}%</div></div>
                    <div class="col-md-3"><div class="text-muted small">Average Position</div><div class="fw-bold">{{ $google_performance['average_position'] }}</div></div>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($recommendations))
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">پیشنهادات بهبود</h2>
                <ul class="mb-0">
                    @foreach ($recommendations as $recommendation)
                        <li>{{ $recommendation }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($latest_audit && !empty($latest_audit->issues))
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">مهم‌ترین موارد قابل بهبود</h2>
                <ul class="mb-0">
                    @foreach ($latest_audit->issues as $issue)
                        <li>{{ $issue['message'] ?? '' }} — <em class="text-muted">{{ $issue['suggested_fix'] ?? '' }}</em></li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (!empty($score_history))
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">روند امتیاز (History)</h2>
                <table class="table table-sm mb-0">
                    <thead><tr><th>تاریخ</th><th>امتیاز</th></tr></thead>
                    <tbody>
                        @foreach ($score_history as $point)
                            <tr><td>{{ $point['date'] }}</td><td>{{ $point['score'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <h2 class="h5">کلمات کلیدی شما</h2>
            @if ($keywords->isNotEmpty())
                <table class="table table-sm">
                    <thead><tr><th>کلمه کلیدی</th><th>رتبه فعلی</th></tr></thead>
                    <tbody>
                        @foreach ($keywords as $keyword)
                            <tr><td>{{ $keyword->keyword }}</td><td>{{ $keyword->position ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <span class="text-muted">هنوز کلمه کلیدی‌ای ثبت نشده است.</span>
            @endif
        </div>
    </div>
</div>
@endsection
