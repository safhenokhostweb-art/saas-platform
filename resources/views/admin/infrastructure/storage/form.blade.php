@extends('layouts.admin')

@section('header', $provider->exists ? 'ویرایش استوریج' : 'افزودن سرویس استوریج')

@section('content')

<div class="max-w-3xl">

    <div class="flex items-center gap-2 mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.infrastructure.overview') }}" class="hover:text-indigo-600">زیرساخت</a>
        <span>/</span>
        <a href="{{ route('admin.infrastructure.storage.index') }}" class="hover:text-indigo-600">استوریج</a>
        <span>/</span>
        <span class="text-gray-800">{{ $provider->exists ? $provider->name : 'جدید' }}</span>
    </div>

    <form method="POST" action="{{ $provider->exists ? route('admin.infrastructure.storage.update', $provider) : route('admin.infrastructure.storage.store') }}">
        @csrf
        @if($provider->exists) @method('PUT') @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">نام <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') border-red-400 @enderror" value="{{ old('name', $provider->name) }}" placeholder="Primary MinIO, Backup S3...">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">درایور <span class="text-red-500">*</span></label>
                    <select name="driver" class="form-select">
                        @foreach(['local' => 'Local', 's3' => 'S3 (AWS)', 'minio' => 'MinIO', 'ftp' => 'FTP', 'sftp' => 'SFTP'] as $v => $l)
                        <option value="{{ $v }}" {{ old('driver', $provider->driver ?? 'local') === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Endpoint (برای MinIO / S3-compatible)</label>
                    <input type="url" name="endpoint" class="form-control" value="{{ old('endpoint', $provider->endpoint) }}" dir="ltr" placeholder="https://minio.example.com">
                </div>
                <div>
                    <label class="form-label">Bucket</label>
                    <input type="text" name="bucket" class="form-control" value="{{ old('bucket', $provider->bucket) }}" dir="ltr">
                </div>
                <div>
                    <label class="form-label">Region</label>
                    <input type="text" name="region" class="form-control" value="{{ old('region', $provider->region) }}" dir="ltr" placeholder="us-east-1">
                </div>
                <div>
                    <label class="form-label">نام دیسک Laravel</label>
                    <input type="text" name="disk_name" class="form-control" value="{{ old('disk_name', $provider->disk_name) }}" dir="ltr" placeholder="s3, minio, backup...">
                    <p class="text-xs text-gray-400 mt-1">نام دیسک در config/filesystems.php</p>
                </div>
                <div>
                    <label class="form-label">هدف / نقش <span class="text-red-500">*</span></label>
                    <select name="purpose" class="form-select">
                        @foreach(['primary' => 'اصلی', 'backup' => 'پشتیبان‌گیری', 'cdn' => 'CDN', 'tenant-uploads' => 'آپلود تِننت', 'exports' => 'خروجی / گزارش'] as $v => $l)
                        <option value="{{ $v }}" {{ old('purpose', $provider->purpose ?? 'primary') === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">وضعیت <span class="text-red-500">*</span></label>
                    <select name="status" class="form-select">
                        @foreach(['active' => 'فعال', 'degraded' => 'ضعیف', 'full' => 'پر', 'inactive' => 'غیرفعال'] as $v => $l)
                        <option value="{{ $v }}" {{ old('status', $provider->status ?? 'active') === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">ظرفیت و مصرف</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label">ظرفیت کل (GB)</label>
                        <input type="number" name="capacity_gb" class="form-control" value="{{ old('capacity_gb', $provider->capacity_gb) }}" min="0">
                    </div>
                    <div>
                        <label class="form-label">مصرف فعلی (GB)</label>
                        <input type="number" name="used_gb" class="form-control" value="{{ old('used_gb', $provider->used_gb) }}" min="0">
                    </div>
                    <div>
                        <label class="form-label">مصرف ماه قبل (GB)</label>
                        <input type="number" name="used_gb_prev_month" class="form-control" value="{{ old('used_gb_prev_month', $provider->used_gb_prev_month) }}" min="0">
                    </div>
                    <div>
                        <label class="form-label">هزینه ماهانه</label>
                        <input type="number" name="monthly_cost" class="form-control" value="{{ old('monthly_cost', $provider->monthly_cost) }}" min="0" step="0.01">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">تاریخ آخرین اندازه‌گیری</label>
                    <x-jalali-datepicker
                        name="last_measured_at"
                        :value="old('last_measured_at', $provider->last_measured_at ? \App\Helpers\Jalali::format($provider->last_measured_at, 'Y/m/d') : '')" />
                </div>
            </div>

            <div>
                <label class="form-label">یادداشت</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $provider->notes) }}</textarea>
            </div>

            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $provider->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
                    <span class="text-sm text-gray-700">سرویس فعال است</span>
                </label>
            </div>

        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="btn btn-primary">{{ $provider->exists ? 'ذخیره تغییرات' : 'ثبت سرویس' }}</button>
            <a href="{{ route('admin.infrastructure.storage.index') }}" class="btn btn-outline-secondary">انصراف</a>
        </div>
    </form>
</div>

@endsection
