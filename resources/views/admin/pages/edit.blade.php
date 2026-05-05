@extends('layouts.admin')

@section('header', 'ویرایش صفحه: ' . $page->title)

@section('content')
    @include('admin.pages.form', [
        'action' => route('admin.pages.update', $page),
        'page' => $page,
    ])
@endsection
