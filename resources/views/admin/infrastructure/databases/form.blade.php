@extends('layouts.admin')

@section('header', $database->exists ? 'ویرایش سرور دیتابیس' : 'افزودن سرور دیتابیس')

@section('content')

<div class="max-w-3xl">

    <div class="flex items-center gap-2 mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.infrastructure.overview') }}" class="hover:text-indigo-600">زیرساخت</a>
        <span>/</span>
        <a href="{{ route('admin.infrastructure.databases.index') }}" class="hover:text-indigo-600">دیتابیس‌ها</a>
        <span>/</span>
        <span class="text-gray-800">{{ $database->exists ? $database->name : 'جدید' }}</span>
    </div>

    <form method="POST" action="{{ $database->exists ? route('admin.infrastructure.databases.update', $database) : route('admin.infrastructure.databases.store') }}">
        @csrf
        @if($database->exists) @method('PUT') @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">نام <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') border-red-400 @enderror" value="{{ old('name', $database->name) }}" placeholder="MySQL Production">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">موتور <span class="text-red-500">*</span></label>
                    <select name="engine" class="form-select">
                        @foreach(['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite', 'redis' => 'Redis', 'mongodb' => 'MongoDB'] as $v => $l)
                        <option value="{{ $v }}" {{ old('engine', $database->engine ?? 'mysql') === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">نسخه</label>
                    <input type="text" name="version" class="form-control" value="{{ old('version', $database->version) }}" placeholder="8.0.35" dir="ltr">
                </div>
                <div>
                    <label class="form-label">هاست اتصال</label>
                    <input type="text" name="host" class="form-control" value="{{ old('host', $database->host) }}" dir="ltr" placeholder="db.internal or 10.0.0.2">
                    <p class="text-xs text-gray-400 mt-1">فقط برای مستندسازی — کد از .env می‌خواند</p>
                </div>
                <div>
                    <label class="form-label">پورت</label>
                    <input type="number" name="port" class="form-control" value="{{ old('port', $database->port) }}" placeholder="3306" dir="ltr">
                </div>
                <div>
                    <label class="form-label">سرور مرتبط</label>
                    <select name="server_id" class="form-select">
                        <option value="">— انتخاب سرور —</option>
                        @foreach($servers as $server)
                        <option value="{{ $server->id }}" {{ old('server_id', $database->server_id) == $server->id ? 'selected' : '' }}>{{ $server->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">محیط <span class="text-red-500">*</span></label>
                    <select name="environment" class="form-select">
                        <option value="production" {{ old('environment', $database->environment ?? 'production') === 'production' ? 'selected' : '' }}>پروداکشن</option>
                        <option value="staging" {{ old('environment', $database->environment) === 'staging' ? 'selected' : '' }}>استیجینگ</option>
                        <option value="development" {{ old('environment', $database->environment) === 'development' ? 'selected' : '' }}>دولاپمنت</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">وضعیت <span class="text-red-500">*</span></label>
                    <select name="status" class="form-select">
                        @foreach(['online' => 'آنلاین', 'offline' => 'آفلاین', 'degraded' => 'ضعیف', 'maintenance' => 'تعمیرات'] as $v => $l)
                        <option value="{{ $v }}" {{ old('status', $database->status ?? 'online') === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">اندازه و ظرفیت</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label">اندازه فعلی (MB)</label>
                        <input type="number" name="size_mb" class="form-control" value="{{ old('size_mb', $database->size_mb) }}" min="0">
                    </div>
                    <div>
                        <label class="form-label">اندازه ماه قبل (MB)</label>
                        <input type="number" name="size_mb_prev_month" class="form-control" value="{{ old('size_mb_prev_month', $database->size_mb_prev_month) }}" min="0">
                    </div>
                    <div>
                        <label class="form-label">کانکشن‌های فعال</label>
                        <input type="number" name="active_connections" class="form-control" value="{{ old('active_connections', $database->active_connections) }}" min="0">
                    </div>
                    <div>
                        <label class="form-label">حداکثر کانکشن</label>
                        <input type="number" name="max_connections" class="form-control" value="{{ old('max_connections', $database->max_connections) }}" min="0">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">پشتیبان‌گیری</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">آخرین پشتیبان‌گیری</label>
                        <x-jalali-datepicker
                            name="last_backup_at"
                            :with-time="true"
                            placeholder="۱۴۰۳/۰۱/۰۱ ۱۴:۳۰"
                            :value="old('last_backup_at', $database->last_backup_at ? \App\Helpers\Jalali::format($database->last_backup_at, 'Y/m/d H:i') : '')" />
                    </div>
                    <div>
                        <label class="form-label">مقصد پشتیبان</label>
                        <input type="text" name="backup_storage" class="form-control" value="{{ old('backup_storage', $database->backup_storage) }}" placeholder="S3, MinIO, FTP...">
                    </div>
                    <div>
                        <label class="form-label">دوره پشتیبان‌گیری</label>
                        <input type="text" name="backup_frequency" class="form-control" value="{{ old('backup_frequency', $database->backup_frequency) }}" placeholder="روزانه، ساعتی...">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">رپلیکاسیون</h3>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="replication_enabled" value="1" id="replication_enabled"
                               {{ old('replication_enabled', $database->replication_enabled) ? 'checked' : '' }} class="rounded border-gray-300">
                        <span class="text-sm text-gray-700">رپلیکاسیون فعال است</span>
                    </label>
                    <select name="replication_role" class="form-select max-w-40">
                        <option value="">— نقش —</option>
                        <option value="primary" {{ old('replication_role', $database->replication_role) === 'primary' ? 'selected' : '' }}>Primary</option>
                        <option value="replica" {{ old('replication_role', $database->replication_role) === 'replica' ? 'selected' : '' }}>Replica</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="form-label">یادداشت</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $database->notes) }}</textarea>
            </div>

            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $database->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
                    <span class="text-sm text-gray-700">سرور فعال است</span>
                </label>
            </div>

        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="btn btn-primary">{{ $database->exists ? 'ذخیره تغییرات' : 'ثبت سرور دیتابیس' }}</button>
            <a href="{{ route('admin.infrastructure.databases.index') }}" class="btn btn-outline-secondary">انصراف</a>
        </div>
    </form>
</div>

@endsection
