@extends(config('crm.layouts.admin'))

@section('title', 'Import CSV')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">Import CSV — {{ $entity }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <p class="text-muted">ستون‌های مورد انتظار (ردیف اول فایل CSV): <code>{{ implode(', ', $columns) }}</code></p>

    <form method="POST" action="{{ route('crm.admin.import.preview', [$siteId, $entity]) }}" enctype="multipart/form-data" class="row g-2">
        @csrf
        <div class="col-md-6"><input type="file" name="file" class="form-control" accept=".csv,.txt" required></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">پیش‌نمایش</button></div>
    </form>
</div>
@endsection
