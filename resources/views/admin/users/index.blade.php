@extends('layouts.admin')

@section('header', 'مدیریت کاربران پلتفرم')

@section('content')

@include('admin.users.guide')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-2">
        <input type="text" name="mobile" class="form-control w-48" placeholder="جستجو با موبایل" value="{{ request('mobile') }}">
        @if(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active'))
        <select name="status" class="form-select w-40">
            <option value="">همه وضعیت‌ها</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>فعال</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>غیرفعال</option>
        </select>
        @endif
        <button type="submit" class="btn btn-primary">جستجو</button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="p-3 font-bold">#</th>
                    <th class="p-3 font-bold">موبایل</th>
                    <th class="p-3 font-bold">نام (پروفایل)</th>
                    <th class="p-3 font-bold">تاریخ ثبت‌نام</th>
                    @if(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active'))
                    <th class="p-3 font-bold">وضعیت</th>
                    @endif
                    <th class="p-3 font-bold">مشتری مرتبط</th>
                    <th class="p-3 font-bold">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="p-3">{{ $user->id }}</td>
                        <td class="p-3 font-medium">{{ $user->mobile }}</td>
                        <td class="p-3">{{ $user->profile->full_name ?? '—' }}</td>
                        <td class="p-3 text-sm text-gray-600">{{ $user->created_at ? \App\Helpers\Jalali::formatFa($user->created_at, 'Y/m/d H:i') : '—' }}</td>
                        @if(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active'))
                        <td class="p-3">
                            @if($user->id !== auth()->id())
                                <form action="{{ route('admin.users.toggle', $user) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="px-2 py-1 rounded text-sm {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $user->is_active ? 'فعال' : 'غیرفعال' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-500">شما</span>
                            @endif
                        </td>
                        @endif
                        <td class="p-3">
                            @if($user->customer)
                                <a href="{{ route('admin.customers.edit', $user->customer) }}" class="text-blue-600 hover:underline">{{ $user->customer->company_name ?: $user->customer->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="p-3">
                            @if($user->is_admin)
                                <span class="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded">ادمین</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ \Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active') ? 7 : 6 }}" class="p-6 text-center text-gray-500">کاربری یافت نشد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-gray-200">
        {{ $users->links() }}
    </div>
</div>

@if(!\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active'))
<div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
    برای فعال/غیرفعال کردن اکانت کاربران، در ترمینال اجرا کنید: <code class="bg-amber-100 px-1 rounded">php artisan migrate</code>
</div>
@endif
@endsection
