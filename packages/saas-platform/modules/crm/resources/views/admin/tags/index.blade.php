@extends(config('crm.layouts.admin'))

@section('title', 'برچسب‌ها')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">برچسب‌ها — سایت #{{ $siteId }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('crm.admin.tags.store', $siteId) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="نام برچسب" required></div>
        <div class="col-md-3"><input type="color" name="color" class="form-control form-control-color" value="#6b7280"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">افزودن</button></div>
    </form>

    <table class="table table-bordered">
        <thead><tr><th>نام</th><th>رنگ</th><th></th></tr></thead>
        <tbody>
            @forelse ($tags as $tag)
                <tr>
                    <td>{{ $tag->name }}</td>
                    <td><span class="badge" style="background-color: {{ $tag->color }}">{{ $tag->color }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('crm.admin.tags.destroy', [$siteId, $tag]) }}" onsubmit="return confirm('حذف شود؟');">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-muted">برچسبی ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
