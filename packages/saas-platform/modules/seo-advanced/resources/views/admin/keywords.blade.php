@extends(config('seo-advanced.layouts.admin'))

@section('title', 'پیگیری کلمات کلیدی')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">پیگیری کلمات کلیدی — سایت #{{ $siteId }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('seo-advanced.admin.keywords.store', $siteId) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-4">
            <input type="text" name="keyword" class="form-control" placeholder="کلمه کلیدی" required>
        </div>
        <div class="col-md-4">
            <input type="text" name="target_url" class="form-control" placeholder="آدرس هدف (Target URL)">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="active">فعال</option>
                <option value="paused">متوقف‌شده</option>
                <option value="archived">بایگانی‌شده</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary w-100">افزودن</button>
        </div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>کلمه کلیدی</th>
                <th>آدرس هدف</th>
                <th>وضعیت</th>
                <th>آخرین رتبه</th>
                <th>ثبت رتبه جدید</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($keywords as $keyword)
                <tr>
                    <td>{{ $keyword->keyword }}</td>
                    <td>{{ $keyword->target_url ?? '—' }}</td>
                    <td>{{ $keyword->status }}</td>
                    <td>{{ $keyword->position ?? '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('seo-advanced.admin.keywords.position', [$siteId, $keyword]) }}" class="d-flex gap-1">
                            @csrf
                            <input type="number" name="position" class="form-control form-control-sm" style="width: 80px" min="1" required>
                            <button type="submit" class="btn btn-sm btn-outline-primary">ثبت</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('seo-advanced.admin.keywords.destroy', [$siteId, $keyword]) }}" onsubmit="return confirm('حذف شود؟');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">هنوز هیچ کلمه کلیدی ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $keywords->links() }}
</div>
@endsection
