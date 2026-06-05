@extends('layouts.admin')

@section('header', 'ایجاد تم جدید')

@section('content')
<form action="{{ route('admin.theme-builder.store') }}" method="post" class="max-w-xl">
    @csrf
    <div class="card">
        <div class="card-body space-y-4">
            <div>
                <label class="form-label">نام تم</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="مثلاً تم شرکتی">
            </div>
            <div>
                <label class="form-label">شناسه (slug) — اختیاری</label>
                <input type="text" name="slug" class="form-control" value="{{ old('slug') }}" placeholder="خالی = خودکار از نام">
            </div>
            <div>
                <label class="form-label">توضیح کوتاه</label>
                <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="اختیاری">
            </div>
            <div>
                <label class="form-label">وابسته به سایت — اختیاری (خالی = قالب پلتفرم)</label>
                <select name="site_id" class="form-select">
                    <option value="">قالب پلتفرم (برای همه)</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" {{ old('site_id', $siteId) == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">ایجاد تم</button>
                <a href="{{ route('admin.theme-builder.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </div>
    </div>
</form>
@endsection
