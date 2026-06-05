@extends('layouts.admin')

@section('header', 'مدیریت ماژول‌ها')

@section('content')
<x-module-features module='modules' />
<p class='mb-4'>
    <span class='text-sm text-gray-500'>تعداد سایت‌ها: {{ $sitesCount ?? 0 }}</span>
</p>

<form action="{{ route('admin.modules.bulk-update') }}" method="POST" id="bulk-form">
    @csrf
    <div class='bg-white rounded shadow overflow-hidden'>
        <div class='overflow-x-auto'>
            <table class='w-full text-sm'>
                <thead class='bg-gray-50'>
                    <tr>
                        <th class='p-3 text-right w-8'><input type='checkbox' id='select-all'></th>
                        <th class='p-3 text-right'>عنوان</th>
                        <th class='p-3 text-right'>نوع</th>
                        <th class='p-3 text-right'>وضعیت</th>
                        <th class='p-3 text-right'>قیمت (ریال)</th>
                        <th class='p-3 text-right'>سایت‌های فعال</th>
                        <th class='p-3 text-right'>نسخه</th>
                        <th class='p-3 text-right'>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($modules as $module)
                    <tr class='border-t hover:bg-gray-50'>
                        <td class='p-3'><input type='checkbox' name='module_ids[]' value="{{ $module->id }}" class='module-checkbox'></td>
                        <td class='p-3 font-medium'>{{ $module->title ?? $module->slug }}</td>
                        <td class='p-3'>
                            @php
                                $typeLabels = ['core' => 'هسته', 'paid' => 'پولی', 'internal' => 'داخلی'];
                                $typeColors = ['core' => 'bg-blue-100 text-blue-700', 'paid' => 'bg-green-100 text-green-700', 'internal' => 'bg-gray-100 text-gray-700'];
                            @endphp
                            <span class='px-2 py-0.5 text-xs rounded-full {{ $typeColors[$module->type] ?? 'bg-gray-100' }}'>
                                {{ $typeLabels[$module->type] ?? $module->type }}
                            </span>
                        </td>
                        <td class='p-3'>
                            @if($module->is_active)
                                <span class='text-green-600 text-xs'>فعال</span>
                            @else
                                <span class='text-red-600 text-xs'>غیرفعال</span>
                            @endif
                        </td>
                        <td class='p-3' dir='ltr'>
                            @if($module->price_irr)
                                {{ number_format($module->price_irr) }}
                            @else
                                <span class='text-gray-400'>—</span>
                            @endif
                        </td>
                        <td class='p-3'>
                            @php $cnt = $moduleSiteCounts[$module->id] ?? 0; @endphp
                            @if($cnt > 0)
                                <span class='text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded'>{{ $cnt }}</span>
                            @else
                                <span class='text-gray-400 text-xs'>0</span>
                            @endif
                        </td>
                        <td class='p-3 text-xs text-gray-500'>{{ $module->version ?? '1.0.0' }}</td>
                        <td class='p-3'>
                            <a href="{{ route('admin.modules.edit', $module) }}" class='text-blue-600 hover:underline text-xs'>ویرایش</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan='8' class='p-4 text-center text-gray-500'>هیچ ماژولی یافت نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(count($modules) > 0)
    <div class='mt-4 flex items-center gap-3'>
        <select name='bulk_action' class='form-control w-auto' required>
            <option value=''>— انتخاب عملیات گروهی —</option>
            <optgroup label='تغییر نوع'>
                <option value='set_type_internal'>تبدیل به داخلی</option>
                <option value='set_type_license'>تبدیل به مبتنی بر لایسنس</option>
                <option value='set_type_paid'>تبدیل به پولی</option>
            </optgroup>
            <optgroup label='مارکت‌پلیس'>
                <option value='marketplace_on'>فعال کردن نمایش در فروشگاه</option>
                <option value='marketplace_off'>غیرفعال کردن نمایش در فروشگاه</option>
            </optgroup>
            <optgroup label='اختصاص رایگان'>
                <option value='free_assignable_on'>فعال کردن اختصاص رایگان</option>
                <option value='free_assignable_off'>غیرفعال کردن اختصاص رایگان</option>
            </optgroup>
            <optgroup label='لایسنس'>
                <option value='license_required_on'>الزام لایسنس دستی</option>
                <option value='license_required_off'>لغو الزام لایسنس دستی</option>
            </optgroup>
        </select>
        <button type='submit' class='btn btn-primary' onclick="return confirm('عملیات گروهی اعمال شود؟')">اعمال</button>
    </div>
    @endif
</form>

<script>
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
@endsection