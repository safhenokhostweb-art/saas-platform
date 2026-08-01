@extends(config('crm.layouts.admin'))

@section('title', 'سرنخ‌ها')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">سرنخ‌ها — سایت #{{ $siteId }}</h1>

    <div class="mb-3">
        <a href="{{ route('crm.admin.import.show', [$siteId, 'lead']) }}" class="btn btn-outline-primary btn-sm">Import CSV</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4"><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="جستجو"></div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">همه وضعیت‌ها</option>
                @foreach (['new', 'contacted', 'qualified', 'unqualified', 'converted'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100">فیلتر</button></div>
    </form>

    <form method="POST" action="{{ route('crm.admin.leads.store', $siteId) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-2"><input type="text" name="first_name" class="form-control" placeholder="نام" required></div>
        <div class="col-md-2"><input type="text" name="last_name" class="form-control" placeholder="نام خانوادگی"></div>
        <div class="col-md-2"><input type="email" name="email" class="form-control" placeholder="ایمیل"></div>
        <div class="col-md-2"><input type="text" name="company_name" class="form-control" placeholder="نام شرکت"></div>
        <div class="col-md-2"><input type="text" name="source" class="form-control" placeholder="منبع"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">افزودن</button></div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr><th>نام</th><th>ایمیل</th><th>شرکت</th><th>منبع</th><th>وضعیت</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td>{{ $lead->full_name }}</td>
                    <td>{{ $lead->email }}</td>
                    <td>{{ $lead->company_name }}</td>
                    <td>{{ $lead->source }}</td>
                    <td>{{ $lead->status }}</td>
                    <td>
                        @if (!$lead->isConverted())
                            <form method="POST" action="{{ route('crm.admin.leads.convert', [$siteId, $lead]) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="create_company" value="1">
                                <input type="hidden" name="create_opportunity" value="1">
                                <button class="btn btn-sm btn-outline-success">تبدیل به مخاطب</button>
                            </form>
                        @else
                            <span class="badge bg-success">تبدیل‌شده</span>
                        @endif
                        <form method="POST" action="{{ route('crm.admin.leads.destroy', [$siteId, $lead]) }}" class="d-inline" onsubmit="return confirm('حذف شود؟');">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">سرنخی ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $leads->links() }}
</div>
@endsection
