@extends('layouts.admin')

@section('header', 'کپی تم: ' . $theme->name)

@section('content')
<form action="{{ route('admin.theme-builder.clone', $theme) }}" method="post" class="max-w-xl">
    @csrf
    <div class="card">
        <div class="card-body space-y-4">
            <p class="text-gray-600">یک تم جدید با همان هدر، فوتر و استایل‌ها ایجاد می‌شود. نام جدید وارد کنید.</p>
            <div>
                <label class="form-label">نام تم جدید</label>
                <input type="text" name="name" class="form-control" value="کپی {{ $theme->name }}" required>
            </div>
            <div>
                <label class="form-label">سایت (اختیاری)</label>
                <select name="site_id" class="form-select">
                    <option value="">قالب پلتفرم</option>
                    @foreach(\App\Models\Site::orderBy('title')->get(['id', 'title']) as $s)
                        <option value="{{ $s->id }}">{{ $s->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">ایجاد کپی</button>
                <a href="{{ route('admin.theme-builder.edit', $theme) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </div>
    </div>
</form>
@endsection
