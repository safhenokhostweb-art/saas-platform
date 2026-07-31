@extends(config('seo-advanced.layouts.admin'))

@section('title', 'ارزیابی SEO')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">ارزیابی SEO — سایت #{{ $siteId }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('seo-advanced.admin.audit.run', $siteId) }}" class="mb-4">
        @csrf
        <div class="mb-2">
            <label class="form-label">آدرس صفحه</label>
            <input type="text" name="page_url" class="form-control" required>
        </div>
        <div class="mb-2">
            <label class="form-label">عنوان صفحه (title)</label>
            <input type="text" name="title" class="form-control">
        </div>
        <div class="mb-2">
            <label class="form-label">توضیحات صفحه (description)</label>
            <textarea name="description" class="form-control"></textarea>
        </div>
        <div class="mb-2">
            <label class="form-label">تیتر اصلی صفحه (H1)</label>
            <input type="text" name="heading" class="form-control">
        </div>
        <div class="mb-2">
            <label class="form-label">Canonical URL</label>
            <input type="text" name="canonical" class="form-control">
        </div>
        <div class="mb-2">
            <label class="form-label">ساختار تیترهای صفحه (اختیاری — هر خط: سطح|متن، مثال: 1|عنوان اصلی)</label>
            <textarea name="headings_raw" class="form-control" rows="3" placeholder="1|عنوان اصلی&#10;2|زیرعنوان"></textarea>
        </div>
        <div class="row">
            <div class="col-md-4 mb-2">
                <label class="form-label">OG Title</label>
                <input type="text" name="og_title" class="form-control">
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label">OG Description</label>
                <input type="text" name="og_description" class="form-control">
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label">OG Image</label>
                <input type="text" name="og_image" class="form-control">
            </div>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="check_url_structure" value="1" class="form-check-input" id="check_url_structure">
            <label class="form-check-label" for="check_url_structure">بررسی ساختار آدرس (URL Structure)</label>
        </div>
        <button type="submit" class="btn btn-primary">ارزیابی کن</button>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>صفحه</th>
                <th>طول عنوان</th>
                <th>طول توضیحات</th>
                <th>امتیاز</th>
                <th>بحرانی</th>
                <th>هشدار</th>
                <th>پیشنهاد</th>
                <th>تاریخ</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($audits as $audit)
                @php
                    $issues = $audit->issues ?? [];
                    $bySeverity = ['critical' => 0, 'warning' => 0, 'recommendation' => 0];
                    foreach ($issues as $issue) {
                        $bySeverity[$issue['severity'] ?? 'recommendation'] = ($bySeverity[$issue['severity'] ?? 'recommendation'] ?? 0) + 1;
                    }
                @endphp
                <tr>
                    <td>{{ $audit->page_url }}</td>
                    <td>{{ $audit->title_length }}</td>
                    <td>{{ $audit->description_length }}</td>
                    <td>{{ $audit->score }}</td>
                    <td>{{ $bySeverity['critical'] }}</td>
                    <td>{{ $bySeverity['warning'] }}</td>
                    <td>{{ $bySeverity['recommendation'] }}</td>
                    <td>{{ optional($audit->audited_at)->format('Y-m-d H:i') }}</td>
                </tr>
                @if (!empty($issues))
                    <tr>
                        <td colspan="8">
                            <ul class="mb-0 small">
                                @foreach ($issues as $issue)
                                    <li>
                                        <span class="badge bg-{{ ($issue['severity'] ?? '') === 'critical' ? 'danger' : (($issue['severity'] ?? '') === 'warning' ? 'warning' : 'secondary') }}">{{ $issue['severity'] ?? '' }}</span>
                                        {{ $issue['message'] ?? '' }}
                                        <em class="text-muted">— {{ $issue['suggested_fix'] ?? '' }}</em>
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="8" class="text-muted">هنوز هیچ ارزیابی‌ای ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $audits->links() }}
</div>
@endsection
