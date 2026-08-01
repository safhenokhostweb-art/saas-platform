@extends(config('crm.layouts.admin'))

@section('title', 'گزارش فروش')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">گزارش فروش (Sales Report) — سایت #{{ $siteId }}</h1>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="pipeline_id" class="form-select" onchange="this.form.submit()">
                <option value="">همه خطوط لوله</option>
                @foreach ($pipelines as $p)
                    <option value="{{ $p->id }}" @selected($pipelineId == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <a href="{{ route('crm.admin.reports.export', ['siteId' => $siteId, 'pipeline_id' => $pipelineId]) }}" class="btn btn-outline-secondary">خروجی CSV</a>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">نرخ برد (Win Rate)</div>
            <div class="fw-bold fs-4">{{ $win_rate }}%</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">نرخ باخت (Lost Rate)</div>
            <div class="fw-bold fs-4">{{ $lost_rate }}%</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">نرخ تبدیل سرنخ (Conversion Rate)</div>
            <div class="fw-bold fs-4">{{ $conversion_rate }}%</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">ارزش خط لوله (Pipeline Value)</div>
            <div class="fw-bold fs-4">{{ number_format($pipeline_value) }}</div>
        </div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card"><div class="card-body">
            <div class="text-muted small">پیش‌بینی درآمد (Revenue Forecast)</div>
            <div class="fw-bold fs-4">{{ number_format($revenue_forecast) }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body">
            <div class="text-muted small">مبلغ برد (Won Amount)</div>
            <div class="fw-bold fs-4">{{ number_format($won_amount) }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body">
            <div class="text-muted small">برد / باخت</div>
            <div class="fw-bold fs-4">{{ $won_count }} / {{ $lost_count }}</div>
        </div></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header">فروش به تفکیک مرحله (Sales by Stage)</div>
        <table class="table table-sm mb-0">
            <thead><tr><th>مرحله</th><th>تعداد</th><th>مبلغ</th></tr></thead>
            <tbody>
                @forelse ($sales_by_stage as $row)
                    <tr><td>{{ $row['stage']->name }}</td><td>{{ $row['count'] }}</td><td>{{ number_format($row['amount']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">داده‌ای موجود نیست.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header">فروش به تفکیک دوره (Sales by Period — ۶ ماه اخیر)</div>
        <table class="table table-sm mb-0">
            <thead><tr><th>دوره</th><th>تعداد برد</th><th>مبلغ برد</th></tr></thead>
            <tbody>
                @foreach ($sales_by_period as $row)
                    <tr><td>{{ $row['period'] }}</td><td>{{ $row['won_count'] }}</td><td>{{ number_format($row['won_amount']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
