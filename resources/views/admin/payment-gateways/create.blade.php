@extends('layouts.admin')

@section('header', 'افزودن درگاه پرداخت')

@section('content')
<form method="POST" action="{{ route('admin.payment-gateways.store') }}" class="card p-4 max-w-2xl">
    @csrf
    @include('admin.payment-gateways._form')
    <button type="submit" class="btn btn-primary">ذخیره</button>
</form>
@endsection
