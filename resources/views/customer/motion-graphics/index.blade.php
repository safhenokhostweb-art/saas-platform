@extends('layouts.admin')

@section('header', 'موشن گرافیک')

@section('content')
<div class="max-w-5xl space-y-6">

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($sites->isEmpty())
        <div class="bg-white rounded-2xl shadow p-6 text-center text-gray-500">
            <p>شما به هیچ سایتی تخصیص داده نشده‌اید. برای دسترسی به موشن گرافیک با پشتیبانی تماس بگیرید.</p>
        </div>
    @else
        {{-- انتخاب سایت --}}
        @if($sites->count() > 1)
            <form method="GET" class="flex items-center gap-2">
                <label class="text-sm text-gray-600">سایت:</label>
                <select name="site" class="form-select w-auto" onchange="this.form.submit()">
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" {{ $site->id === $s->id ? 'selected' : '' }}>{{ $s->title ?: $s->domain }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        {{-- Overview / License Status --}}
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-bold text-lg mb-3">وضعیت لایسنس</h2>
            <div class="flex flex-wrap gap-4 items-center">
                @if($hasModuleLicense)
                    <span class="badge bg-green-100 text-green-800 px-3 py-1 rounded-full">موتور موشن گرافیک فعال است</span>
                @else
                    <span class="badge bg-gray-100 text-gray-600 px-3 py-1 rounded-full">موتور موشن گرافیک برای این سایت خریداری نشده است</span>
                    <a href="{{ route('store.modules', ['site_id' => $site->id]) }}" class="btn btn-primary btn-sm">خرید موتور موشن گرافیک</a>
                @endif
            </div>
            @if($runtimeUnavailableReason)
                <p class="text-sm text-gray-400 mt-3">وضعیت اجرای زنده: در دسترس نیست ({{ $runtimeUnavailableReason }})</p>
            @endif
        </div>

        {{-- My Assets / Purchased & Assigned Motions (published runtime) --}}
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-bold text-lg">موشن‌های فعال روی سایت</h2>
                <p class="text-gray-500 text-sm mt-1">نسخه‌ای که هم‌اکنون منتشرشده و به سایت شما اختصاص یافته است — نسخه‌های داخلی یا پیش‌نویس هرگز اینجا نمایش داده نمی‌شوند.</p>
            </div>
            @if(empty($runtimeAssets))
                <div class="p-6 text-center text-gray-500">در حال حاضر موشنی برای این سایت فعال نیست.</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($runtimeAssets as $asset)
                        <li class="p-4 flex items-center justify-between">
                            <span class="text-sm text-gray-700">شناسه دارایی: {{ $asset['asset_id'] }}</span>
                            <span class="badge bg-blue-100 text-blue-800">نسخه منتشرشده {{ \App\Helpers\NumberHelper::toPersianNumbers((string) $asset['published_version']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Custom Requests / Purchased Motions status --}}
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-bold text-lg">موشن‌های سفارشی من</h2>
                <p class="text-gray-500 text-sm mt-1">درخواست‌های موشن اختصاصی این سایت و وضعیت طراحی/خرید هرکدام.</p>
            </div>
            @if(empty($commissionRows))
                <div class="p-6 text-center text-gray-500">هنوز درخواست موشن سفارشی ثبت نکرده‌اید.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3 text-right">عنوان</th>
                            <th class="p-3 text-right">وضعیت طراحی</th>
                            <th class="p-3 text-right">خریداری‌شده</th>
                            <th class="p-3 text-right">نسخه فعال</th>
                            <th class="p-3 text-right">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($commissionRows as $row)
                            <tr class="border-t">
                                <td class="p-3">{{ $row['title'] }}</td>
                                <td class="p-3">
                                    @php
                                        $statusLabels = [
                                            'draft' => 'ثبت‌شده', 'designing' => 'در حال طراحی', 'review' => 'در حال بازبینی',
                                            'approved' => 'تأییدشده', 'published' => 'منتشرشده — آماده خرید', 'archived' => 'بایگانی‌شده',
                                        ];
                                    @endphp
                                    <span class="badge bg-gray-100 text-gray-700">{{ $statusLabels[$row['status']] ?? $row['status'] }}</span>
                                </td>
                                <td class="p-3">{{ $row['purchased'] ? 'بله' : 'خیر' }}</td>
                                <td class="p-3">{{ $row['assigned_version'] ? \App\Helpers\NumberHelper::toPersianNumbers((string) $row['assigned_version']) : '—' }}</td>
                                <td class="p-3">
                                    @if($row['status'] === 'published' && !$row['purchased'])
                                        <form method="POST" action="{{ route('customer.motion-graphics.purchase', $row['commission_id']) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">خرید</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Request a new custom motion --}}
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-bold text-lg mb-3">درخواست موشن سفارشی جدید</h2>
            <p class="text-gray-500 text-sm mb-4">مثال: انیمیشن لوگو، معرفی برند، بسته آموزشی. تیم طراحی پس از بررسی، قیمت و زمان‌بندی را با شما هماهنگ می‌کند.</p>
            <form method="POST" action="{{ route('customer.motion-graphics.request') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="site_id" value="{{ $site->id }}">
                <div>
                    <label class="form-label">عنوان</label>
                    <input type="text" name="title" class="form-control" required maxlength="191" value="{{ old('title') }}">
                </div>
                <div>
                    <label class="form-label">توضیحات</label>
                    <textarea name="description" class="form-control" rows="3" maxlength="2000">{{ old('description') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">ثبت درخواست</button>
            </form>
        </div>
    @endif
</div>
@endsection
