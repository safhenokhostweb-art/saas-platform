@extends(config('crm.layouts.admin'))

@section('title', 'پیش‌بینی فروش')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">پیش‌بینی فروش (Forecast) — {{ $pipeline->name }}</h1>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="pipeline_id" class="form-select" onchange="this.form.submit()">
                @foreach ($pipelines as $p)
                    <option value="{{ $p->id }}" @selected($p->id === $pipeline->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-muted small">تعداد معاملات باز</div>
                <div class="fw-bold fs-4">{{ $totals['count'] }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-muted small">مجموع مبلغ (Expected Revenue)</div>
                <div class="fw-bold fs-4">{{ number_format($totals['total_amount']) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-muted small">مبلغ وزن‌دار (Weighted / Forecast)</div>
                <div class="fw-bold fs-4">{{ number_format($totals['weighted_amount']) }}</div>
            </div></div>
        </div>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr><th>مرحله</th><th>احتمال پیش‌فرض</th><th>تعداد</th><th>مجموع مبلغ</th><th>مبلغ وزن‌دار</th></tr>
        </thead>
        <tbody>
            @foreach ($byStage as $row)
                <tr>
                    <td>{{ $row['stage']->name }}</td>
                    <td>{{ $row['stage']->probability }}%</td>
                    <td>{{ $row['count'] }}</td>
                    <td>{{ number_format($row['total_amount']) }}</td>
                    <td>{{ number_format($row['weighted_amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
