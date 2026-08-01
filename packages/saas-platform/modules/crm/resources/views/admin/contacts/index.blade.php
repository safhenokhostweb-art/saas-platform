@extends(config('crm.layouts.admin'))

@section('title', 'مخاطبین')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">مخاطبین — سایت #{{ $siteId }}</h1>

    <div class="mb-3">
        <a href="{{ route('crm.admin.import.show', [$siteId, 'contact']) }}" class="btn btn-outline-primary btn-sm">Import CSV</a>
        <a href="{{ route('crm.admin.export.contacts', $siteId) }}" class="btn btn-outline-secondary btn-sm">Export CSV</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4"><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="جستجو نام/ایمیل/تلفن"></div>
        <div class="col-md-2"><label class="form-check-label"><input type="checkbox" name="favorites" value="1" @checked(request('favorites')) class="form-check-input"> فقط ویژه</label></div>
        <div class="col-md-2"><label class="form-check-label"><input type="checkbox" name="archived" value="1" @checked(request('archived')) class="form-check-input"> بایگانی‌شده</label></div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100">فیلتر</button></div>
    </form>

    <form method="POST" action="{{ route('crm.admin.contacts.store', $siteId) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-2"><input type="text" name="first_name" class="form-control" placeholder="نام" required></div>
        <div class="col-md-2"><input type="text" name="last_name" class="form-control" placeholder="نام خانوادگی"></div>
        <div class="col-md-2"><input type="email" name="email" class="form-control" placeholder="ایمیل"></div>
        <div class="col-md-2"><input type="text" name="phone" class="form-control" placeholder="تلفن"></div>
        <div class="col-md-2">
            <select name="company_id" class="form-select">
                <option value="">— بدون شرکت —</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">افزودن</button></div>
    </form>

    <form method="POST" action="{{ route('crm.admin.contacts.bulk', $siteId) }}">
        @csrf
        <table class="table table-bordered">
            <thead>
                <tr><th></th><th>نام</th><th>ایمیل</th><th>شرکت</th><th>مرحله</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($contacts as $contact)
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="{{ $contact->id }}"></td>
                        <td><a href="{{ route('crm.admin.contacts.profile', [$siteId, $contact]) }}">{{ $contact->full_name }}</a></td>
                        <td>{{ $contact->email }}</td>
                        <td>{{ $contact->company?->name }}</td>
                        <td>{{ $contact->lifecycle_stage }}</td>
                        <td>
                            <form method="POST" action="{{ route('crm.admin.contacts.destroy', [$siteId, $contact]) }}" class="d-inline" onsubmit="return confirm('حذف شود؟');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">مخاطبی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="d-flex gap-2 mb-4">
            <button name="action" value="archive" class="btn btn-sm btn-outline-secondary">بایگانی</button>
            <button name="action" value="favorite" class="btn btn-sm btn-outline-warning">ویژه</button>
            <button name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف گروهی؟');">حذف گروهی</button>
        </div>
    </form>

    {{ $contacts->links() }}
</div>
@endsection
