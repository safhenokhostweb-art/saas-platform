@extends(config('seo-advanced.layouts.admin'))

@section('title', 'داشبورد SEO Advanced')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">SEO Advanced — سایت #{{ $siteId }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">عنوان متا</div>
                    <div class="fw-bold">{{ $settings->meta_title ?? '— تنظیم نشده —' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Robots</div>
                    <div class="fw-bold">{{ $settings->robots_directive ?? 'index,follow' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">تعداد ریدایرکت‌ها</div>
                    <div class="fw-bold">{{ $redirectCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">آخرین امتیاز SEO</div>
                    <div class="fw-bold">{{ $latestAudit->score ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5">آخرین ارزیابی (Last Audit)</h2>
            @if ($latestAudit)
                <p class="text-muted small mb-2">
                    صفحه: {{ $latestAudit->page_url }} —
                    {{ optional($latestAudit->audited_at)->format('Y-m-d H:i') }}
                </p>
                @if (!empty($latestAudit->missing_meta))
                    <strong>موارد یافت‌شده (Issues):</strong>
                    <ul class="mb-0">
                        @foreach ($latestAudit->missing_meta as $issue)
                            <li>{{ $issue }}</li>
                        @endforeach
                    </ul>
                @else
                    <span class="text-success">هیچ مشکلی یافت نشد.</span>
                @endif
            @else
                <span class="text-muted">هنوز هیچ ارزیابی‌ای ثبت نشده است.</span>
            @endif

            <hr>
            <strong>وضعیت فنی (Technical):</strong>
            <ul class="mb-0">
                <li>Robots تنظیم شده: {{ $technical['robots_configured'] ? 'بله' : 'خیر' }}</li>
                <li>Schema.org تنظیم شده: {{ $technical['schema_configured'] ? 'بله' : 'خیر' }}</li>
                <li>Sitemap در دسترس: {{ $technical['sitemap_available'] ? 'بله' : 'خیر' }}</li>
            </ul>
        </div>
    </div>

    <div class="list-group">
        <a href="{{ route('seo-advanced.admin.meta', $siteId) }}" class="list-group-item list-group-item-action">تنظیمات متا (Meta / OpenGraph / Schema)</a>
        <a href="{{ route('seo-advanced.admin.sitemap', $siteId) }}" class="list-group-item list-group-item-action">Sitemap</a>
        <a href="{{ route('seo-advanced.admin.robots', $siteId) }}" class="list-group-item list-group-item-action">Robots.txt</a>
        <a href="{{ route('seo-advanced.admin.redirects', $siteId) }}" class="list-group-item list-group-item-action">ریدایرکت‌ها</a>
        <a href="{{ route('seo-advanced.admin.audit', $siteId) }}" class="list-group-item list-group-item-action">ارزیابی SEO</a>
        <a href="{{ route('seo-advanced.admin.keywords', $siteId) }}" class="list-group-item list-group-item-action">پیگیری کلمات کلیدی (Keyword Tracking)</a>
        <a href="{{ route('seo-advanced.admin.report', $siteId) }}" class="list-group-item list-group-item-action">گزارش SEO</a>
        <a href="{{ route('seo-advanced.admin.search-console', $siteId) }}" class="list-group-item list-group-item-action">Google Search Console</a>
        <a href="{{ route('seo-advanced.admin.ai-assistant', $siteId) }}" class="list-group-item list-group-item-action">دستیار هوشمند SEO (AI Assistant)</a>
    </div>
</div>
@endsection
