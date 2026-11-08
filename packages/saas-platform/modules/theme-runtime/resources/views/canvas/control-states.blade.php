{{-- ADR-082 — the Normal / Hover / Active tabs above a state_group. Each
     state is its own schema key; the tabs only pick which one the Inspector
     shows (CanvasControls.visibleFields). Rendered by the host's field loop
     where fieldStateStart(propKey) is true. --}}
<div class="tbc-states" role="tablist" aria-label="حالت نمایش" :data-testid="'tbc-states-' + ctlDef(propKey).state_group">
    <template x-for="tbcState in ctlStateOptions(propKey)" :key="tbcState.state">
        <button type="button" role="tab" class="tbc-state" @click="ctlSetState(propKey, tbcState.state)"
            :aria-selected="ctlState(propKey) === tbcState.state ? 'true' : 'false'"
            x-text="tbcState.label" :data-testid="'tbc-state-' + ctlDef(propKey).state_group + '-' + tbcState.state"></button>
    </template>
</div>
