{{-- ADR-082 — the device switch beside a responsive field's label
     (Elementor's responsive icons). It switches the editor's device, so the
     preview and every responsive control follow; a dot marks a device with
     a value of its own. Needs theme-runtime::canvas.controls and the
     CanvasControls.editorMixin() methods; `propKey` comes from the host's
     field loop.

     Every scope variable in the control-* partials is prefixed `tbc`: Alpine
     writes `this.x = …` to the innermost scope that has `x`, so a loop
     variable named like an editor prop (e.g. `device`) would swallow the
     editor's own write. --}}
<template x-if="ctlIsResponsive(propKey)">
    <span class="tbc-devices" role="group" aria-label="مقدار برای هر دستگاه" :data-testid="'tbc-devices-' + propKey">
        <template x-for="tbcDevice in CanvasControls.DEVICES" :key="tbcDevice">
            <button type="button" class="tbc-dev" @click.prevent="ctlSetDevice(tbcDevice)"
                :aria-pressed="activeBreakpoint === tbcDevice ? 'true' : 'false'"
                :title="CanvasControls.DEVICE_LABELS[tbcDevice]" :aria-label="CanvasControls.DEVICE_LABELS[tbcDevice]"
                :data-testid="'tbc-device-' + propKey + '-' + tbcDevice">
                <svg x-show="tbcDevice === 'desktop'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
                <svg x-show="tbcDevice === 'tablet'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M11 18h2"/></svg>
                <svg x-show="tbcDevice === 'mobile'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
                <span class="tbc-dev-dot" x-show="ctlOverrides(propKey).includes(tbcDevice)" aria-hidden="true"></span>
            </button>
        </template>
    </span>
</template>
