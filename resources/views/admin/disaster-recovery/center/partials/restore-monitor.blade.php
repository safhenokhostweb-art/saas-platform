<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">مانیتور بازیابی</h2>
        </div>
        <a href="{{ route('admin.disaster-recovery.logs') }}" class="text-xs text-blue-600 hover:underline">مشاهده همه</a>
    </div>

    {{-- Stats Cards (30d) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-0 divide-x divide-x-reverse divide-gray-100 border-b border-gray-100">
        <div class="p-4 text-center">
            <div class="text-2xl font-bold text-gray-900">{{ $restoreStats['total'] }}</div>
            <div class="text-xs text-gray-500 mt-1">کل بازیابی (۳۰ روز)</div>
        </div>
        <div class="p-4 text-center">
            <div class="text-2xl font-bold text-green-600">{{ $restoreStats['success'] }}</div>
            <div class="text-xs text-gray-500 mt-1">موفق</div>
        </div>
        <div class="p-4 text-center">
            <div class="text-2xl font-bold {{ $restoreStats['failed'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $restoreStats['failed'] }}</div>
            <div class="text-xs text-gray-500 mt-1">ناموفق</div>
        </div>
        <div class="p-4 text-center">
            <div class="text-2xl font-bold text-blue-600">{{ $restoreStats['success_rate'] }}%</div>
            <div class="text-xs text-gray-500 mt-1">نرخ موفقیت</div>
        </div>
    </div>

    {{-- Recent Events Table --}}
    @if($recentRestores->isEmpty())
    <div class="py-12 text-center text-gray-400 text-sm">هیچ بازیابی‌ای انجام نشده</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="text-right px-4 py-3 font-medium">نوع</th>
                    <th class="text-right px-4 py-3 font-medium">هدف</th>
                    <th class="text-right px-4 py-3 font-medium">وضعیت</th>
                    <th class="text-right px-4 py-3 font-medium">اجراکننده</th>
                    <th class="text-right px-4 py-3 font-medium">زمان</th>
                    <th class="text-right px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recentRestores as $log)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                            {{ match($log->restore_type) {
                                'platform_database' => 'DB پلتفرم',
                                'platform_storage'  => 'Storage پلتفرم',
                                'tenant_database'   => 'DB تِنِنت',
                                'tenant_storage'    => 'Storage تِنِنت',
                                default             => $log->restore_type,
                            } }}
                        </span>
                        @if($log->dry_run)
                        <span class="mr-1 px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-600">شبیه‌سازی</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $log->site?->name ?? 'پلتفرم' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded text-xs font-medium
                            {{ match($log->status) {
                                'success'          => 'bg-green-100 text-green-700',
                                'failed'           => 'bg-red-100 text-red-700',
                                'running'          => 'bg-blue-100 text-blue-700',
                                'dry_run_complete' => 'bg-gray-100 text-gray-600',
                                default            => 'bg-gray-100 text-gray-600',
                            } }}">
                            {{ match($log->status) {
                                'success'          => 'موفق',
                                'failed'           => 'ناموفق',
                                'running'          => 'در حال اجرا',
                                'dry_run_complete' => 'شبیه‌سازی',
                                default            => $log->status,
                            } }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $log->executor?->name ?? 'سیستم' }}</td>
                    <td class="px-4 py-3 text-gray-400">{{ $log->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.disaster-recovery.logs.show', $log) }}" class="text-blue-500 hover:text-blue-700 text-xs">جزئیات</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
