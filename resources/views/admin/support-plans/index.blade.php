@extends('layouts.admin')

@section('header', 'پلن‌های پشتیبانی')

@section('content')
<div class="mb-4 flex justify-between items-center">
    <a href="{{ route('admin.support-plans.create') }}" class="btn btn-primary">افزودن پلن جدید</a>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">عنوان</th>
                <th class="p-3 text-right">قیمت (ریال)</th>
                <th class="p-3 text-right">مدت (روز)</th>
                <th class="p-3 text-right">تیکت</th>
                <th class="p-3 text-right">پیامک</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plans as $plan)
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3 font-medium">{{ $plan->title }}</td>
                    <td class="p-3">@numFa($plan->price)</td>
                    <td class="p-3">{{ $plan->duration_days }}</td>
                    <td class="p-3">
                        @if($plan->has_ticket)
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-800">دارد</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">ندارد</span>
                        @endif
                    </td>
                    <td class="p-3">
                        @if($plan->has_sms)
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-800">دارد</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">ندارد</span>
                        @endif
                    </td>
                    <td class="p-3 flex gap-2">
                        <a href="{{ route('admin.support-plans.edit', $plan) }}" class="text-indigo-600 hover:underline text-xs">ویرایش</a>
                        <form method="POST" action="{{ route('admin.support-plans.destroy', $plan) }}" onsubmit="return confirm('حذف شود؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-xs">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-6 text-center text-gray-500">پلن پشتیبانی تعریف نشده است.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
