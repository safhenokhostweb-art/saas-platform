@extends('layouts.admin')

@section('header', 'ظ…ط¯غŒط±غŒطھ ع©ط§ط±ط¨ط±ط§ظ† ظ¾ظ„طھظپط±ظ…')

@section('content')

@include('admin.users.guide')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-2">
        <input type="text" name="mobile" class="form-control w-48" placeholder="ط¬ط³طھط¬ظˆ ط¨ط§ ظ…ظˆط¨ط§غŒظ„" value="{{ request('mobile') }}">
        @if(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active'))
        <select name="status" class="form-select w-40">
            <option value="">ظ‡ظ…ظ‡ ظˆط¶ط¹غŒطھâ€Œظ‡ط§</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>ظپط¹ط§ظ„</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>ط؛غŒط±ظپط¹ط§ظ„</option>
        </select>
        @endif
        <button type="submit" class="btn btn-primary">ط¬ط³طھط¬ظˆ</button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="p-3 font-bold">#</th>
                    <th class="p-3 font-bold">ظ…ظˆط¨ط§غŒظ„</th>
                    <th class="p-3 font-bold">ظ†ط§ظ… (ظ¾ط±ظˆظپط§غŒظ„)</th>
                    <th class="p-3 font-bold">طھط§ط±غŒط® ط«ط¨طھâ€Œظ†ط§ظ…</th>
                    @if(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active'))
                    <th class="p-3 font-bold">ظˆط¶ط¹غŒطھ</th>
                    @endif
                    <th class="p-3 font-bold">ظ…ط´طھط±غŒ ظ…ط±طھط¨ط·</th>
                    <th class="p-3 font-bold">ط¹ظ…ظ„غŒط§طھ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="p-3">{{ $user->id }}</td>
                        <td class="p-3 font-medium">{{ $user->mobile }}</td>
                        <td class="p-3">{{ $user->profile->full_name ?? 'â€”' }}</td>
                        <td class="p-3 text-sm text-gray-600">{{ $user->created_at ? \App\Helpers\Jalali::formatFa($user->created_at, 'Y/m/d H:i') : 'â€”' }}</td>
                        @if(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active'))
                        <td class="p-3">
                            @if($user->id !== auth()->id())
                                <form action="{{ route('admin.users.toggle', $user) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="px-2 py-1 rounded text-sm {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $user->is_active ? 'ظپط¹ط§ظ„' : 'ط؛غŒط±ظپط¹ط§ظ„' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-500">ط´ظ…ط§</span>
                            @endif
                        </td>
                        @endif
                        <td class="p-3">
                            @if($user->customer)
                                <a href="{{ route('admin.customers.edit', $user->customer) }}" class="text-blue-600 hover:underline">{{ $user->customer->company_name ?: $user->customer->name }}</a>
                            @else
                                â€”
                            @endif
                        </td>
                        <td class="p-3">
                            @if($user->is_admin)
                                <span class="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded">ط§ط¯ظ…غŒظ†</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ \Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active') ? 7 : 6 }}" class="p-6 text-center text-gray-500">ع©ط§ط±ط¨ط±غŒ غŒط§ظپطھ ظ†ط´ط¯.</td>
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
    ط¨ط±ط§غŒ ظپط¹ط§ظ„/ط؛غŒط±ظپط¹ط§ظ„ ع©ط±ط¯ظ† ط§ع©ط§ظ†طھ ع©ط§ط±ط¨ط±ط§ظ†طŒ ط¯ط± طھط±ظ…غŒظ†ط§ظ„ ط§ط¬ط±ط§ ع©ظ†غŒط¯: <code class="bg-amber-100 px-1 rounded">php artisan migrate</code>
</div>
@endif
@endsection

