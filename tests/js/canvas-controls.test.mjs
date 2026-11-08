import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// ADR-082: the Inspector's control model (tabs, conditions, devices, states, values).
const runtime = '../../packages/saas-platform/modules/theme-runtime/resources/js/';
const context = vm.createContext({ window: {}, Math, Number, String, Object, Array, Set, JSON });
vm.runInContext(fs.readFileSync(new URL(runtime + 'canvas-color.js', import.meta.url), 'utf8'), context);
vm.runInContext(fs.readFileSync(new URL(runtime + 'canvas-controls.js', import.meta.url), 'utf8'), context);
const C = context.window.CanvasControls;
const plain = (v) => JSON.parse(JSON.stringify(v));

const schema = {
  logo_text: { type: 'string', default: '' },
  show_search: { type: 'boolean', default: false },
  search_mode: { type: 'select', options: { input: 'فیلد', icon: 'آیکون' }, default: 'input', condition: { show_search: true } },
  search_placeholder: { type: 'string', default: '', condition: { show_search: true, search_mode: 'input' } },
  cta_text: { type: 'string', default: '' },
  cta_url: { type: 'link', default: '/', condition: { 'cta_text!': '' } },
  bg_color: { type: 'color', default: '#fff', panel: 'style', group: 'رنگ‌ها' },
  menu_color: { type: 'color', default: '', panel: 'style', group: 'منو', state_group: 'menu_colors', state: 'normal' },
  menu_color_hover: { type: 'color', default: '', panel: 'style', group: 'منو', state_group: 'menu_colors', state: 'hover' },
  menu_color_active: { type: 'color', default: '', panel: 'style', group: 'منو', state_group: 'menu_colors', state: 'active' },
  logo_height: { type: 'range', unit: 'px', min: 16, max: 120, default: 40, mobile_default: 32, responsive: true, panel: 'style', group: 'منو' },
  sticky: { type: 'boolean', default: false, panel: 'advanced' },
  children: { type: 'blocks', default: [] },
};

test('a field is in the content tab unless it declares a panel', () => {
  assert.equal(C.panelOf(schema.logo_text), 'content');
  assert.equal(C.panelOf(schema.bg_color), 'style');
  assert.equal(C.panelOf({ type: 'color' }), 'content', 'never inferred from the control type');
  assert.equal(C.panelOf({ panel: 'responsive' }), 'content', 'unknown panels fall back to content');
});

test('conditions: equality, booleans, negation of empty, lists', () => {
  assert.equal(C.conditionMet(schema.search_mode, {}, schema), false, 'show_search defaults to false');
  assert.equal(C.conditionMet(schema.search_mode, { show_search: true }, schema), true);
  assert.equal(C.conditionMet(schema.search_mode, { show_search: '1' }, schema), true, 'truthy strings count as true');
  assert.equal(C.conditionMet(schema.search_placeholder, { show_search: true, search_mode: 'icon' }, schema), false);
  assert.equal(C.conditionMet(schema.search_placeholder, { show_search: true }, schema), true, 'search_mode default input');
  assert.equal(C.conditionMet(schema.cta_url, { cta_text: '' }, schema), false);
  assert.equal(C.conditionMet(schema.cta_url, { cta_text: 'خرید' }, schema), true);
  assert.equal(C.conditionMet({ condition: { search_mode: ['icon', 'input'] } }, { search_mode: 'icon' }, schema), true);
  assert.equal(C.conditionMet({ condition: { 'search_mode!': ['icon'] } }, { search_mode: 'icon' }, schema), false);
  assert.equal(C.conditionMet({}, {}, schema), true, 'no condition always shows');
});

test('each tab lists its own visible fields in schema order, without child lists', () => {
  assert.deepEqual(Object.keys(C.visibleFields(schema, {}, 'content', {})), ['logo_text', 'show_search', 'cta_text']);
  assert.deepEqual(Object.keys(C.visibleFields(schema, { show_search: true, cta_text: 'x' }, 'content', {})),
    ['logo_text', 'show_search', 'search_mode', 'search_placeholder', 'cta_text', 'cta_url']);
  assert.deepEqual(Object.keys(C.visibleFields(schema, {}, 'style', {})), ['bg_color', 'menu_color', 'logo_height']);
  assert.deepEqual(Object.keys(C.visibleFields(schema, {}, 'advanced', {})), ['sticky']);
  assert.equal(C.panelHasFields(schema, {}, 'advanced', {}), true);
  assert.equal(C.panelHasFields({ a: { type: 'string' } }, {}, 'style', {}), false);
});

test('a state group shows only its active state, and its tabs start where it starts', () => {
  const hover = C.visibleFields(schema, {}, 'style', { menu_colors: 'hover' });
  assert.deepEqual(Object.keys(hover), ['bg_color', 'menu_color_hover', 'logo_height']);
  assert.equal(C.stateGroupStart(hover, 'menu_color_hover'), true);
  assert.equal(C.stateGroupStart(hover, 'logo_height'), false);
  assert.deepEqual(plain(C.stateOptions(schema, 'menu_colors')).map((o) => o.state), ['normal', 'hover', 'active']);
  assert.equal(C.activeState({}, 'menu_colors'), 'normal');
  assert.equal(C.activeState({ menu_colors: 'bogus' }, 'menu_colors'), 'normal');
});

test('section headers follow the visible list, not the raw schema', () => {
  const style = C.visibleFields(schema, {}, 'style', {});
  assert.equal(C.groupStart(style, 'bg_color'), true);
  assert.equal(C.groupStart(style, 'menu_color'), true);
  assert.equal(C.groupStart(style, 'logo_height'), false, 'same group as the previous visible field');
});

test('responsive values: device keys, device defaults and inheritance', () => {
  const def = schema.logo_height;
  assert.equal(C.deviceKey('logo_height', 'tablet'), 'logo_height_tablet');
  assert.equal(C.writeKey('logo_height', def, 'mobile'), 'logo_height_mobile');
  assert.equal(C.writeKey('logo_text', schema.logo_text, 'mobile'), 'logo_text', 'non-responsive fields keep their key');

  assert.deepEqual(plain(C.read({}, 'logo_height', def, 'desktop')), { value: 40, from: 'default' });
  assert.equal(C.read({ logo_height: 56 }, 'logo_height', def, 'tablet').value, 56, 'tablet inherits desktop');
  assert.equal(C.read({ logo_height: 56 }, 'logo_height', def, 'tablet').from, 'desktop');
  assert.equal(C.read({ logo_height: 56 }, 'logo_height', def, 'mobile').value, 32, 'a device default wins over inheritance');
  assert.equal(C.read({ logo_height: 56, logo_height_tablet: 44 }, 'logo_height', def, 'tablet').from, 'tablet');
  assert.equal(C.read({ logo_height_mobile: 28 }, 'logo_height', def, 'mobile').value, 28);
  assert.deepEqual(plain(C.overriddenDevices({ logo_height_mobile: 28 }, 'logo_height', def)), ['mobile']);
  assert.equal(C.hasDeviceOverride({ logo_height_tablet: 30 }, 'logo_height', def, 'tablet'), true);
  assert.equal(C.isResponsive({ type: 'color', responsive: true }), false, 'only responsive-capable controls');
});

test('typography merges sub-values down the device cascade', () => {
  const def = { type: 'typography', responsive: true, default: {} };
  const props = { t: { size: 16, weight: '600' }, t_mobile: { size: 14 } };
  assert.deepEqual(plain(C.read(props, 't', def, 'mobile').value), { size: 14, weight: '600' });
  assert.deepEqual(plain(C.read(props, 't', def, 'tablet').value), { size: 16, weight: '600' });
  assert.deepEqual(plain(C.typographySet({ size: 16 }, 'weight', 700)), { size: 16, weight: '700' });
  assert.equal(C.typographySet({ size: 16 }, 'size', ''), '', 'nothing left means empty');
  assert.deepEqual(plain(C.typographySet({}, 'bogus', 1)), {}, 'unknown keys are ignored');
  assert.equal(C.typographySummary({ size: 15, weight: '600' }), '15px · 600');
  assert.equal(C.typographySummary(''), 'پیش‌فرض');
});

test('range: clamping, clearing and multi-unit storage', () => {
  const single = { type: 'range', unit: 'px', min: 16, max: 120 };
  assert.equal(C.rangeStore('200', 'px', single), 120);
  assert.equal(C.rangeStore('', 'px', single), '');
  assert.deepEqual(plain(C.rangeParts(40, single)), { size: 40, unit: 'px' });
  const multi = { type: 'range', units: ['px', '%'], unit: 'px', min: 0, max: 400, unit_limits: { '%': { min: 0, max: 100 } } };
  assert.deepEqual(plain(C.rangeStore('150', '%', multi)), { size: 100, unit: '%' });
  assert.deepEqual(plain(C.rangeParts({ size: 50, unit: '%' }, multi)), { size: 50, unit: '%' });
  assert.deepEqual(plain(C.rangeParts(80, multi)), { size: 80, unit: 'px' }, 'a legacy number reads as the default unit');
  assert.deepEqual(plain(C.rangeLimits(multi, '%')), { min: 0, max: 100, step: 1 });
});

test('spacing uses logical sides and is linked by default', () => {
  const linked = C.spacingSet('', 'block-start', 12);
  assert.deepEqual(plain(linked), { unit: 'px', linked: true, 'block-start': 12, 'inline-start': 12, 'block-end': 12, 'inline-end': 12 });
  const unlinked = C.spacingSet(C.spacingSetLinked(linked, false), 'inline-start', 20);
  assert.equal(unlinked['inline-start'], 20);
  assert.equal(unlinked['block-start'], 12);
  assert.equal(C.spacingSet({ linked: true, 'block-start': 4 }, 'block-start', ''), '', 'clearing every side empties the value');
  assert.equal(C.spacingSetUnit(linked, 'rem').unit, 'rem');
  assert.equal(C.spacingSetUnit(linked, 'cm').unit, 'px', 'units are allowlisted');
  assert.ok(!Object.keys(C.spacingValue({ left: 5 })).includes('left'), 'physical keys are never produced');
});

test('shadow presets, custom values and CSS', () => {
  assert.equal(C.shadowPreset('none'), '');
  assert.equal(C.shadowPresetOf(''), 'none');
  assert.equal(C.shadowPresetOf(C.shadowPreset('md')), 'md');
  const custom = C.shadowSet(C.shadowPreset('md'), 'blur', 999);
  assert.equal(custom.blur, 200, 'clamped to the PHP limits');
  assert.equal(C.shadowPresetOf(custom), 'custom');
  assert.equal(C.shadowCss({ x: 0, y: 2, blur: 4, spread: 0, color: '#000', inset: true }), 'inset 0px 2px 4px 0px #000');
  assert.equal(C.shadowCss(''), 'none');
});

test('hidden generic style sections, leftover values and clearing them', () => {
  assert.deepEqual(plain(C.hiddenStyleSections({ hideGenericStyle: true })), Object.keys(C.GENERIC_STYLE_SECTIONS));
  assert.deepEqual(plain(C.hiddenStyleSections({ hideGenericStyle: ['colors', 'nope'] })), ['colors']);
  assert.equal(C.genericStyleVisible({}, 'colors'), true);
  assert.equal(C.genericStyleVisible({ hideGenericStyle: ['colors'] }, 'colors'), false);

  const editor = { hideGenericStyle: true };
  const block = { style: { bgColor: '#000', customClass: 'x', hideMobile: true }, responsive: { mobile: { paddingTop: 4 } } };
  assert.equal(C.hasHiddenGenericStyle(block, editor), true);
  assert.equal(C.hasHiddenGenericStyle({ style: { customClass: 'x' } }, editor), false, 'Advanced-tab keys are not generic style');
  assert.deepEqual(plain(C.withoutHiddenGenericStyle(block, editor)), { style: { customClass: 'x', hideMobile: true } });
});

test('selectOptions accepts a map or a list', () => {
  assert.deepEqual(plain(C.selectOptions({ options: { a: 'الف', 1: 'یک' } })).map((o) => o.value).sort(), ['1', 'a']);
  assert.deepEqual(plain(C.selectOptions({ options: [{ value: 'x', label: 'ایکس' }, 'y'] })), [{ value: 'x', label: 'ایکس' }, { value: 'y', label: 'y' }]);
});

test('the editor mixin routes writes to the active device and switches states', () => {
  const writes = [];
  const host = {
    selectedBlock: { props: { logo_height: 50 } }, selectedSchema: schema, selectedTab: 'style',
    activeBreakpoint: 'tablet', setDevice(d) { this.activeBreakpoint = d; },
    selectedEditorCapabilities: {}, isDirty: false, autoSaveAndReload() {},
    ctlSetProp(key, value) { writes.push([key, value]); if (value === undefined) delete this.selectedBlock.props[key]; else this.selectedBlock.props[key] = value; },
    ...C.editorMixin(),
  };
  assert.equal(host.ctlInherited('logo_height'), true);
  host.ctlRangeSet('logo_height', 44, 'px');
  assert.deepEqual(writes.at(-1), ['logo_height_tablet', 44]);
  assert.equal(host.ctlInherited('logo_height'), false);
  host.ctlReset('logo_height');
  assert.deepEqual(writes.at(-1), ['logo_height_tablet', undefined]);
  host.ctlSetDevice('desktop');
  host.ctlRangeSet('logo_height', 60, 'px');
  assert.deepEqual(writes.at(-1), ['logo_height', 60]);

  assert.deepEqual(Object.keys(host.panelFields('style')), ['bg_color', 'menu_color', 'logo_height']);
  host.ctlSetState('menu_color', 'active');
  assert.deepEqual(Object.keys(host.panelFields('style')), ['bg_color', 'menu_color_active', 'logo_height']);
  assert.equal(host.fieldStateStart('menu_color_active'), true);

  host.activeBreakpoint = 'mobile';
  host.selectedSchema = { ...schema, t: { type: 'typography', responsive: true, panel: 'style' } };
  host.selectedBlock.props.t = { size: 16, weight: '700' };
  host.ctlTypographySet('t', 'size', 13);
  assert.deepEqual(plain(writes.at(-1)), ['t_mobile', { size: 13 }], 'only the changed sub-value is stored per device');
  assert.deepEqual(plain(host.ctlTypography('t')), { size: 13, weight: '700' });
});
