@extends('layouts.admin')

@section('header', 'ویرایش درگاه پرداخت')

@section('content')
<form method="POST" action="{{ route('admin.payment-gateways.update', $paymentGateway) }}" class="card p-4 max-w-2xl">
    @csrf
    @method('PUT')
    @include('admin.payment-gateways._form', ['paymentGateway' => $paymentGateway])
    <button type="submit" class="btn btn-primary">ذخیره</button>
</form>
@endsection
