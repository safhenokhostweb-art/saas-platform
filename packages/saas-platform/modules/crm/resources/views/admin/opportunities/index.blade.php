@extends(config('crm.layouts.admin'))

@section('title', 'معاملات')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">معاملات (Sales Pipeline) — سایت #{{ $siteId }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="mb-3">
        <a href="{{ route('crm.admin.opportunities.kanban', ['siteId' => $siteId, 'pipeline_id' => $pipelineId]) }}" class="btn btn-outline-primary btn-sm">Kanban</a>
        <a href="{{ route('crm.admin.opportunities.forecast', ['siteId' => $siteId, 'pipeline_id' => $pipelineId]) }}" class="btn btn-outline-primary btn-sm">پیش‌بینی فروش (Forecast)</a>
        <a href="{{ route('crm.admin.pipelines.index', $siteId) }}" class="btn btn-outline-secondary btn-sm">مدیریت خطوط لوله</a>
        <a href="{{ route('crm.admin.reports.show', ['siteId' => $siteId, 'pipeline_id' => $pipelineId]) }}" class="btn btn-outline-primary btn-sm">گزارش فروش</a>
        <a href="{{ route('crm.admin.export.opportunities', $siteId) }}" class="btn btn-outline-secondary btn-sm">Export CSV</a>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="pipeline_id" class="form-select" onchange="this.form.submit()">
                @foreach ($pipelines as $pipeline)
                    <option value="{{ $pipeline->id }}" @selected($pipelineId == $pipeline->id)>{{ $pipeline->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="جستجو نام معامله"></div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">همه</option>
                <option value="open" @selected(request('status') === 'open')>باز</option>
                <option value="won" @selected(request('status') === 'won')>برد</option>
                <option value="lost" @selected(request('status') === 'lost')>باخت</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100">فیلتر</button></div>
    </form>

    <form method="POST" action="{{ route('crm.admin.opportunities.store', $siteId) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="نام معامله" required></div>
        <div class="col-md-2"><input type="number" name="amount" class="form-control" placeholder="مبلغ"></div>
        <input type="hidden" name="pipeline_id" value="{{ $pipelineId }}">
        <div class="col-md-3">
            <select name="stage_id" class="form-select" required>
                @foreach ($pipelines->firstWhere('id', $pipelineId)?->stages ?? [] as $stage)
                    <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><input type="date" name="expected_close_date" class="form-control"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">افزودن</button></div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr><th>نام</th><th>مرحله</th><th>مبلغ</th><th>احتمال</th><th>وضعیت</th><th>عملیات</th></tr>
        </thead>
        <tbody>
            @forelse ($opportunities as $opportunity)
                <tr>
                    <td>{{ $opportunity->name }}</td>
                    <td>{{ $opportunity->stage?->name }}</td>
                    <td>{{ number_format($opportunity->amount) }}</td>
                    <td>{{ $opportunity->effectiveProbability() }}%</td>
                    <td>{{ $opportunity->status }}</td>
                    <td>
                        @if ($opportunity->status === 'open')
                            <form method="POST" action="{{ route('crm.admin.opportunities.won', [$siteId, $opportunity]) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success">برد</button>
                            </form>
                            <form method="POST" action="{{ route('crm.admin.opportunities.lost', [$siteId, $opportunity]) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">باخت</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">معامله‌ای ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $opportunities->links() }}
</div>
@endsection
