@extends('layouts.admin')

@section('header', 'پلن‌های پشتیبانی')

@section('content')
<a href="{{ route('admin.support-plans.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded">افزودن پلن</a>
@if(session('success'))<div class="alert alert-success mt-2">{{ session('success') }}</div>@endif

<div class="mt-4 bg-white rounded shadow overflow-hidden">
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
            @foreach($plans as $plan)
            <tr class="border-t">
                <td class="p-3">{{ $plan->title }}</td>

                <td class="p-3">{{ \App\Helpers\NumberHelper::formatFa($plan->price) }}</td>
                <td class="p-3">{{ $plan->duration_days }}</td>
                <td class="p-3">{{ $plan->has_ticket ? 'بله' : 'خیر' }}</td>
                <td class="p-3">{{ $plan->has_sms ? 'بله' : 'خیر' }}</td>
                <td class="p-3">
                    <a href="{{ route('admin.support-plans.edit', $plan) }}" class="text-indigo-600 hover:underline">ویرایش</a>
                    <form method="POST" action="{{ route('admin.support-plans.destroy', $plan) }}" class="inline">@csrf @method('DELETE')
                        <button type="submit" class="text-red-600" onclick="return confirm('حذف شود؟');">حذف</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
