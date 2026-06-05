@extends('layouts.admin')

@section('header', 'مدیریت سایت‌ها')

@section('content')
<x-module-features module='sites' />
<p class='mb-4'>
    <a href="{{ route('admin.sites.create') }}" class='btn btn-primary'>ایجاد سایت جدید</a>
</p>

@if(isset($filterCustomer) || isset($filterModule))
<div class='mb-4 p-3 bg-blue-50 rounded border border-blue-200 text-sm'>
    فیلتر فعال:
    @if(isset($filterCustomer)) مشتری: <strong>{{ $filterCustomer->name ?? $filterCustomer->company ?? '—' }}</strong> @endif
    @if(isset($filterModule)) ماژول: <strong>{{ $filterModule->title ?? $filterModule->slug }}</strong> @endif
    <a href="{{ route('admin.sites.index') }}" class='mr-2 text-blue-600 hover:underline'>حذف فیلتر</a>
</div>
@endif

<div class='bg-white rounded shadow overflow-hidden'>
    <div class='overflow-x-auto'>
        <table class='w-full text-sm'>
            <thead class='bg-gray-50'>
                <tr>
                    <th class='p-3 text-right'>#</th>
                    <th class='p-3 text-right'>عنوان</th>
                    <th class='p-3 text-right'>مشتری</th>
                    <th class='p-3 text-right'>دامنه</th>
                    <th class='p-3 text-right'>وضعیت</th>
                    <th class='p-3 text-right'>ماژول‌ها</th>
                    <th class='p-3 text-right'>تاریخ ایجاد</th>
                    <th class='p-3 text-right'>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sites as $site)
                <tr class='border-t hover:bg-gray-50'>
                    <td class='p-3'>{{ $site->id }}</td>
                    <td class='p-3 font-medium'>{{ $site->title }}</td>
                    <td class='p-3'>{{ $site->customer?->name ?? $site->customer?->company ?? '—' }}</td>
                    <td class='p-3' dir='ltr'>{{ $site->domain ?? '—' }}</td>
                    <td class='p-3'>
                        @if($site->is_active)
                            <span class='px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700'>فعال</span>
                        @else
                            <span class='px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700'>غیرفعال</span>
                        @endif
                    </td>
                    <td class='p-3'>
                        @php $activeModules = $site->modules->where('pivot.is_active', true); @endphp
                        @if($activeModules->count() > 0)
                            <div class='flex flex-wrap gap-1'>
                                @foreach($activeModules->take(3) as $module)
                                    <span class='text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded'>{{ $module->title ?? $module->slug }}</span>
                                @endforeach
                                @if($activeModules->count() > 3)
                                    <span class='text-xs text-gray-500'>+{{ $activeModules->count() - 3 }}</span>
                                @endif
                            </div>
                        @else
                            <span class='text-gray-400'>—</span>
                        @endif
                    </td>
                    <td class='p-3 text-gray-500'>{{ $site->created_at->format('Y/m/d') }}</td>
                    <td class='p-3'>
                        <a href="{{ route('admin.sites.edit', $site) }}" class='text-blue-600 hover:underline text-xs'>ویرایش</a>
                        <a href="{{ route('admin.sites.license.create', $site) }}" class='text-indigo-600 hover:underline text-xs mr-2'>صدور لایسنس</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan='8' class='p-4 text-center text-gray-500'>هیچ سایتی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@include('admin.sites.guide')
@endsection
