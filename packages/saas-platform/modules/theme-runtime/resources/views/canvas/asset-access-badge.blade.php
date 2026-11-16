{{--
    ADR-087: the palette badge of a restricted widget, shared by both canvas
    editors. $access is AssetAccessDecision::toArray() or null (ordinary
    public widget: renders nothing). Inline styles on purpose: neither host
    has to rebuild its Tailwind bundle for these colours.
--}}
@if(!empty($access) && !empty($access['badge']))
    @php
        $isPurchase = ($access['reason'] ?? null) === \SaaSPlatform\ThemeRuntime\Access\AssetAccessDecision::REASON_ENTITLEMENT_REQUIRED;
        $palette = $isPurchase ? 'background:#fef3c7;color:#92400e;border-color:#fcd34d' : 'background:#ede9fe;color:#5b21b6;border-color:#c4b5fd';
    @endphp
    <span class="inline-flex items-center gap-1 text-[9px] font-semibold leading-none px-1.5 py-0.5 rounded-full border mt-0.5"
          style="{{ $palette }}"
          data-testid="tb-asset-badge"
          data-asset-state="{{ $access['state'] }}"
          title="{{ $access['message'] ?? $access['badge'] }}">
        <svg aria-hidden="true" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        {{ $access['badge'] }}
    </span>
@endif
