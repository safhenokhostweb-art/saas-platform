{{--
    ADR-087: the access layer of a template-modal card, shared by both
    canvas editors. Rendered inside the card's thumbnail box (`relative`),
    in the scope of an Alpine `tpl` (a card from the templates endpoint,
    whose library cards carry `access`). A locked card keeps its thumbnail
    (the upgrade path is the point) under a veil, with the badge and the
    reason; the host's click handler refuses it with the same message.
--}}
<template x-if="tpl.access && tpl.access.badge">
    <span class="absolute bottom-1.5 left-1.5 inline-flex items-center gap-1 text-[9px] font-bold leading-none px-1.5 py-1 rounded-full border shadow-sm"
          :style="tpl.access.reason === 'entitlement_required' ? 'background:#fef3c7;color:#92400e;border-color:#fcd34d' : 'background:#ede9fe;color:#5b21b6;border-color:#c4b5fd'"
          :data-asset-state="tpl.access.state"
          data-testid="tpl-asset-badge">
        <svg aria-hidden="true" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <span x-text="tpl.access.badge"></span>
    </span>
</template>
<template x-if="tpl.access && tpl.access.locked">
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none" style="background:rgba(15,23,42,.38);backdrop-filter:grayscale(.7)">
        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-3 py-1.5 rounded-lg shadow" style="background:#fff;color:#0f172a">
            <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            قفل است
        </span>
    </div>
</template>
