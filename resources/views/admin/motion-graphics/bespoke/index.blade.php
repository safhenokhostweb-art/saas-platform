@extends('layouts.admin')

@section('title', 'موشن‌های اختصاصی')
@section('header', 'موشن‌های اختصاصی — '.($site->title ?? $site->domain))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    @include('admin.motion-graphics.partials.site-nav', ['site' => $site, 'active' => 'bespoke'])

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card"><div class="card-body">
        <h3 class="font-bold mb-3">کمیسیون موشن اختصاصی جدید</h3>
        <form method="POST" action="{{ route('admin.motion-graphics.bespoke.store', $site) }}" class="flex gap-2">
            @csrf
            <input type="text" name="title" required maxlength="191" class="form-input flex-1" placeholder="عنوان (مثلاً: انیمیشن هیرو صفحه اصلی)">
            <button type="submit" class="btn btn-primary">ایجاد</button>
        </form>
    </div></div>

    @foreach($commissions as $commission)
        <div class="card"><div class="card-body space-y-3">
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-bold">{{ $commission->title }}</div>
                    <div class="text-xs text-gray-500">
                        وضعیت: <span class="font-mono">{{ $commission->status }}</span>
                        @if($commission->asset_id) — دارایی: <span class="font-mono">{{ $commission->asset_id }}</span> @endif
                        @if($commission->product) — محصول: {{ $commission->product->sku }} @endif
                    </div>
                    @if($commission->assignment && $commission->assignment->status === \App\Modules\MotionGraphics\Models\MotionBespokeAssignment::STATUS_ACTIVE)
                        <div class="text-xs text-green-700">تخصیص فعال — نسخه {{ $commission->assignment->active_version }}</div>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-4 text-sm">
                <form method="POST" action="{{ route('admin.motion-graphics.bespoke.transition', [$site, $commission]) }}" class="flex gap-1">
                    @csrf
                    <select name="status" class="form-select">
                        @foreach(\App\Modules\MotionGraphics\Models\MotionBespokeCommission::ALLOWED_TRANSITIONS[$commission->status] ?? [] as $next)
                            <option value="{{ $next }}">{{ $next }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm">تغییر وضعیت</button>
                </form>

                <form method="POST" action="{{ route('admin.motion-graphics.bespoke.attach-asset', [$site, $commission]) }}" class="flex gap-1">
                    @csrf
                    <input type="text" name="asset_id" class="form-input" placeholder="asset uuid ماژول" required>
                    <button type="submit" class="btn btn-sm">اتصال دارایی</button>
                </form>

                <form method="POST" action="{{ route('admin.motion-graphics.bespoke.link-product', [$site, $commission]) }}" class="flex gap-1">
                    @csrf
                    <input type="number" name="product_id" class="form-input" placeholder="Product ID" required>
                    <button type="submit" class="btn btn-sm">اتصال محصول</button>
                </form>

                <form method="POST" action="{{ route('admin.motion-graphics.bespoke.assign', [$site, $commission]) }}" class="flex gap-1">
                    @csrf
                    <input type="number" name="version" class="form-input" min="1" placeholder="نسخه" required>
                    <button type="submit" class="btn btn-sm btn-primary">تخصیص</button>
                </form>

                @if($commission->assignment)
                    <form method="POST" action="{{ route('admin.motion-graphics.bespoke.rollback', [$site, $commission]) }}" class="flex gap-1">
                        @csrf
                        <input type="number" name="version" class="form-input" min="1" placeholder="نسخه قبلی" required>
                        <button type="submit" class="btn btn-sm btn-warning">بازگردانی</button>
                    </form>

                    <form method="POST" action="{{ route('admin.motion-graphics.bespoke.revoke', [$site, $commission]) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger">لغو تخصیص</button>
                    </form>
                @endif
            </div>
        </div></div>
    @endforeach
</div>
@endsection
