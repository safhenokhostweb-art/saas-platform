@extends('layouts.admin')
@section('header', $agent ? 'ویرایش Agent' : 'Agent جدید')

@section('content')
<div style="font-family:'Vazirmatn',Tahoma,sans-serif;direction:rtl;padding:1.5rem;max-width:640px">

  <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:2rem">
    <a href="{{ route('admin.infrastructure.remote-agents.index') }}" class="btn btn-secondary btn-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      بازگشت
    </a>
    <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0">{{ $agent ? 'ویرایش: '.$agent->name : 'ثبت Agent جدید' }}</h1>
  </div>

  <form method="POST" action="{{ $agent ? route('admin.infrastructure.remote-agents.update', $agent) : route('admin.infrastructure.remote-agents.store') }}" class="card card-body">
    @csrf
    @if($agent) @method('PUT') @endif

    <div style="margin-bottom:1rem">
      <label class="form-label">نام Agent *</label>
      <input type="text" name="name" value="{{ old('name', $agent?->name) }}" required class="form-control" placeholder="مثلاً: سرور مشتری الف">
      @error('name')<p style="color:#dc2626;font-size:.8rem;margin-top:.25rem">{{ $message }}</p>@enderror
    </div>

    <div style="margin-bottom:1rem">
      <label class="form-label">آدرس Agent (HTTPS) *</label>
      <input type="url" name="base_url" value="{{ old('base_url', $agent?->base_url) }}" required class="form-control" placeholder="https://client1.example.com" dir="ltr">
      @error('base_url')<p style="color:#dc2626;font-size:.8rem;margin-top:.25rem">{{ $message }}</p>@enderror
    </div>

    <div style="margin-bottom:1rem" x-data="{}">
      <label class="form-label">Secret Key {{ $agent ? '(خالی بگذارید تا تغییر نکند)' : '*' }}</label>
      <div style="display:flex;gap:.5rem">
        <input type="text" name="secret_key" id="secret_key"
               value="{{ old('secret_key') }}"
               {{ !$agent ? 'required' : '' }}
               class="form-control"
               placeholder="{{ $agent ? '••••••••••••••••' : 'حداقل ۱۶ کاراکتر' }}"
               dir="ltr">
        <button type="button" class="btn btn-secondary"
                onclick="fetch('{{ route('admin.infrastructure.remote-agents.generate-secret') }}').then(r=>r.json()).then(d=>document.getElementById('secret_key').value=d.secret)">
          تولید
        </button>
      </div>
      @error('secret_key')<p style="color:#dc2626;font-size:.8rem;margin-top:.25rem">{{ $message }}</p>@enderror
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div>
        <label class="form-label">محیط</label>
        <select name="environment" class="form-select">
          <option value="production" {{ old('environment', $agent?->environment) === 'production' ? 'selected' : '' }}>Production</option>
          <option value="staging"    {{ old('environment', $agent?->environment) === 'staging'    ? 'selected' : '' }}>Staging</option>
        </select>
      </div>
      @if($agent)
      <div>
        <label class="form-label">وضعیت</label>
        <select name="is_active" class="form-select">
          <option value="1" {{ $agent->is_active ? 'selected' : '' }}>فعال</option>
          <option value="0" {{ !$agent->is_active ? 'selected' : '' }}>غیرفعال</option>
        </select>
      </div>
      @endif
    </div>

    <div style="margin-bottom:1.5rem">
      <label class="form-label">یادداشت (اختیاری)</label>
      <textarea name="notes" class="form-control" rows="3">{{ old('notes', $agent?->notes) }}</textarea>
    </div>

    <div style="display:flex;gap:.75rem">
      <button type="submit" class="btn btn-primary">{{ $agent ? 'ذخیره تغییرات' : 'ثبت Agent' }}</button>
      <a href="{{ route('admin.infrastructure.remote-agents.index') }}" class="btn btn-secondary">انصراف</a>
    </div>
  </form>

</div>
@endsection
