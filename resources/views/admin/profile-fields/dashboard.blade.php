@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-4xl py-5 px-4">
    <div class="card border-0 shadow-sm rounded-xl p-6">
        <h4 class="font-bold mb-3 text-xl text-right">داشبورد ادمین SaaS</h4>
        <p class="text-gray-500 text-right mb-4">
            از اینجا می‌تونی سایت مشتری‌ها، ماژول‌ها و پلن‌های پشتیبانی رو مدیریت کنی.
        </p>
        <div class="flex gap-2 justify-end flex-wrap">
            <a href="{{ route('admin.sites.index') }}" class="btn btn-primary rounded-xl px-4 py-2">
                مدیریت سایت‌ها
            </a>
            <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-primary rounded-xl px-4 py-2">
                مدیریت مشتری‌ها
            </a>
        </div>
    </div>
</div>
@endsection
