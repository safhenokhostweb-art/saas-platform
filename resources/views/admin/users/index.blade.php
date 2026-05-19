@extends('layouts.admin')

@section('header', 'مدیریت کاربران پلتفرم')

@section('content')
@include('admin.users.guide')

<x-module-features module="users" />

<div x-data="usersIndex()" x-init="init()" class="space-y-6">

    {{-- Stats Cards --}}
    @if(isset($stats) && is_array($stats))
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-indigo-600">{{ number_format($stats['total'] ?? 0) }}</div>
            <div class="text-xs text-gray-500 mt-1">کل کاربران</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-green-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['active'] ?? 0) }}</div>
            <div class="text-xs text-gray-500 mt-1">فعال</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-red-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-red-600">{{ number_format($stats['inactive'] ?? 0) }}</div>
            <div class="text-xs text-gray-500 mt-1">غیرفعال</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-amber-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-amber-600">{{ number_format($stats['locked'] ?? 0) }}</div>
            <div class="text-xs text-gray-500 mt-1">قفل شده</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-blue-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['admins'] ?? 0) }}</div>

            <div class="text-xs text-gray-500 mt-1">ادمین</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-purple-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-purple-600">{{ number_format($stats['two_factor_enabled'] ?? 0) }}</div>
            <div class="text-xs text-gray-500 mt-1">2FA فعال</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-teal-100 text-center hover:shadow-md transition-shadow">
            <div class="text-2xl font-bold text-teal-600">{{ number_format($stats['new_today'] ?? 0) }}</div>
            <div class="text-xs text-gray-500 mt-1">جدید امروز</div>
        </div>
    </div>
    @endif


    @include('admin.users._toast')

    @include('admin.users._table')

    @include('admin.users._bulk_modal')

    @include('admin.users._delete_modal')

    <form id="bulk-form" method="POST" class="hidden"
          data-routes='{{ json_encode(["activate" => route("admin.users.bulk-toggle"), "deactivate" => route("admin.users.bulk-toggle"), "delete" => route("admin.users.bulk-delete"), "restore" => route("admin.users.bulk-restore")]) }}'>
        @csrf
        <input type="hidden" name="user_ids" id="bulk-ids">
        <input type="hidden" name="action" id="bulk-action-hidden">
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('usersIndex', usersIndex);
    });
</script>
@endpush

<script>
function sortByClick(field) {
    var url = new URL(window.location.href);
    var dir = url.searchParams.get('sort') === field && url.searchParams.get('direction') === 'asc' ? 'desc' : 'asc';
    url.searchParams.set('sort', field);
    url.searchParams.set('direction', dir);
    window.location.href = url.toString();
}
</script>
@endsection

