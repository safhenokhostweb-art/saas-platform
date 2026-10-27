{{-- Phase 0.6 (ADR-065) — the shared Canvas icon picker (both hosts, every
     list depth). Searchable (Persian or English), filtered by category,
     keyboard navigable; a legacy emoji/text value is shown as-is and can
     still be typed. Needs theme-runtime::canvas.icons on the page.

     @param string $value  JS expression: the current icon value
     @param string $set    JS statement run with `__icon` bound to the new value
     @param string $id     JS expression: unique suffix for ids/test ids
     @param string $label  JS expression: the field's accessible label --}}
<div class="tbip"
    x-data="{
        open: false, q: '', cat: '',
        get value() { const v = ({!! $value !!}); return v == null ? '' : String(v); },
        get results() { return window.CanvasIcons.search(this.q, this.cat); },
        setIcon(__icon) { {!! $set !!}; },
        pick(name, ev) {
            {{-- Resolve the trigger now: the clicked option is torn down with the panel. --}}
            const trigger = ev && ev.currentTarget ? ev.currentTarget.closest('.tbip').querySelector('.tbip-trigger') : null;
            this.setIcon(name);
            this.open = false;
            if (trigger) setTimeout(() => trigger.focus(), 0);
        },
        toggle() {
            this.open = !this.open;
            if (this.open) { this.q = ''; this.cat = ''; this.$nextTick(() => this.$refs.q && this.$refs.q.focus()); }
        },
        move(e, step) {
            const opts = Array.from(this.$refs.grid ? this.$refs.grid.querySelectorAll('.tbip-option') : []);
            if (!opts.length) return;
            const cols = getComputedStyle(this.$refs.grid).gridTemplateColumns.split(' ').length || 1;
            const rtl = getComputedStyle(this.$refs.grid).direction === 'rtl';
            const delta = { left: rtl ? 1 : -1, right: rtl ? -1 : 1, up: -cols, down: cols }[step];
            const i = opts.indexOf(document.activeElement);
            const next = opts[Math.max(0, Math.min(opts.length - 1, i < 0 ? 0 : i + delta))];
            e.preventDefault();
            next.focus();
        },
    }"
    @keydown.escape.stop="if (open) { open = false; $refs.trigger.focus(); }"
    @click.outside="open = false"
    :data-testid="'tb-icon-picker-' + ({!! $id !!})">
    <div class="tbip-row">
        <button type="button" class="tbip-trigger" x-ref="trigger" @click="toggle()"
            :id="'tbip-' + ({!! $id !!})"
            aria-haspopup="dialog" :aria-expanded="open ? 'true' : 'false'"
            :aria-label="({!! $label !!}) + ': ' + window.CanvasIcons.describe(value)"
            :data-testid="'tb-icon-trigger-' + ({!! $id !!})">
            <span class="tbip-current" x-html="window.CanvasIcons.previewHtml(value)" aria-hidden="true"></span>
            <span class="tbip-name" x-text="window.CanvasIcons.describe(value)"></span>
            <svg class="tbip-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <button type="button" class="tbip-clear" x-show="value !== ''" @click="setIcon('')"
            aria-label="حذف آیکون" title="حذف آیکون" :data-testid="'tb-icon-clear-' + ({!! $id !!})">×</button>
    </div>
    <template x-if="open">
        <div class="tbip-panel" role="dialog" :aria-label="'انتخاب آیکون — ' + ({!! $label !!})">
            <input type="search" class="tbip-search" x-ref="q" x-model="q" @input="cat = ''"
                @keydown.arrow-down.prevent="$refs.grid && $refs.grid.querySelector('.tbip-option') && $refs.grid.querySelector('.tbip-option').focus()"
                @keydown.enter.prevent="results.length && pick(results[0].name, $event)"
                placeholder="جستجو: ارسال، تلفن، امنیت، cart…" aria-label="جستجوی آیکون"
                :data-testid="'tb-icon-search-' + ({!! $id !!})">
            <div class="tbip-cats" role="group" aria-label="دسته‌بندی آیکون‌ها">
                <button type="button" class="tbip-cat" :aria-pressed="cat === '' ? 'true' : 'false'" @click="cat = ''">همه</button>
                <template x-for="c in window.CanvasIcons.categories()" :key="c.key">
                    <button type="button" class="tbip-cat" :aria-pressed="cat === c.key ? 'true' : 'false'" @click="cat = c.key"
                        x-text="c.label" :data-testid="'tb-icon-cat-' + ({!! $id !!}) + '-' + c.key"></button>
                </template>
            </div>
            <div class="tbip-grid" role="listbox" x-ref="grid" :aria-label="'آیکون‌ها (' + results.length.toLocaleString('fa-IR') + ')'"
                @keydown.arrow-left="move($event, 'left')" @keydown.arrow-right="move($event, 'right')"
                @keydown.arrow-up="move($event, 'up')" @keydown.arrow-down="move($event, 'down')">
                <template x-for="ic in results" :key="ic.name">
                    <button type="button" role="option" class="tbip-option" @click="pick(ic.name, $event)"
                        :aria-selected="window.CanvasIcons.name(value) === ic.name ? 'true' : 'false'"
                        :title="ic.label" :aria-label="ic.label" x-html="window.CanvasIcons.svg(ic.name)"
                        :data-testid="'tb-icon-option-' + ({!! $id !!}) + '-' + ic.name"></button>
                </template>
            </div>
            <p class="tbip-empty" x-show="results.length === 0">آیکونی با این عبارت پیدا نشد.</p>
            <label class="tbip-custom">
                <span>یا ایموجی/متن دلخواه:</span>
                <input type="text" maxlength="8" :value="window.CanvasIcons.kind(value) === 'legacy' ? value.trim() : ''"
                    @change="setIcon($event.target.value.trim())" :data-testid="'tb-icon-custom-' + ({!! $id !!})">
            </label>
        </div>
    </template>
</div>
