@extends('layouts.admin')

@section('header', 'عنوان صفحه')


@section('title', 'ویرایش فیلد پروفایل')

@section('content')
<div class="max-w-2xl mx-auto py-4">

    <div class="flex justify-between items-center mb-3">
        <h5 class="font-bold text-lg">ویرایش فیلد پروفایل</h5>
        <a href="{{ route('admin.profile-fields.index') }}" class="btn btn-outline-secondary rounded-xl px-4 py-2">
            بازگشت
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-xl">
        <div class="card-body p-4">
            @if ($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                <ul class="list-disc pr-5">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            <form action="{{ route('admin.profile-fields.update', $profileField) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">عنوان نمایشی (Label)</label>
                    <input type="text"
                           name="label"
                           value="{{ old('label', $profileField->label) }}"
                           class="form-control"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">کلید (Key)</label>
                    <input type="text"
                           name="key"
                           value="{{ old('key', $profileField->key) }}"
                           class="form-control"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">نوع فیلد</label>
                    <select name="type" class="form-select" required>
                        @foreach(['text', 'number', 'textarea', 'map'] as $type)
                            <option value="{{ $type }}" @selected(old('type', $profileField->type) === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input"
                           type="checkbox"
                           name="required"
                           value="1"
                           @checked(old('required', $profileField->required))>
                    <label class="form-check-label">اجباری باشد</label>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input"
                           type="checkbox"
                           name="enabled"
                           value="1"
                           @checked(old('enabled', $profileField->enabled))>
                    <label class="form-check-label">فعال باشد</label>
                </div>

                <button type="submit" class="btn btn-primary rounded-xl w-full">
                    ذخیره تغییرات
                </button>
            </form>

        </div>
    </div>

</div>
@endsection
