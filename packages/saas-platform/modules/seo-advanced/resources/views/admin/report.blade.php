@extends(config('seo-advanced.layouts.admin'))

@section('title', 'گزارش SEO')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">گزارش SEO — سایت #{{ $siteId }}</h1>

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

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5">روند امتیاز (Score Over Time)</h2>
            @if (!empty($score_history))
                <table class="table table-sm">
                    <thead><tr><th>تاریخ</th><th>امتیاز</th></tr></thead>
                    <tbody>
                        @foreach ($score_history as $point)
                            <tr><td>{{ $point['date'] }}</td><td>{{ $point['score'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <span class="text-muted">هنوز داده‌ای برای نمایش روند وجود ندارد.</span>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h2 class="h5">کلمات کلیدی</h2>
            @if ($keywords->isNotEmpty())
                <table class="table table-sm">
                    <thead><tr><th>کلمه کلیدی</th><th>رتبه فعلی</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        @foreach ($keywords as $keyword)
                            <tr><td>{{ $keyword->keyword }}</td><td>{{ $keyword->position ?? '—' }}</td><td>{{ $keyword->status }}</td></tr>
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
