@extends('installer.layout')
@section('title', 'انتخاب منبع پشتیبان')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">بازیابی — انتخاب منبع</div>
    <div class="card-subtitle">منبع فایل پشتیبان را انتخاب کنید.</div>

    <form action="{{ route('installer.process', ['mode'=>'restore','step'=>'source_select']) }}" method="POST" x-data="{ source: '' }">
        @csrf
        <input type="hidden" name="source" x-model="source">
        <input type="hidden" name="file_path" id="selectedPath" value="">

        {{-- Arvan Backups --}}
        @if(count($sources['arvan']) > 0)
        <div class="card-section">
            <div class="section-title">☁️ Arvan Object Storage</div>
            @foreach($sources['arvan'] as $file)
                <div class="check-row" style="cursor:pointer;"
                     @click="source='arvan'; document.getElementById('selectedPath').value='{{ $file['path'] }}'">
                    <input type="radio" name="_arvan_pick" value="{{ $file['path'] }}">
                    <span class="check-icon">🗄️</span>
                    <span class="check-label">{{ $file['filename'] }}</span>
                    <span class="check-message" style="font-size:12px;">{{ $file['size_human'] ?? '' }}</span>
                    <span style="font-size:11px; color:var(--c-muted);">{{ $file['date_human'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Local Backups --}}
        @if(count($sources['local']) > 0)
        <div class="card-section">
            <div class="section-title">💾 دیسک محلی</div>
            @foreach($sources['local'] as $file)
                <div class="check-row" style="cursor:pointer;"
                     @click="source='local'; document.getElementById('selectedPath').value='{{ $file['path'] }}'">
                    <input type="radio" name="_local_pick" value="{{ $file['path'] }}">
                    <span class="check-icon">📄</span>
                    <span class="check-label">{{ $file['filename'] }}</span>
                    <span class="check-message" style="font-size:12px;">{{ number_format($file['size'] / 1048576, 1) }} MB</span>
                    <span style="font-size:11px; color:var(--c-muted);">{{ date('Y/m/d H:i', $file['modified']) }}</span>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Release Packages --}}
        @if(count($sources['release']) > 0)
        <div class="card-section">
            <div class="section-title">📦 پکیج Release</div>
            @foreach($sources['release'] as $file)
                <div class="check-row" style="cursor:pointer;"
                     @click="source='release'; document.getElementById('selectedPath').value='{{ $file['path'] }}'">
                    <input type="radio" name="_release_pick" value="{{ $file['path'] }}">
                    <span class="check-icon">📦</span>
                    <span class="check-label">{{ $file['filename'] }}</span>
                    <span class="check-message" style="font-size:12px;">{{ number_format($file['size'] / 1048576, 1) }} MB</span>
                </div>
            @endforeach
        </div>
        @endif

        @if(empty($sources['arvan']) && empty($sources['local']) && empty($sources['release']))
            <div class="alert alert-warning">
                ⚠ هیچ فایل پشتیبانی یافت نشد. فایل پشتیبان را در <code>storage/backups/</code> یا <code>storage/releases/</code> قرار دهید.
            </div>
        @endif

        <div class="actions-bar">
            <a href="{{ route('installer.index') }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary" :disabled="!source">ادامه — تأیید پشتیبان &larr;</button>
        </div>
    </form>
</div>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
