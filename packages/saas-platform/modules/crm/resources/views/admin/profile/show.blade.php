@extends(config('crm.layouts.admin'))

@section('title', 'پروفایل مشتری')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-1">{{ $contact->full_name }}</h1>
    <p class="text-muted">
        {{ $contact->email }} · {{ $contact->phone }}
        @if ($contact->company) · {{ $contact->company->name }} @endif
        · <span class="badge bg-info">{{ $contact->lifecycle_stage }}</span>
        @foreach ($contact->tags as $tag)
            <span class="badge" style="background-color: {{ $tag->color }}">{{ $tag->name }}</span>
        @endforeach
    </p>

    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-muted small">کل معاملات</div><div class="fw-bold fs-5">{{ $stats['total_deals'] }}</div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-muted small">معاملات باز</div><div class="fw-bold fs-5">{{ $stats['open_deals'] }}</div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-muted small">معاملات برد</div><div class="fw-bold fs-5">{{ $stats['won_deals'] }}</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">مبلغ برد</div><div class="fw-bold fs-5">{{ number_format($stats['won_amount']) }}</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">خط لوله باز</div><div class="fw-bold fs-5">{{ number_format($stats['open_pipeline_amount']) }}</div></div></div></div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">معاملات (Deals)</div>
                <ul class="list-group list-group-flush">
                    @forelse ($deals as $deal)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $deal->name }} — {{ $deal->stage?->name }}</span>
                            <strong>{{ number_format($deal->amount) }}</strong>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">معامله‌ای ثبت نشده است.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card mb-4">
                <div class="card-header">وظایف در انتظار (Pending Tasks)</div>
                <ul class="list-group list-group-flush">
                    @forelse ($tasks as $task)
                        <li class="list-group-item">{{ $task->title }} — {{ optional($task->due_at)->format('Y-m-d') }}</li>
                    @empty
                        <li class="list-group-item text-muted">وظیفه‌ای ثبت نشده است.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card mb-4">
                <div class="card-header">فایل‌ها (Files)</div>
                <ul class="list-group list-group-flush">
                    @forelse ($files as $file)
                        <li class="list-group-item"><a href="{{ $file->url() }}">{{ $file->original_name }}</a></li>
                    @empty
                        <li class="list-group-item text-muted">فایلی بارگذاری نشده است.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">یادداشت‌ها (Notes)</div>
                <ul class="list-group list-group-flush">
                    @forelse ($notes as $note)
                        <li class="list-group-item">{{ $note->body }} <small class="text-muted d-block">{{ optional($note->occurred_at)->format('Y-m-d H:i') }}</small></li>
                    @empty
                        <li class="list-group-item text-muted">یادداشتی ثبت نشده است.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card">
                <div class="card-header">آخرین رویدادها (Latest Activities)</div>
                <ul class="list-group list-group-flush">
                    @forelse ($activities as $activity)
                        <li class="list-group-item">
                            <span class="badge bg-secondary">{{ $activity->type }}</span> {{ $activity->subject }}
                            <small class="text-muted d-block">{{ optional($activity->occurred_at)->format('Y-m-d H:i') }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">رویدادی ثبت نشده است.</li>
                    @endforelse
                </ul>
                <div class="card-footer">
                    <a href="{{ route('crm.admin.activities.index', [$siteId, 'contact', $contact->id]) }}" class="btn btn-sm btn-outline-primary">مشاهده تایم‌لاین کامل</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
