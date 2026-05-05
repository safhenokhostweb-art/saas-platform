@extends('layouts.admin')

@section('header', 'عنوان صفحه')


@section('content')
<div class="max-w-2xl mx-auto py-4">

    <h5 class="font-bold mb-3 text-lg">ویرایش مشتری</h5>

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                <ul class="list-disc pr-5">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
                @csrf
                @method('PUT')

                @include('admin.customers.form')

                <button class="btn btn-primary w-full mt-3">ذخیره تغییرات</button>
            </form>

        </div>
    </div>

</div>
@endsection
