@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'ویژگی‌های کشف‌شده')

@section('content')
<div class="space-y-5" dir="rtl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">ویژگی‌های کشف‌شده پلتفرم</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ number_format($adoptionStats['total']) }} ویژگی — نرخ پذیرش: {{ $adoptionStats['adoption_pct'] }}%</p>
        </div>
        <div class="flex gap-2">
            <form action="{{ route('admin.education.features.discover') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                    اجرای کشف ویژگی‌ها
                </button>
            </form>
            <a href="{{ route('admin.education.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">بازگشت</a>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if(session('info'))
    <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700">{{ session('info') }}</div>
    @endif

    {{-- Adoption Summary Bar --}}
    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="text-gray-600">پذیرش: {{ $adoptionStats['adopted'] }} از {{ $adoptionStats['total'] }} ویژگی</span>
            <span class="{{ $adoptionStats['adoption_pct'] >= 60 ? 'text-emerald-600' : 'text-amber-600' }} font-semibold">{{ $adoptionStats['adoption_pct'] }}%</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full {{ $adoptionStats['adoption_pct'] >= 60 ? 'bg-emerald-500' : 'bg-amber-500' }}"
                 style="width: {{ min($adoptionStats['adoption_pct'], 100) }}%"></div>
        </div>
    </div>

    {{-- Features Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-sm text-right">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3">ماژول</th>
                    <th class="px-4 py-3">ویژگی</th>
                    <th class="px-4 py-3">نام</th>
                    <th class="px-4 py-3">روت</th>
                    <th class="px-4 py-3">موقعیت منو</th>
                    <th class="px-4 py-3">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($features as $feature)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $feature->module_slug }}</span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $feature->feature_slug }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $feature->feature_name }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $feature->route_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $feature->menu_location ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <form action="{{ route('admin.education.features.generate-doc', $feature) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-cyan-600 hover:underline ml-3">تولید مستندات</button>
                        </form>
                        <form action="{{ route('admin.education.features.regenerate-doc', $feature) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-amber-600 hover:underline">بازتولید</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                        هنوز ویژگی‌ای کشف نشده. روی «اجرای کشف ویژگی‌ها» کلیک کنید.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $features->links() }}
        </div>
    </div>

</div>
@endsection
