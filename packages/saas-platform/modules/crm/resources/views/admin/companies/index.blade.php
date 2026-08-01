@extends(config('crm.layouts.admin'))

@section('title', 'شرکت‌ها')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">شرکت‌ها — سایت #{{ $siteId }}</h1>

    <div class="mb-3">
        <a href="{{ route('crm.admin.import.show', [$siteId, 'company']) }}" class="btn btn-outline-primary btn-sm">Import CSV</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4"><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="جستجو نام/دامنه"></div>
        <div class="col-md-3">
            <select name="tag" class="form-select">
                <option value="">همه برچسب‌ها</option>
                @foreach ($tags as $tag)
                    <option value="{{ $tag->id }}" @selected(request('tag') == $tag->id)>{{ $tag->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-check-label"><input type="checkbox" name="favorites" value="1" @checked(request('favorites')) class="form-check-input"> فقط ویژه</label></div>
        <div class="col-md-2"><label class="form-check-label"><input type="checkbox" name="archived" value="1" @checked(request('archived')) class="form-check-input"> بایگانی‌شده</label></div>
        <div class="col-md-1"><button class="btn btn-outline-secondary w-100">فیلتر</button></div>
    </form>

    <form method="POST" action="{{ route('crm.admin.companies.store', $siteId) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="نام شرکت" required></div>
        <div class="col-md-3"><input type="text" name="domain" class="form-control" placeholder="دامنه"></div>
        <div class="col-md-2">
            <select name="lifecycle_stage" class="form-select">
                <option value="lead">Lead</option>
                <option value="prospect">Prospect</option>
                <option value="customer">Customer</option>
            </select>
        </div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">افزودن</button></div>
    </form>

    <form method="POST" action="{{ route('crm.admin.companies.bulk', $siteId) }}" id="bulk-form">
        @csrf
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th></th>
                    <th>نام</th>
                    <th>دامنه</th>
                    <th>مرحله</th>
                    <th>ویژه</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="{{ $company->id }}"></td>
                        <td>{{ $company->name }}</td>
                        <td>{{ $company->domain }}</td>
                        <td>{{ $company->lifecycle_stage }}</td>
                        <td>{{ $company->is_favorite ? '⭐' : '' }}</td>
                        <td>
                            <form method="POST" action="{{ route('crm.admin.companies.destroy', [$siteId, $company]) }}" class="d-inline" onsubmit="return confirm('حذف شود؟');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">شرکتی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="d-flex gap-2 mb-4">
            <button name="action" value="archive" class="btn btn-sm btn-outline-secondary">بایگانی</button>
            <button name="action" value="unarchive" class="btn btn-sm btn-outline-secondary">خروج از بایگانی</button>
            <button name="action" value="favorite" class="btn btn-sm btn-outline-warning">ویژه</button>
            <button name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف گروهی؟');">حذف گروهی</button>
        </div>
    </form>

    {{ $companies->links() }}
</div>
@endsection
