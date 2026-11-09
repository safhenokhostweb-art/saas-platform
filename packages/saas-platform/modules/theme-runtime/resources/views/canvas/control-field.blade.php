{{-- ADR-082 — the shared Inspector controls both Canvas editors render
     inside their field loop: range (slider + number + unit), responsive
     number and select, spacing (four logical sides, linked), typography and
     box shadow. Every read and write goes through the
     CanvasControls.editorMixin() methods, so a responsive field reads and
     writes the active device's own key (key, key_tablet, key_mobile) and an
     inherited value shows greyed until the device gets its own.

     Needs theme-runtime::canvas.controls (and canvas.color for the shadow
     colour). `schemaDef` and `propKey` come from the host's field loop; the
     host renders its own number/select controls only for fields that are
     not responsive. --}}

{{-- range: slider + exact number + unit --}}
<template x-if="schemaDef.type === 'range'">
    <div class="tbc" :data-testid="'tbc-range-' + propKey">
        <div class="tbc-row">
            <input type="range" class="tbc-range" :class="ctlRange(propKey).size === '' ? 'is-empty' : ''" :id="'ctrl-' + propKey"
                :min="CanvasControls.rangeLimits(schemaDef, ctlRange(propKey).unit).min"
                :max="CanvasControls.rangeLimits(schemaDef, ctlRange(propKey).unit).max"
                :step="CanvasControls.rangeLimits(schemaDef, ctlRange(propKey).unit).step"
                :value="ctlRange(propKey).size === '' ? CanvasControls.rangeLimits(schemaDef, ctlRange(propKey).unit).min : ctlRange(propKey).size"
                @input="ctlRangeSet(propKey, $event.target.value, ctlRange(propKey).unit)"
                :aria-label="schemaDef.label || propKey">
            <input type="number" class="tbc-input tbc-input--num" :class="ctlInherited(propKey) ? 'is-inherited' : ''"
                :min="CanvasControls.rangeLimits(schemaDef, ctlRange(propKey).unit).min"
                :max="CanvasControls.rangeLimits(schemaDef, ctlRange(propKey).unit).max"
                :step="CanvasControls.rangeLimits(schemaDef, ctlRange(propKey).unit).step"
                :value="ctlRange(propKey).size"
                @change="ctlRangeSet(propKey, $event.target.value, ctlRange(propKey).unit)"
                :aria-label="(schemaDef.label || propKey) + ' (عدد)'" :data-testid="'tbc-range-value-' + propKey">
            <template x-if="CanvasControls.rangeUnits(schemaDef).length > 1">
                <select class="tbc-select tbc-unit" :value="ctlRange(propKey).unit"
                    @change="ctlRangeSet(propKey, ctlRange(propKey).size, $event.target.value)" aria-label="واحد" :data-testid="'tbc-range-unit-' + propKey">
                    <template x-for="tbcUnit in CanvasControls.rangeUnits(schemaDef)" :key="tbcUnit">
                        <option :value="tbcUnit" :selected="tbcUnit === ctlRange(propKey).unit" x-text="tbcUnit"></option>
                    </template>
                </select>
            </template>
            <template x-if="CanvasControls.rangeUnits(schemaDef).length === 1">
                <span class="tbc-hint" style="margin:0" x-text="CanvasControls.rangeUnits(schemaDef)[0]"></span>
            </template>
        </div>
        @include('theme-runtime::canvas.control-inherit')
    </div>
</template>

{{-- responsive number --}}
<template x-if="schemaDef.type === 'number' && ctlIsResponsive(propKey)">
    <div class="tbc">
        <input type="number" class="tbc-input" :class="ctlInherited(propKey) ? 'is-inherited' : ''" :id="'ctrl-' + propKey"
            :value="ctlRead(propKey) ?? ''" @change="ctlWrite(propKey, $event.target.value === '' ? '' : Number($event.target.value))"
            :min="schemaDef.min ?? null" :max="schemaDef.max ?? null" :step="schemaDef.step ?? 'any'" :data-testid="'tb-prop-' + propKey">
        @include('theme-runtime::canvas.control-inherit')
    </div>
</template>

{{-- responsive select --}}
<template x-if="schemaDef.type === 'select' && ctlIsResponsive(propKey)">
    <div class="tbc">
        <select class="tbc-select" :class="ctlInherited(propKey) ? 'is-inherited' : ''" :id="'ctrl-' + propKey"
            @change="ctlWrite(propKey, $event.target.value)" :data-testid="'tb-prop-' + propKey">
            <template x-for="tbcOpt in CanvasControls.selectOptions(schemaDef)" :key="tbcOpt.value">
                <option :value="tbcOpt.value" :selected="String(ctlRead(propKey) ?? '') === tbcOpt.value" x-text="tbcOpt.label"></option>
            </template>
        </select>
        @include('theme-runtime::canvas.control-inherit')
    </div>
</template>

{{-- spacing: logical sides, linked by default --}}
<template x-if="schemaDef.type === 'spacing'">
    <div class="tbc" :data-testid="'tbc-spacing-' + propKey">
        <div class="tbc-sides">
            <template x-for="tbcSide in CanvasControls.SPACING_SIDES" :key="tbcSide">
                <label class="tbc-side">
                    <input type="number" class="tbc-input" :class="ctlInherited(propKey) ? 'is-inherited' : ''"
                        :value="ctlSpacing(propKey)[tbcSide]" @change="ctlSpacingSet(propKey, tbcSide, $event.target.value)"
                        :aria-label="(schemaDef.label || propKey) + ' — ' + ({'block-start': 'بالا', 'inline-start': 'ابتدای سطر (راست)', 'block-end': 'پایین', 'inline-end': 'انتهای سطر (چپ)'})[tbcSide]"
                        :data-testid="'tbc-spacing-' + propKey + '-' + tbcSide">
                    <span x-text="({'block-start': 'بالا', 'inline-start': 'راست', 'block-end': 'پایین', 'inline-end': 'چپ'})[tbcSide]"></span>
                </label>
            </template>
            <button type="button" class="tbc-btn tbc-link" @click="ctlSpacingLink(propKey, !ctlSpacing(propKey).linked)"
                :aria-pressed="ctlSpacing(propKey).linked ? 'true' : 'false'"
                :title="ctlSpacing(propKey).linked ? 'همهٔ جهت‌ها یکسان (برای جداکردن کلیک کنید)' : 'هر جهت جدا (برای یکسان‌کردن کلیک کنید)'"
                aria-label="یکسان‌بودن همهٔ جهت‌ها" :data-testid="'tbc-spacing-link-' + propKey">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
            </button>
        </div>
        <div class="tbc-row" style="margin-top:.375rem">
            <span class="tbc-hint" style="margin:0">واحد</span>
            <select class="tbc-select tbc-unit" :value="ctlSpacing(propKey).unit" @change="ctlSpacingUnit(propKey, $event.target.value)" aria-label="واحد فاصله">
                <template x-for="tbcUnit in (schemaDef.units || ['px', 'rem', 'em', '%'])" :key="tbcUnit">
                    <option :value="tbcUnit" :selected="tbcUnit === ctlSpacing(propKey).unit" x-text="tbcUnit"></option>
                </template>
            </select>
        </div>
        @include('theme-runtime::canvas.control-inherit')
    </div>
</template>

{{-- typography: summary button + panel --}}
<template x-if="schemaDef.type === 'typography'">
    <div class="tbc" x-data="{ tbcOpen: false }" :data-testid="'tbc-typography-' + propKey">
        <div class="tbc-row">
            <button type="button" class="tbc-btn tbc-summary" @click="tbcOpen = !tbcOpen" :aria-expanded="tbcOpen ? 'true' : 'false'" :id="'ctrl-' + propKey">
                <span x-text="CanvasControls.typographySummary(ctlTypography(propKey))"></span>
                <span aria-hidden="true" x-text="tbcOpen ? '▴' : '▾'"></span>
            </button>
        </div>
        <div class="tbc-panel" x-show="tbcOpen" x-transition.opacity>
            <label><span class="tbc-sub">فونت</span>
                <select class="tbc-select" @change="ctlTypographySet(propKey, 'fontToken', $event.target.value)" :data-testid="'tbc-typography-' + propKey + '-fontToken'">
                    <template x-for="tbcFont in CanvasControls.FONT_TOKENS" :key="tbcFont.value">
                        <option :value="tbcFont.value" :selected="(ctlTypography(propKey).fontToken ?? '') === tbcFont.value" x-text="tbcFont.label"></option>
                    </template>
                </select>
            </label>
            <div class="tbc-pair">
                <label><span class="tbc-sub">اندازه (px)</span>
                    <input type="number" min="8" max="120" class="tbc-input" :value="ctlTypography(propKey).size ?? ''" @change="ctlTypographySet(propKey, 'size', $event.target.value)" :data-testid="'tbc-typography-' + propKey + '-size'">
                </label>
                <label><span class="tbc-sub">وزن</span>
                    <select class="tbc-select" @change="ctlTypographySet(propKey, 'weight', $event.target.value)" :data-testid="'tbc-typography-' + propKey + '-weight'">
                        <template x-for="tbcWeight in CanvasControls.FONT_WEIGHTS" :key="tbcWeight.value">
                            <option :value="tbcWeight.value" :selected="String(ctlTypography(propKey).weight ?? '') === tbcWeight.value" x-text="tbcWeight.label"></option>
                        </template>
                    </select>
                </label>
            </div>
            <div class="tbc-pair">
                <label><span class="tbc-sub">ارتفاع خط</span>
                    <input type="number" min="0.8" max="3" step="0.05" class="tbc-input" :value="ctlTypography(propKey).lineHeight ?? ''" @change="ctlTypographySet(propKey, 'lineHeight', $event.target.value)" :data-testid="'tbc-typography-' + propKey + '-lineHeight'">
                </label>
                <label><span class="tbc-sub">فاصلهٔ حروف (px)</span>
                    <input type="number" min="-5" max="20" step="0.1" class="tbc-input" :value="ctlTypography(propKey).letterSpacing ?? ''" @change="ctlTypographySet(propKey, 'letterSpacing', $event.target.value)" :data-testid="'tbc-typography-' + propKey + '-letterSpacing'">
                </label>
            </div>
            <p class="tbc-hint" x-show="ctlIsResponsive(propKey) && activeBreakpoint !== 'desktop'">
                فقط مقدارهایی که این‌جا عوض می‌کنید برای <span x-text="CanvasControls.DEVICE_LABELS[activeBreakpoint]"></span> جدا ذخیره می‌شوند؛ بقیه از دستگاه بزرگ‌تر می‌آیند.
            </p>
        </div>
        @include('theme-runtime::canvas.control-inherit')
    </div>
</template>

{{-- box shadow: presets + custom values --}}
<template x-if="schemaDef.type === 'shadow'">
    <div class="tbc" :data-testid="'tbc-shadow-' + propKey">
        <div class="tbc-row">
            <span class="tbc-swatch" :style="'box-shadow:' + CanvasControls.shadowCss(ctlShadow(propKey))" aria-hidden="true"></span>
            <div class="tbc-presets tbc-grow" role="group" :aria-label="schemaDef.label || propKey">
                <template x-for="tbcPreset in ['none', 'sm', 'md', 'lg', 'xl']" :key="tbcPreset">
                    <button type="button" class="tbc-btn" @click="ctlShadowPreset(propKey, tbcPreset)"
                        :aria-pressed="CanvasControls.shadowPresetOf(ctlShadow(propKey)) === tbcPreset ? 'true' : 'false'"
                        x-text="CanvasControls.SHADOW_PRESET_LABELS[tbcPreset]" :data-testid="'tbc-shadow-' + propKey + '-' + tbcPreset"></button>
                </template>
            </div>
        </div>
        <div x-data="{ tbcOpen: false }">
            <button type="button" class="tbc-btn" style="margin-top:.375rem" @click="tbcOpen = !tbcOpen" :aria-expanded="tbcOpen ? 'true' : 'false'" :data-testid="'tbc-shadow-' + propKey + '-custom'">
                <span x-text="CanvasControls.shadowPresetOf(ctlShadow(propKey)) === 'custom' ? 'سایهٔ سفارشی' : 'تنظیم دقیق'"></span>
                <span aria-hidden="true" x-text="tbcOpen ? '▴' : '▾'"></span>
            </button>
            <div class="tbc-panel" x-show="tbcOpen" x-transition.opacity>
                <div class="tbc-pair">
                    <template x-for="tbcPart in [['x', 'افقی (px)'], ['y', 'عمودی (px)'], ['blur', 'محو (px)'], ['spread', 'گسترش (px)']]" :key="tbcPart[0]">
                        <label><span class="tbc-sub" x-text="tbcPart[1]"></span>
                            <input type="number" class="tbc-input" :min="CanvasControls.SHADOW_LIMITS[tbcPart[0]][0]" :max="CanvasControls.SHADOW_LIMITS[tbcPart[0]][1]"
                                :value="ctlShadow(propKey)?.[tbcPart[0]] ?? 0" @change="ctlShadowSet(propKey, tbcPart[0], $event.target.value)"
                                :data-testid="'tbc-shadow-' + propKey + '-' + tbcPart[0]">
                        </label>
                    </template>
                </div>
                <div class="tbc-row">
                    <input type="color" class="tbc-color" :value="ctlShadowHex(propKey)" @input="ctlShadowColor(propKey, $event.target.value, ctlShadowAlpha(propKey))" aria-label="رنگ سایه">
                    <input type="range" min="0" max="100" step="5" class="tbc-range" :value="ctlShadowAlpha(propKey)" @input="ctlShadowColor(propKey, ctlShadowHex(propKey), Number($event.target.value))" aria-label="شفافیت رنگ سایه">
                    <span class="tbc-hint" style="margin:0;width:2.25rem" x-text="ctlShadowAlpha(propKey) + '٪'"></span>
                </div>
                <label class="tbc-check">
                    <input type="checkbox" :checked="ctlShadow(propKey)?.inset ?? false" @change="ctlShadowSet(propKey, 'inset', $event.target.checked)">
                    سایهٔ داخلی (inset)
                </label>
            </div>
        </div>
    </div>
</template>
