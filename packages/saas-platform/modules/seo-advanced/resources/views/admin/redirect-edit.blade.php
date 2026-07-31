@extends(config('seo-advanced.layouts.admin'))

@section('title', 'ویرایش ریدایرکت')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">ویرایش ریدایرکت — سایت #{{ $siteId }}</h1>

    <form method="POST" action="{{ route('seo-advanced.admin.redirects.update', [$siteId, $redirect->id]) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="form-label">از (from)</label>
            <input type="text" name="from_path" class="form-control" value="{{ old('from_path', $redirect->from_path) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">به (to)</label>
            <input type="text" name="to_path" class="form-control" value="{{ old('to_path', $redirect->to_path) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">کد وضعیت</label>
            <select name="status_code" class="form-select">
                <option value="301" @selected($redirect->status_code === 301)>301 (دائمی)</option>
                <option value="302" @selected($redirect->status_code === 302)>302 (موقت)</option>
            </select>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked($redirect->is_active)>
            <label class="form-check-label" for="is_active">فعال</label>
        </div>

        <button type="submit" class="btn btn-primary">ذخیره</button>
        <a href="{{ route('seo-advanced.admin.redirects', $siteId) }}" class="btn btn-outline-secondary">انصراف</a>
    </form>
</div>
@endsection
