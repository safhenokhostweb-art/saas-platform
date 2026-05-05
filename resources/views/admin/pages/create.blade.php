@extends('layouts.admin')

@section('header', 'صفحه جدید')

@section('content')
    @include('admin.pages.form', [
        'action' => route('admin.pages.store'),
        'page' => new \App\Models\Page([
            'menu_position' => 'top',
            'menu_order' => 0,
            'is_active' => true,
            'icon_default' => 'document',
        ]),
    ])
@endsection
