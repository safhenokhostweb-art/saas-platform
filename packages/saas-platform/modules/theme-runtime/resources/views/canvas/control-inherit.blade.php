{{-- ADR-082 — under a responsive control on tablet/mobile: where the shown
     value comes from, or a reset back to it once the device has its own. --}}
<template x-if="ctlIsResponsive(propKey) && activeBreakpoint !== 'desktop'">
    <p class="tbc-hint" :data-testid="'tbc-inherit-' + propKey">
        <template x-if="ctlInherited(propKey)">
            <span x-text="'مقدار ' + CanvasControls.DEVICE_LABELS[activeBreakpoint] + ' از دستگاه بزرگ‌تر یا پیش‌فرض می‌آید؛ تغییر آن فقط روی ' + CanvasControls.DEVICE_LABELS[activeBreakpoint] + ' اعمال می‌شود.'"></span>
        </template>
        <template x-if="!ctlInherited(propKey)">
            <span>
                <span x-text="'مقدار اختصاصی ' + CanvasControls.DEVICE_LABELS[activeBreakpoint]"></span>
                <button type="button" class="tbc-reset" @click="ctlReset(propKey)" :data-testid="'tbc-reset-' + propKey">بازگشت به ارث</button>
            </span>
        </template>
    </p>
</template>
