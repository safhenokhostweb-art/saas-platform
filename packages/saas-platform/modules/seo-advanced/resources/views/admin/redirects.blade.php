@extends(config('seo-advanced.layouts.admin'))

@section('title', 'ریدایرکت‌ها')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">ریدایرکت‌ها — سایت #{{ $siteId }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('seo-advanced.admin.redirects.store', $siteId) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-4">
            <input type="text" name="from_path" placeholder="/مسیر-قدیمی" class="form-control" required>
        </div>
        <div class="col-md-4">
            <input type="text" name="to_path" placeholder="/مسیر-جدید" class="form-control" required>
        </div>
        <div class="col-md-2">
            <select name="status_code" class="form-select">
                <option value="301">301</option>
                <option value="302">302</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">افزودن</button>
        </div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>از</th>
                <th>به</th>
                <th>کد وضعیت</th>
                <th>فعال</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($redirects as $redirect)
                <tr>
                    <td>{{ $redirect->from_path }}</td>
                    <td>{{ $redirect->to_path }}</td>
                    <td>{{ $redirect->status_code }}</td>
                    <td>{{ $redirect->is_active ? 'بله' : 'خیر' }}</td>
                    <td>
                        <a href="{{ route('seo-advanced.admin.redirects.edit', [$siteId, $redirect->id]) }}" class="btn btn-sm btn-outline-secondary">ویرایش</a>
                        <form method="POST" action="{{ route('seo-advanced.admin.redirects.destroy', [$siteId, $redirect->id]) }}" onsubmit="return confirm('حذف شود؟')" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">هیچ ریدایرکتی ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
