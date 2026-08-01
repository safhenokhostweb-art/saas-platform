@extends(config('crm.layouts.admin'))

@section('title', 'خطوط لوله فروش')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">خطوط لوله فروش — سایت #{{ $siteId }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('crm.admin.pipelines.store', $siteId) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="نام خط لوله" required></div>
        <div class="col-md-3"><label><input type="checkbox" name="is_default" value="1" class="form-check-input"> پیش‌فرض</label></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">افزودن</button></div>
    </form>

    @foreach ($pipelines as $pipeline)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <strong>{{ $pipeline->name }} @if ($pipeline->is_default) <span class="badge bg-primary">پیش‌فرض</span> @endif</strong>
                <form method="POST" action="{{ route('crm.admin.pipelines.destroy', [$siteId, $pipeline]) }}" onsubmit="return confirm('حذف شود؟');">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">حذف خط لوله</button>
                </form>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead><tr><th>مرحله</th><th>احتمال</th><th>نوع</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pipeline->stages as $stage)
                            <tr>
                                <td>{{ $stage->name }}</td>
                                <td>{{ $stage->probability }}%</td>
                                <td>{{ $stage->type }}</td>
                                <td>
                                    <form method="POST" action="{{ route('crm.admin.pipelines.stages.destroy', [$siteId, $pipeline, $stage]) }}" onsubmit="return confirm('حذف شود؟');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <form method="POST" action="{{ route('crm.admin.pipelines.stages.store', [$siteId, $pipeline]) }}" class="row g-2">
                    @csrf
                    <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="نام مرحله" required></div>
                    <div class="col-md-3"><input type="number" name="probability" class="form-control" placeholder="احتمال %" min="0" max="100" required></div>
                    <div class="col-md-3">
                        <select name="type" class="form-select">
                            <option value="open">باز</option>
                            <option value="won">برد</option>
                            <option value="lost">باخت</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-outline-primary w-100">افزودن مرحله</button></div>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
