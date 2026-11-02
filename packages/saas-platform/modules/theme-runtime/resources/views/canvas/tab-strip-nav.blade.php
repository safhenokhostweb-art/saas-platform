{{-- ADR-076 — one chevron of the Canvas page-tab strip ($dir: 'start' | 'end').
     See theme-runtime::canvas.tab-strip. --}}
<button type="button" class="tbts-nav tbts-nav--{{ $dir }}" data-tbts-dir="{{ $dir }}" tabindex="-1" hidden
        aria-label="{{ $dir === 'start' ? 'نمایش صفحه‌های قبلی' : 'نمایش صفحه‌های بعدی' }}"
        title="{{ $dir === 'start' ? 'صفحه‌های قبلی' : 'صفحه‌های بعدی' }}"
        data-testid="tb-page-tabs-{{ $dir }}"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6"/></svg></span></button>
