@extends(config('crm.layouts.admin'))

@section('title', 'وظایف')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">وظایف — سایت #{{ $siteId }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3"><label><input type="checkbox" name="due_soon" value="1" @checked(request('due_soon')) class="form-check-input"> سررسید نزدیک (۷ روز)</label></div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100">فیلتر</button></div>
    </form>

    <table class="table table-bordered">
        <thead><tr><th>عنوان</th><th>سررسید</th><th>وضعیت</th><th></th></tr></thead>
        <tbody>
            @forelse ($tasks as $task)
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>{{ optional($task->due_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $task->status }}</td>
                    <td>
                        @if ($task->status === 'pending')
                            <form method="POST" action="{{ route('crm.admin.tasks.complete', [$siteId, $task]) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success">تکمیل</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('crm.admin.tasks.destroy', [$siteId, $task]) }}" class="d-inline" onsubmit="return confirm('حذف شود؟');">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted">وظیفه‌ای ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $tasks->links() }}
</div>
@endsection
