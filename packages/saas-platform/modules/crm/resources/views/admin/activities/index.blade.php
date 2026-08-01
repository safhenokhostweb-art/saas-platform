@extends(config('crm.layouts.admin'))

@section('title', 'رویدادها')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">تایم‌لاین — {{ class_basename($subject) }} #{{ $subjectId }}</h1>

    <form method="POST" action="{{ route('crm.admin.activities.store', [$siteId, $subjectType, $subjectId]) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-2">
            <select name="type" class="form-select">
                <option value="note">یادداشت</option>
                <option value="call">تماس</option>
                <option value="meeting">جلسه</option>
                <option value="email">ایمیل</option>
                <option value="sms">پیامک</option>
            </select>
        </div>
        <div class="col-md-3"><input type="text" name="subject" class="form-control" placeholder="موضوع"></div>
        <div class="col-md-5"><textarea name="body" class="form-control" placeholder="متن"></textarea></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">ثبت</button></div>
    </form>

    <ul class="list-group">
        @forelse ($activities as $activity)
            <li class="list-group-item">
                <span class="badge bg-secondary">{{ $activity->type }}</span>
                <strong>{{ $activity->subject }}</strong>
                <p class="mb-1">{{ $activity->body }}</p>
                <small class="text-muted">{{ optional($activity->occurred_at)->format('Y-m-d H:i') }}</small>
            </li>
        @empty
            <li class="list-group-item text-muted">رویدادی ثبت نشده است.</li>
        @endforelse
    </ul>

    {{ $activities->links() }}
</div>
@endsection
