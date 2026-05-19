{{-- Filters & Search Bar with Alpine.js --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-end gap-2"
          @submit.prevent="applyFilters">
        <div>
            <label class="block text-xs text-gray-500 mb-1">موبایل</label>
            <input type="text" x-model="filters.mobile" class="form-control w-36" placeholder="موبایل">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">ایمیل</label>
            <input type="text" x-model="filters.email" class="form-control w-36" placeholder="ایمیل">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">نام</label>
            <input type="text" x-model="filters.name" class="form-control w-36" placeholder="نام">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
            <select x-model="filters.status" class="form-select w-32">
                <option value="">همه</option>
                <option value="active">فعال</option>
                <option value="inactive">غیرفعال</option>
                <option value="locked">قفل شده</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">نقش</label>
            <select x-model="filters.role" class="form-select w-32">
                <option value="">همه</option>
                @if(isset($roles))
                    @foreach($roles as $role)
                        <option value="{{ $role->slug }}">{{ $role->name }}</option>
                    @endforeach
                @endif
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">حذف شده</label>
            <select x-model="filters.trashed" class="form-select w-28">
                <option value="0">فقط فعال</option>
                <option value="1">حذف شده</option>
                <option value="all">همه</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">🔍 جستجو</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetFilters()">✕ پاک کردن</button>
    </form>

    <div class="flex gap-2">
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <svg class="w-4 h-4 ml-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            کاربر جدید
        </a>
        <button id="export-csv-btn" data-url="{{ route('admin.users.export-csv') }}"
                @click="exportCSV()" class="btn btn-outline-secondary">
            📥 خروجی CSV
        </button>
        <a href="{{ route('admin.users.import') }}" class="btn btn-outline-secondary">📤 وارد کردن</a>
    </div>
</div>