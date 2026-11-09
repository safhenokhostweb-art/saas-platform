/*
 * ADR-082 — the Canvas Inspector's control model, shared by the platform
 * Canvas and customer-site's Canvas editor and tested in Node. DOM-free.
 *
 * A widget schema field may declare, beside its control `type`:
 *
 *   panel        'content' | 'style' | 'advanced' — the Inspector tab
 *                (content when absent: explicit only, nothing moves by type)
 *   group        the section label inside that tab (ADR-076)
 *   condition    { field: value | [values], 'field!': value } — every entry
 *                must hold for the field to show (Elementor's `condition`)
 *   responsive   true — one value per device, stored as key, key_tablet,
 *                key_mobile; a device without its own value inherits the
 *                next wider one. tablet_default / mobile_default seed them.
 *   state_group  + state ('normal' | 'hover' | 'active') — adjacent fields
 *                shown as one control with Normal / Hover / Active tabs
 *
 * The PHP side of the same vocabulary is InspectorFieldRegistry (constants,
 * value normalisation) and WidgetSchemaContract (schema checks); the
 * constants below must stay equal to it (CanvasControlsParityTest).
 */
(function (root) {
  'use strict';

  const PANELS = ['content', 'style', 'advanced'];
  const DEVICES = ['desktop', 'tablet', 'mobile'];
  const DEVICE_SUFFIXES = { desktop: '', tablet: '_tablet', mobile: '_mobile' };
  const DEVICE_LABELS = { desktop: 'دسکتاپ', tablet: 'تبلت', mobile: 'موبایل' };
  const RESPONSIVE_CONTROLS = ['number', 'range', 'select', 'spacing', 'typography'];
  const STATES = ['normal', 'hover', 'active'];
  const STATE_LABELS = { normal: 'عادی', hover: 'هاور', active: 'فعال' };
  const UNITS = ['px', 'rem', 'em', '%', 'vw', 'vh'];

  /** Generic wrapper sections of the Style tab and the block style keys each writes. */
  const GENERIC_STYLE_SECTIONS = {
    typography: ['fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textAlign'],
    colors: ['bgColor', 'textColor', 'bgGradientFrom', 'bgGradientTo', 'bgGradientDir'],
    spacing: ['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'paddingX', 'paddingY', 'marginTop', 'marginBottom'],
    border: ['borderWidth', 'borderStyle', 'borderColor', 'borderRadius'],
    shadow: ['shadow'],
    opacity: ['opacity'],
  };

  /** Logical sides, in the order the control shows them (top, start, bottom, end). */
  const SPACING_SIDES = ['block-start', 'inline-start', 'block-end', 'inline-end'];

  const TYPOGRAPHY_KEYS = ['fontToken', 'size', 'weight', 'lineHeight', 'letterSpacing', 'transform', 'style', 'decoration'];
  const FONT_TOKENS = [
    { value: '', label: 'ارث از قالب' },
    { value: 'body', label: 'فونت متن قالب' },
    { value: 'heading', label: 'فونت عنوان قالب' },
  ];
  const FONT_WEIGHTS = [
    { value: '', label: 'پیش‌فرض' }, { value: '300', label: 'سبک (300)' }, { value: '400', label: 'معمولی (400)' },
    { value: '500', label: 'متوسط (500)' }, { value: '600', label: 'نیمه‌ضخیم (600)' }, { value: '700', label: 'ضخیم (700)' },
    { value: '800', label: 'خیلی ضخیم (800)' }, { value: '900', label: 'سیاه (900)' },
  ];

  const SHADOW_LIMITS = { x: [-100, 100], y: [-100, 100], blur: [0, 200], spread: [-100, 100] };
  const SHADOW_PRESETS = {
    sm: { x: 0, y: 1, blur: 3, spread: 0, color: 'rgba(15, 23, 42, 0.1)', inset: false },
    md: { x: 0, y: 4, blur: 12, spread: -2, color: 'rgba(15, 23, 42, 0.12)', inset: false },
    lg: { x: 0, y: 12, blur: 28, spread: -8, color: 'rgba(15, 23, 42, 0.18)', inset: false },
    xl: { x: 0, y: 24, blur: 48, spread: -12, color: 'rgba(15, 23, 42, 0.24)', inset: false },
  };
  const SHADOW_PRESET_LABELS = { none: 'بدون سایه', sm: 'کم', md: 'متوسط', lg: 'زیاد', xl: 'خیلی زیاد', custom: 'سفارشی' };

  function isEmpty(v) {
    return v === undefined || v === null || v === '' || (Array.isArray(v) && v.length === 0);
  }

  function truthy(v) {
    return v === true || v === 1 || v === '1' || v === 'true';
  }

  function num(v, fallback) {
    const n = typeof v === 'number' ? v : (typeof v === 'string' && v.trim() !== '' ? Number(v) : NaN);
    return Number.isFinite(n) ? n : fallback;
  }

  function clamp(n, min, max) {
    let out = n;
    if (min !== undefined && min !== null && Number.isFinite(Number(min))) out = Math.max(Number(min), out);
    if (max !== undefined && max !== null && Number.isFinite(Number(max))) out = Math.min(Number(max), out);
    return out;
  }

  /* ── Tabs, conditions, states ───────────────────────────────────── */

  function panelOf(def) {
    return PANELS.includes(def?.panel) ? def.panel : 'content';
  }

  function isResponsive(def) {
    return def?.responsive === true && RESPONSIVE_CONTROLS.includes(def?.type);
  }

  /** The field's current value for condition checks: its stored value, else its default. */
  function conditionValue(props, schema, key) {
    const stored = props ? props[key] : undefined;
    return stored === undefined ? schema?.[key]?.default : stored;
  }

  function sameValue(actual, expected) {
    if (typeof expected === 'boolean') return truthy(actual) === expected;
    if (expected === '') return isEmpty(actual);
    if (isEmpty(actual)) return false;
    return String(actual) === String(expected);
  }

  /** True when every entry of the field's `condition` holds. */
  function conditionMet(def, props, schema) {
    const condition = def?.condition;
    if (!condition || typeof condition !== 'object' || Array.isArray(condition)) return true;
    return Object.entries(condition).every(([rawKey, expected]) => {
      const negate = rawKey.endsWith('!');
      const key = negate ? rawKey.slice(0, -1) : rawKey;
      const actual = conditionValue(props, schema, key);
      const match = Array.isArray(expected) ? expected.some((e) => sameValue(actual, e)) : sameValue(actual, expected);
      return negate ? !match : match;
    });
  }

  function activeState(states, group) {
    const state = states && states[group];
    return STATES.includes(state) ? state : 'normal';
  }

  /**
   * The fields a tab shows, in schema order, as an object (x-for keeps the
   * order): the tab's own fields whose condition holds and, inside a
   * state_group, only the field of the group's active state.
   */
  function visibleFields(schema, props, panel, states) {
    const out = {};
    Object.entries(schema || {}).forEach(([key, def]) => {
      if (!def || def.type === 'blocks' || panelOf(def) !== panel) return;
      if (!conditionMet(def, props, schema)) return;
      if (def.state_group && (def.state || 'normal') !== activeState(states, def.state_group)) return;
      out[key] = def;
    });
    return out;
  }

  function panelHasFields(schema, props, panel, states) {
    return Object.keys(visibleFields(schema, props, panel, states)).length > 0;
  }

  /** True where a visible field opens a new section (its group differs from the previous visible field's). */
  function groupStart(fields, key) {
    const keys = Object.keys(fields || {});
    const index = keys.indexOf(key);
    const group = fields?.[key]?.group;
    return !!group && index >= 0 && (index === 0 || fields[keys[index - 1]]?.group !== group);
  }

  /** True where a visible field is the first of its state_group (the state tabs render above it). */
  function stateGroupStart(fields, key) {
    const keys = Object.keys(fields || {});
    const index = keys.indexOf(key);
    const group = fields?.[key]?.state_group;
    return !!group && index >= 0 && (index === 0 || fields[keys[index - 1]]?.state_group !== group);
  }

  /** The states a group offers, in Normal / Hover / Active order. */
  function stateOptions(schema, group) {
    const present = new Set(Object.values(schema || {}).filter((d) => d && d.state_group === group).map((d) => d.state || 'normal'));
    return STATES.filter((s) => present.has(s)).map((s) => ({ state: s, label: STATE_LABELS[s] }));
  }

  /* ── Per-device values ──────────────────────────────────────────── */

  function deviceKey(key, device) {
    return key + (DEVICE_SUFFIXES[device] ?? '');
  }

  /** The key a write on `device` goes to: the device key of a responsive field, else the field's own. */
  function writeKey(key, def, device) {
    return isResponsive(def) ? deviceKey(key, device) : key;
  }

  function deviceDefault(def, device) {
    if (device === 'desktop') return def?.default;
    return def ? def[device + '_default'] : undefined;
  }

  /**
   * A field's value on `device`, with where it came from:
   *   { value, own, from } — `own` is the device's own stored value (or
   *   undefined); `from` names the device the shown value belongs to, or
   *   'default'. Typography merges sub-values across the cascade, so a
   *   phone override of the size alone keeps the desktop weight.
   */
  function read(props, key, def, device) {
    const p = props || {};
    if (!isResponsive(def) || device === 'desktop' || !DEVICES.includes(device)) {
      const own = p[key];
      return { value: isEmpty(own) ? def?.default : own, own, from: isEmpty(own) ? 'default' : 'desktop' };
    }
    const chain = DEVICES.slice(0, DEVICES.indexOf(device) + 1).reverse(); // e.g. mobile, tablet, desktop
    const own = p[deviceKey(key, device)];
    if (def.type === 'typography') {
      const layers = chain.slice().reverse().map((d) => {
        const v = p[deviceKey(key, d)];
        return v && typeof v === 'object' && !Array.isArray(v) ? v : (deviceDefault(def, d) || {});
      });
      return { value: Object.assign({}, ...layers), own, from: isEmpty(own) ? 'inherited' : device };
    }
    for (const d of chain) {
      const v = p[deviceKey(key, d)];
      if (!isEmpty(v)) return { value: v, own, from: d };
      const dflt = deviceDefault(def, d);
      if (d !== 'desktop' && !isEmpty(dflt)) return { value: dflt, own, from: 'default' };
    }
    return { value: def?.default, own, from: 'default' };
  }

  /** True when `device` has a value of its own for a responsive field (the control can reset it). */
  function hasDeviceOverride(props, key, def, device) {
    return isResponsive(def) && device !== 'desktop' && !isEmpty((props || {})[deviceKey(key, device)]);
  }

  /** The devices that hold their own value, for the switch's dots. */
  function overriddenDevices(props, key, def) {
    if (!isResponsive(def)) return [];
    return DEVICES.filter((d) => d !== 'desktop' && !isEmpty((props || {})[deviceKey(key, d)]));
  }

  /** A select's options as [{value, label}], from a {value: label} map or a list. */
  function selectOptions(def) {
    const options = def?.options;
    if (Array.isArray(options)) {
      return options.map((o) => (o && typeof o === 'object' ? { value: String(o.value), label: String(o.label ?? o.value) } : { value: String(o), label: String(o) }));
    }
    return Object.entries(options || {}).map(([value, label]) => ({ value: String(value), label: String(label) }));
  }

  /* ── Range ──────────────────────────────────────────────────────── */

  function rangeUnits(def) {
    const units = Array.isArray(def?.units) ? def.units.filter((u) => UNITS.includes(u)) : [];
    return units.length ? units : [def?.unit && UNITS.includes(def.unit) ? def.unit : 'px'];
  }

  function rangeLimits(def, unit) {
    const own = def?.unit_limits && def.unit_limits[unit] ? def.unit_limits[unit] : {};
    return {
      min: own.min ?? def?.min ?? 0,
      max: own.max ?? def?.max ?? 100,
      step: own.step ?? def?.step ?? 1,
    };
  }

  /** {size, unit} of a stored range value: a number (single unit) or {size, unit}. */
  function rangeParts(value, def) {
    const units = rangeUnits(def);
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      const unit = units.includes(value.unit) ? value.unit : units[0];
      return { size: num(value.size, ''), unit };
    }
    return { size: num(value, ''), unit: units.includes(def?.unit) ? def.unit : units[0] };
  }

  /** The value to store: '' when cleared, a clamped number, or {size, unit} for a multi-unit field. */
  function rangeStore(size, unit, def) {
    const units = rangeUnits(def);
    const u = units.includes(unit) ? unit : units[0];
    const n = num(size, null);
    if (n === null) return '';
    const { min, max } = rangeLimits(def, u);
    const clamped = clamp(n, min, max);
    return units.length > 1 ? { size: clamped, unit: u } : clamped;
  }

  /* ── Spacing (logical sides) ────────────────────────────────────── */

  function spacingValue(value) {
    const v = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    const out = { unit: UNITS.includes(v.unit) ? v.unit : 'px', linked: v.linked !== false };
    SPACING_SIDES.forEach((side) => { out[side] = num(v[side], ''); });
    return out;
  }

  function spacingIsEmpty(value) {
    const v = spacingValue(value);
    return SPACING_SIDES.every((side) => v[side] === '');
  }

  /** Set one side (every side while linked); '' when all sides end up empty. */
  function spacingSet(value, side, size) {
    const v = spacingValue(value);
    const n = num(size, '');
    (v.linked ? SPACING_SIDES : [side]).forEach((s) => { v[s] = n === '' ? '' : Math.max(-500, Math.min(500, n)); });
    return SPACING_SIDES.every((s) => v[s] === '') ? '' : v;
  }

  function spacingSetLinked(value, linked) {
    const v = spacingValue(value);
    v.linked = !!linked;
    if (v.linked) {
      const first = SPACING_SIDES.map((s) => v[s]).find((n) => n !== '');
      SPACING_SIDES.forEach((s) => { v[s] = first === undefined ? '' : first; });
    }
    return v;
  }

  function spacingSetUnit(value, unit) {
    const v = spacingValue(value);
    v.unit = UNITS.includes(unit) ? unit : 'px';
    return v;
  }

  /* ── Typography ─────────────────────────────────────────────────── */

  function typographyValue(value) {
    const v = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    const out = {};
    TYPOGRAPHY_KEYS.forEach((k) => { if (!isEmpty(v[k])) out[k] = v[k]; });
    return out;
  }

  /** Set (or with '' remove) one typography sub-value; '' when nothing is left. */
  function typographySet(value, key, sub) {
    if (!TYPOGRAPHY_KEYS.includes(key)) return typographyValue(value);
    const v = typographyValue(value);
    const numeric = ['size', 'lineHeight', 'letterSpacing'].includes(key);
    const next = numeric ? num(sub, '') : (sub ?? '');
    if (next === '') delete v[key];
    else v[key] = key === 'weight' ? String(next) : next;
    return Object.keys(v).length ? v : '';
  }

  /** A short summary for the collapsed control, e.g. "15px · 600". */
  function typographySummary(value) {
    const v = typographyValue(value);
    const parts = [];
    if (v.fontToken) parts.push((FONT_TOKENS.find((t) => t.value === v.fontToken) || {}).label || v.fontToken);
    if (v.size !== undefined) parts.push(v.size + 'px');
    if (v.weight) parts.push(String(v.weight));
    if (v.lineHeight !== undefined) parts.push('↕ ' + v.lineHeight);
    return parts.length ? parts.join(' · ') : 'پیش‌فرض';
  }

  /* ── Shadow ─────────────────────────────────────────────────────── */

  function shadowValue(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
    const out = {};
    Object.entries(SHADOW_LIMITS).forEach(([k, [min, max]]) => { out[k] = clamp(num(value[k], 0), min, max); });
    out.color = typeof value.color === 'string' && value.color.trim() !== '' ? value.color.trim() : SHADOW_PRESETS.md.color;
    out.inset = value.inset === true;
    return out;
  }

  /** The stored value of a preset: '' for none, a copy of the preset otherwise. */
  function shadowPreset(name) {
    return SHADOW_PRESETS[name] ? { ...SHADOW_PRESETS[name] } : '';
  }

  function shadowPresetOf(value) {
    const v = shadowValue(value);
    if (!v) return 'none';
    const match = Object.entries(SHADOW_PRESETS).find(([, p]) => Object.keys(p).every((k) => p[k] === v[k]));
    return match ? match[0] : 'custom';
  }

  function shadowSet(value, key, sub) {
    const v = shadowValue(value) || { ...SHADOW_PRESETS.md };
    if (key === 'inset') v.inset = !!sub;
    else if (key === 'color') v.color = typeof sub === 'string' && sub.trim() !== '' ? sub.trim() : v.color;
    else if (SHADOW_LIMITS[key]) v[key] = clamp(num(sub, 0), SHADOW_LIMITS[key][0], SHADOW_LIMITS[key][1]);
    return v;
  }

  /** CSS box-shadow of a value, for the control's preview swatch. */
  function shadowCss(value) {
    const v = shadowValue(value);
    if (!v) return 'none';
    return `${v.inset ? 'inset ' : ''}${v.x}px ${v.y}px ${v.blur}px ${v.spread}px ${v.color}`;
  }

  /* ── Generic Style-tab sections ─────────────────────────────────── */

  function hiddenStyleSections(editor) {
    const hidden = editor && editor.hideGenericStyle;
    if (hidden === true) return Object.keys(GENERIC_STYLE_SECTIONS);
    return Array.isArray(hidden) ? hidden.filter((s) => GENERIC_STYLE_SECTIONS[s]) : [];
  }

  function genericStyleVisible(editor, section) {
    return !hiddenStyleSections(editor).includes(section);
  }

  function hiddenStyleKeys(editor) {
    return hiddenStyleSections(editor).flatMap((s) => GENERIC_STYLE_SECTIONS[s]);
  }

  /** True when the block still carries a value in a section its widget hides (saved before it was hidden). */
  function hasHiddenGenericStyle(block, editor) {
    const keys = hiddenStyleKeys(editor);
    if (!keys.length || !block) return false;
    const bags = [block.style, block.responsive?.tablet, block.responsive?.mobile];
    return bags.some((bag) => bag && typeof bag === 'object' && keys.some((k) => !isEmpty(bag[k])));
  }

  /**
   * {style, responsive} without the hidden sections' keys; the Advanced
   * tab's own keys (customClass, hide*, animation*) are kept. Empty bags
   * come back as undefined so the block drops them.
   */
  function withoutHiddenGenericStyle(block, editor) {
    const keys = new Set(hiddenStyleKeys(editor));
    const strip = (bag) => {
      if (!bag || typeof bag !== 'object' || Array.isArray(bag)) return undefined;
      const out = Object.fromEntries(Object.entries(bag).filter(([k]) => !keys.has(k)));
      return Object.keys(out).length ? out : undefined;
    };
    const style = strip(block?.style);
    const responsive = {};
    ['tablet', 'mobile'].forEach((d) => { const b = strip(block?.responsive?.[d]); if (b) responsive[d] = b; });
    return { style, responsive: Object.keys(responsive).length ? responsive : undefined };
  }

  /* ── Editor mixin ───────────────────────────────────────────────── */

  /**
   * Methods both Canvas editors spread into their Alpine component, so the
   * tab filtering, per-device routing and state switching behave the same
   * in both. The host provides: selectedBlock, selectedSchema, selectedTab,
   * activeBreakpoint, setDevice(device), selectedEditorCapabilities,
   * isDirty, autoSaveAndReload(), and ctlSetProp(key, value) — its own prop
   * writer (undefined removes the key). Methods only: a spread copies a
   * getter's value, not the getter.
   */
  function editorMixin() {
    return {
      inspectorStates: {},
      panelFields(panel) {
        return visibleFields(this.selectedSchema, this.selectedBlock?.props ?? {}, panel, this.inspectorStates);
      },
      panelHasFields(panel) {
        return Object.keys(this.panelFields(panel)).length > 0;
      },
      fieldGroupStart(propKey) {
        return groupStart(this.panelFields(this.selectedTab), propKey);
      },
      fieldStateStart(propKey) {
        return stateGroupStart(this.panelFields(this.selectedTab), propKey);
      },
      ctlDef(propKey) {
        return this.selectedSchema?.[propKey] ?? {};
      },
      ctlIsResponsive(propKey) {
        return isResponsive(this.ctlDef(propKey));
      },
      ctlInfo(propKey) {
        return read(this.selectedBlock?.props, propKey, this.ctlDef(propKey), this.activeBreakpoint);
      },
      ctlRead(propKey) {
        return this.ctlInfo(propKey).value;
      },
      /** A toggle's effective state: its stored value, else the schema default (as conditions and renderers read it). */
      ctlChecked(propKey) {
        const v = this.selectedBlock?.props?.[propKey];
        return truthy(v === undefined || v === null || v === '' ? this.ctlDef(propKey).default : v);
      },
      /** True when the shown value is not the active device's own (it is inherited or a default). */
      ctlInherited(propKey) {
        return this.ctlIsResponsive(propKey) && this.activeBreakpoint !== 'desktop' && isEmpty(this.ctlInfo(propKey).own);
      },
      ctlWrite(propKey, value) {
        const key = writeKey(propKey, this.ctlDef(propKey), this.activeBreakpoint);
        this.ctlSetProp(key, key !== propKey && isEmpty(value) ? undefined : value);
      },
      ctlReset(propKey) {
        if (this.activeBreakpoint === 'desktop' || !this.ctlIsResponsive(propKey)) return;
        this.ctlSetProp(deviceKey(propKey, this.activeBreakpoint), undefined);
      },
      ctlOverrides(propKey) {
        return overriddenDevices(this.selectedBlock?.props, propKey, this.ctlDef(propKey));
      },
      ctlSetDevice(device) {
        if (DEVICES.includes(device)) this.setDevice(device);
      },
      ctlRange(propKey) {
        return rangeParts(this.ctlRead(propKey), this.ctlDef(propKey));
      },
      ctlRangeSet(propKey, size, unit) {
        this.ctlWrite(propKey, rangeStore(size, unit, this.ctlDef(propKey)));
      },
      ctlSpacing(propKey) {
        return spacingValue(this.ctlRead(propKey));
      },
      ctlSpacingSet(propKey, side, size) {
        this.ctlWrite(propKey, spacingSet(this.ctlRead(propKey), side, size));
      },
      ctlSpacingLink(propKey, linked) {
        this.ctlWrite(propKey, spacingSetLinked(this.ctlRead(propKey), linked));
      },
      ctlSpacingUnit(propKey, unit) {
        this.ctlWrite(propKey, spacingSetUnit(this.ctlRead(propKey), unit));
      },
      ctlTypography(propKey) {
        return typographyValue(this.ctlRead(propKey));
      },
      /** A typography sub-value goes to the active device's own object, so other devices keep theirs. */
      ctlTypographySet(propKey, sub, value) {
        const def = this.ctlDef(propKey);
        const key = writeKey(propKey, def, this.activeBreakpoint);
        this.ctlSetProp(key, typographySet(this.selectedBlock?.props?.[key], sub, value) || (key === propKey ? '' : undefined));
      },
      ctlShadow(propKey) {
        return shadowValue(this.ctlRead(propKey));
      },
      ctlShadowPreset(propKey, name) {
        this.ctlWrite(propKey, shadowPreset(name));
      },
      ctlShadowSet(propKey, sub, value) {
        this.ctlWrite(propKey, shadowSet(this.ctlRead(propKey), sub, value));
      },
      ctlShadowColor(propKey, hex, alpha) {
        const color = root.CanvasColor ? root.CanvasColor.fixed(hex, alpha) : hex;
        if (color) this.ctlShadowSet(propKey, 'color', color);
      },
      ctlShadowAlpha(propKey) {
        const v = this.ctlShadow(propKey);
        return v && root.CanvasColor ? root.CanvasColor.parse(v.color).alpha : 100;
      },
      ctlShadowHex(propKey) {
        const v = this.ctlShadow(propKey);
        return v && root.CanvasColor ? root.CanvasColor.inputHex(v.color, null, '#0f172a') : '#0f172a';
      },
      ctlStateOptions(propKey) {
        return stateOptions(this.selectedSchema, this.ctlDef(propKey).state_group);
      },
      ctlState(propKey) {
        return activeState(this.inspectorStates, this.ctlDef(propKey).state_group);
      },
      ctlSetState(propKey, state) {
        const group = this.ctlDef(propKey).state_group;
        if (group && STATES.includes(state)) this.inspectorStates = { ...this.inspectorStates, [group]: state };
      },
      genericStyleVisible(section) {
        return genericStyleVisible(this.selectedEditorCapabilities, section);
      },
      hasHiddenGenericStyle() {
        return hasHiddenGenericStyle(this.selectedBlock, this.selectedEditorCapabilities);
      },
      /** Drop the values a widget's hidden generic sections still hold; Advanced-tab keys stay. */
      clearHiddenGenericStyle() {
        const block = this.selectedBlock;
        if (!block) return;
        const { style, responsive } = withoutHiddenGenericStyle(block, this.selectedEditorCapabilities);
        if (style) block.style = style; else delete block.style;
        if (responsive) block.responsive = responsive; else delete block.responsive;
        this.isDirty = true;
        this.autoSaveAndReload();
      },
    };
  }

  const api = {
    version: 1,
    editorMixin,
    PANELS, DEVICES, DEVICE_SUFFIXES, DEVICE_LABELS, RESPONSIVE_CONTROLS, STATES, STATE_LABELS, UNITS,
    GENERIC_STYLE_SECTIONS, SPACING_SIDES, TYPOGRAPHY_KEYS, FONT_TOKENS, FONT_WEIGHTS,
    SHADOW_LIMITS, SHADOW_PRESETS, SHADOW_PRESET_LABELS,
    isEmpty, panelOf, isResponsive, conditionMet, activeState, visibleFields, panelHasFields,
    groupStart, stateGroupStart, stateOptions,
    deviceKey, writeKey, read, hasDeviceOverride, overriddenDevices, selectOptions,
    rangeUnits, rangeLimits, rangeParts, rangeStore,
    spacingValue, spacingIsEmpty, spacingSet, spacingSetLinked, spacingSetUnit,
    typographyValue, typographySet, typographySummary,
    shadowValue, shadowPreset, shadowPresetOf, shadowSet, shadowCss,
    hiddenStyleSections, genericStyleVisible, hasHiddenGenericStyle, withoutHiddenGenericStyle,
  };
  root.CanvasControls = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
