@extends('installer.layout')
@section('title', 'انتخاب منبع پشتیبان')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">بازیابی — انتخاب منبع</div>
    <div class="card-subtitle">منبع فایل پشتیبان را انتخاب کنید.</div>

    <form action="{{ route('installer.process', ['mode'=>'restore','step'=>'source_select']) }}" method="POST" id="sourceForm" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="source"    id="sourceInput"  value="">
        <input type="hidden" name="file_path" id="filePathInput" value="">

        {{-- Arvan Backups --}}
        @if(count($sources['arvan']) > 0)
        <div class="card-section">
            <div class="section-title">☁️ Arvan Object Storage</div>
            @foreach($sources['arvan'] as $file)
                <div class="check-row backup-row" style="cursor:pointer;"
                     data-source="arvan"
                     data-path="{{ $file['path'] }}">
                    <input type="radio" name="_arvan_pick" value="{{ $file['path'] }}" tabindex="-1">
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
                <div class="check-row backup-row" style="cursor:pointer;"
                     data-source="local"
                     data-path="{{ $file['path'] }}">
                    <input type="radio" name="_local_pick" value="{{ $file['path'] }}" tabindex="-1">
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
                <div class="check-row backup-row" style="cursor:pointer;"
                     data-source="release"
                     data-path="{{ $file['path'] }}">
                    <input type="radio" name="_release_pick" value="{{ $file['path'] }}" tabindex="-1">
                    <span class="check-icon">📦</span>
                    <span class="check-label">{{ $file['filename'] }}</span>
                    <span class="check-message" style="font-size:12px;">{{ number_format($file['size'] / 1048576, 1) }} MB</span>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Local Recovery Repository --}}
        @if(count($sources['recovery_repository'] ?? []) > 0)
        <div class="card-section">
            <div class="section-title">🛡️ خزانه بازیابی محلی</div>
            @foreach($sources['recovery_repository'] as $file)
                <div class="check-row backup-row" style="cursor:pointer;"
                     data-source="recovery_repository"
                     data-path="{{ $file['path'] }}">
                    <input type="radio" name="_recovery_pick" value="{{ $file['path'] }}" tabindex="-1">
                    <span class="check-icon">🛡️</span>
                    <span class="check-label">{{ $file['filename'] }}</span>
                    <span class="check-message" style="font-size:12px;">{{ $file['size_human'] ?? '' }}</span>
                    <span style="font-size:11px; color:var(--c-muted);">{{ $file['date_human'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Local PC (LEVEL 3 — fully portable, independent of this server) --}}
        <div class="card-section">
            <div class="section-title">💻 Local PC — کامپیوتر شما</div>
            <div class="check-row" style="cursor:default;">
                <span class="check-icon">💻</span>
                <div style="flex:1;">
                    <div style="margin-bottom:8px; font-size:13px; color:var(--c-muted);">
                        فایل ZIP پشتیبان (دانلود شده از مرکز DR یک پلتفرم دیگر یا همین پلتفرم) را از کامپیوتر خود انتخاب کنید.
                        این بسته کاملاً مستقل از سرور است و روی هر سروری قابل بازیابی است.
                    </div>
                    <input type="file" name="pc_zip" id="pcZipInput" accept=".zip">
                </div>
            </div>
        </div>

        @if(empty($sources['arvan']) && empty($sources['local']) && empty($sources['release']) && empty($sources['recovery_repository'] ?? []))
            <div class="alert alert-warning">
                ⚠ هیچ فایل پشتیبانی روی سرور یافت نشد. می‌توانید از بخش «Local PC» در بالا یک بسته از کامپیوتر خود آپلود کنید،
                یا فایل پشتیبان را در <code>storage/backups/</code> یا <code>storage/releases/</code> قرار دهید.
            </div>
        @endif

        <div class="actions-bar">
            <a href="{{ route('installer.index') }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary" id="continueBtn" disabled>ادامه — تأیید پشتیبان &larr;</button>
        </div>
    </form>
</div>

<script>
    var sourceInput   = document.getElementById('sourceInput');
    var filePathInput = document.getElementById('filePathInput');
    var continueBtn   = document.getElementById('continueBtn');
    var pcZipInput    = document.getElementById('pcZipInput');

    document.querySelectorAll('.backup-row').forEach(function(row) {
        row.addEventListener('click', function() {
            document.querySelectorAll('.backup-row').forEach(function(r) {
                r.style.background = '';
                var radio = r.querySelector('input[type="radio"]');
                if (radio) radio.checked = false;
            });
            pcZipInput.value = '';

            this.style.background = 'rgba(99,102,241,0.08)';
            var radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;

            sourceInput.value   = this.getAttribute('data-source');
            filePathInput.value = this.getAttribute('data-path');
            continueBtn.disabled = false;
        });
    });

    pcZipInput.addEventListener('change', function() {
        if (!this.files || !this.files.length) return;

        document.querySelectorAll('.backup-row').forEach(function(r) {
            r.style.background = '';
            var radio = r.querySelector('input[type="radio"]');
            if (radio) radio.checked = false;
        });

        sourceInput.value   = 'local_pc';
        filePathInput.value = '';
        continueBtn.disabled = false;
    });
</script>
@endsection
