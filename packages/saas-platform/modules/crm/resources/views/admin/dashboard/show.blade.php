@extends(config('crm.layouts.admin'))

@section('title', 'داشبورد CRM')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">داشبورد CRM — سایت #{{ $siteId }}</h1>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">سرنخ‌های باز (Leads)</div>
            <div class="fw-bold fs-4">{{ $lead_count }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">مشتریان (Customers)</div>
            <div class="fw-bold fs-4">{{ $customer_count }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">معاملات باز (Opportunities)</div>
            <div class="fw-bold fs-4">{{ $opportunity_count }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">ارزش خط لوله (Pipeline Value)</div>
            <div class="fw-bold fs-4">{{ number_format($pipeline_value) }}</div>
        </div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">معاملات موفق (Won)</div>
            <div class="fw-bold fs-4 text-success">{{ $won_count }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">معاملات از دست‌رفته (Lost)</div>
            <div class="fw-bold fs-4 text-danger">{{ $lost_count }}</div>
        </div></div></div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">وظایف امروز (Today's Tasks)</div>
                <ul class="list-group list-group-flush">
                    @forelse ($today_tasks as $task)
                        <li class="list-group-item">{{ $task->title }} — {{ optional($task->due_at)->format('H:i') }}</li>
                    @empty
                        <li class="list-group-item text-muted">وظیفه‌ای برای امروز ثبت نشده است.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">آخرین فعالیت‌ها (Recent Activities)</div>
                <ul class="list-group list-group-flush">
                    @forelse ($recent_activities as $activity)
                        <li class="list-group-item">
                            <span class="badge bg-secondary">{{ $activity->type }}</span> {{ $activity->subject }}
                            <small class="text-muted d-block">{{ optional($activity->occurred_at)->format('Y-m-d H:i') }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">فعالیتی ثبت نشده است.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
